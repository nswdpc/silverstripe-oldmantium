<?php

namespace NSWDPC\Utilities\Cloudflare;

use GuzzleHttp\Client as GuzzleHttpClient;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Path;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Security;
use SilverStripe\Security\Permission;
use SilverStripe\Versioned\Versioned;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Purge cache service
 *
 * @author James
 */
class CloudflarePurgeService {

    use Injectable;

    use Configurable;

    /**
     * @var int
     * Delay purge all by this many hours (allows undo)
     */
    private static $purge_all_delay = 1;

    /**
     * @var boolean
     * @config
     */
    private static $enabled = false;

    /**
     * @var string
     */
    private static $api_token = '';

    /**
     * @var string
     */
    private static $zone_id = '';

    /**
     * @var string
     */
    private static $base_url = '';

    /**
     * @var ApiClient
     */
    protected $client = null;

    /**
     * @var string
     */
    const TYPE_HOST = 'Host';

    /**
     * @var string
     */
    const TYPE_TAG = 'Tag';

    /**
     * @var string
     */
    const TYPE_PREFIX = 'Prefix';

    /**
     * @var string
     */
    const TYPE_URL = 'URL';

    /**
     * @var string
     */
    const TYPE_ENTIRE = 'Entire';

    /**
     * @var int
     */
    const URL_LIMIT_PER_REQUEST = 30;


    private static bool $emit_headers_in_modeladmin = true;

    /**
     * @inheritdoc
     */
    public function __construct()
    {
    }

    /**
     * Remove reading mode from URLs
     * @param array $urls each value is a string URL that can be parsed by parse_url()
     * @return void
     */
    public static function removeReadingMode( array &$urls ) {
        array_walk(
            $urls,
            function(&$value, $key) {

                $parts = parse_url($value);

                $query = [];
                if(isset($parts['query'])) {
                    parse_str($parts['query'], $query);
                }

                // bail if no stage
                if(!isset($query['stage'])) {
                    return;
                }

                unset($query['stage']);

                $url = "";
                if(isset($parts['scheme'])) {
                    $url .= $parts['scheme'] . "://";
                }
                if(isset($parts['host'])) {
                    $url .= $parts['host'];
                }
                if(isset($parts['port'])) {
                    $url .= ":" . $parts['port'];
                }
                if(isset($parts['path'])) {
                    $url .= $parts['path'];
                }
                if(count($query) > 0) {
                    $url .= "?" . http_build_query($query);
                }

                $value = $url;
            }
        );
    }

    /**
     * Retrieve the ApiClient
     * @return ApiClient|null
     */
    public function getApiClient() : ?ApiClient {
        if(!self::config()->get('enabled')) {
            return null;
        }
        if($this->client) {
            return $this->client;
        }
        $this->client = $this->createApiClient();
        return $this->client;
    }

    /**
     * Helper method to get client
     */
    public function getAdapter() : ?ApiClient {
        return $this->getApiClient();
    }

    /**
     * Create a new API client
     */
    protected function createApiClient(): ApiClient {
        $client = new GuzzleHttpClient();
        $token = self::config()->get('auth_token');
        return new ApiClient($client, $token);
    }

    /**
     * Convert URLs to absolute
     */
    public function prepUrls(array $urls) : array {

        // Remove any reading mode added to the URL in query string
        static::removeReadingMode($urls);

        // ensure URLs are absolute
        array_walk(
            $urls,
            function(&$value, $key) {
                $value = Director::absoluteURL($value);
            }
        );

        return $urls;
    }

    /**
     * Replace the scheme/host with the configured base URL if it exists
     * This is gnerally used by tests to validate base_url functionality
     */
    public static function replaceWithBaseUrl(array $urls) : array {

        $baseURL = self::config()->get('base_url');
        if(!$baseURL) {
            return $urls;
        }

        $scheme = parse_url($baseURL, PHP_URL_SCHEME);
        $host = parse_url($baseURL, PHP_URL_HOST);

        if(!$scheme) {
            throw new \Exception("base_url needs to have a scheme");
        }
        if(!$host) {
            throw new \Exception("base_url needs to have a host");
        }

        // Replace base_url
        $updatedUrls = [];
        foreach($urls as $url) {

            // gather parts from URL provide
            $path = parse_url($url, PHP_URL_PATH);
            $port = parse_url($url, PHP_URL_PORT);
            $query = parse_url($url, PHP_URL_QUERY);

            // use base URL parts for these components
            $newUrl = $scheme . "://";
            $newUrl .= $host;
            if($port) {
                $newUrl .= ":" . $port;
            }
            if($path) {
                $newUrl .= $path;
            }
            if($query) {
                $newUrl .= "?" . $query;
            }

            $updatedUrls[] = $newUrl;

        }

        return $updatedUrls;
    }

    /**
     * Purge all from zone by creating a cache purge job in the future (which handles the purging)
     * The idea here is that job will be created in the future with a configured delay (hrs)
     * This allows job cancellation and manual actioning
     * Only members with the permission ADMIN may create this job (in this method)
     */
    public function purgeAll() : bool
    {
        $member = Security::getCurrentUser();
        if(!Permission::checkMember($member, 'ADMIN')) {
            return false;
        }
        $job = new EntireCachePurgeJob();
        $start = new \DateTime();
        $delay = $this->config()->get('purge_all_delay');
        if($delay > 0) {
            $start->modify("+{$delay} hours");
        }
        $result = false;
        // Logger::log("Cloudflare: purging all (via job)");
        if($job_id = QueuedJobService::singleton()->queueJob(
                        $job,
                        $start->format('Y-m-d H:i:s')
        )) {
            $descriptor = QueuedJobDescriptor::get()->byId($job_id);
            $result = $descriptor && $descriptor->exists();
        }
        return $result;
    }

    /**
     * Purge cache by tags immediately
     * @return ApiResponse
     */
    public function purgeTags(array $tags, array $extraHeaders = []) : ?ApiResponse {
        try {
            if(empty($tags)) {
                return null;
            }
            $client = $this->getApiClient();
            if(!$client) {
                return null;
            }
            $response = $client->purgeTags($this->getZoneIdentifier(), $tags, $extraHeaders);
            return $response;
        } catch (\Exception $exception) {
            Logger::log("Failed to purge tags " . implode(",", $tags) . " with error: " . $exception->getMessage(), "NOTICE");
            return null;
        }
    }

    /**
     * Purge cache by hosts immediately
     */
    public function purgeHosts(array $hosts, array $extraHeaders = []) : ?ApiResponse {
        try {
            if(empty($hosts)) {
                return null;
            }
            $client = $this->getApiClient();
            if(!$client) {
                return null;
            }
            $response = $client->purgeHosts($this->getZoneIdentifier(), $hosts, $extraHeaders);
            return $response;
        } catch (\Exception $exception) {
            Logger::log("Failed to purge hosts " . implode(",", $hosts) . " with error: " . $exception->getMessage(), "NOTICE");
            return null;
        }
    }

    /**
     * Purge cache by urls immediately
     * This method modifies the URLs provided to ensure they are absolute URLs
     */
    public function purgeURLs(array $urls, array $extraHeaders = []) : ?ApiResponse {
        try {
            if(empty($urls)) {
                return null;
            }
            $client = $this->getApiClient();
            if(!$client) {
                return null;
            }
            $urls = $this->prepUrls($urls);
            $response = $client->purgeUrls($this->getZoneIdentifier(), $urls, $extraHeaders);
            return $response;
        } catch (\Exception $exception) {
            Logger::log("Failed to purge URLs " . implode(",", $urls) . " with error: " . $exception->getMessage(), "NOTICE");
            return null;
        }
    }

    /**
     * Purge by prefix
     */
    public function purgePrefixes(array $prefixes, array $extraHeaders = []) {
        try {
            if(empty($prefixes)) {
                return null;
            }
            $client = $this->getApiClient();
            if(!$client) {
                return null;
            }
            $response = $client->purgePrefixes($this->getZoneIdentifier(), $prefixes, $extraHeaders);
            return $response;
        } catch (\Exception $exception) {
            Logger::log("Failed to purge prefixes " . implode(",", $prefixes) . " with error: " . $exception->getMessage(), "NOTICE");
            return null;
        }
    }

    /**
     * Get configured Zone ID
     */
    public function getZoneIdentifier() : ?string {
        return self::config()->get('zone_id');
    }

    /**
     * Map types to the options that can be provided to purge_cache API method called by cachePurge
     * @return array
     */
    public static function getTypeMappings() {
        return [
            self::TYPE_HOST => 'hosts',
            self::TYPE_TAG => 'tags',
            self::TYPE_PREFIX => 'prefixes',
            self::TYPE_URL => 'files'
        ];
    }

    /**
     * Method to purge a DataObject that has an AbsoluteLink() or Link() method (if using base_url for latter)
     */
    final protected function purgeRecordWithAbsoluteLink(DataObject $record, array $extraHeaders = []): ?ApiResponse {
        $urls = [];
        $baseURL = self::config()->get('base_url');
        if($baseURL) {
            $url = Controller::join_links($baseURL, $record->Link());
        } else {
            $url = $record->AbsoluteLink();
        }
        if(!$url) {
            // cannot purge if no value
            return null;
        }
        $urls[] = $url;
        return $this->purgeURLs($urls, $extraHeaders);
    }

    /**
     * Given a page, purge its published URL
     */
    public function purgePage(SiteTree $page, array $extraHeaders = []) : ?ApiResponse {
        return $this->purgeRecordWithAbsoluteLink($page, $extraHeaders);
    }

    /**
     * Give a file, purge its published URL
     * This is functionally the same as purgePage as the API is consistent between the two
     */
    public function purgeFile(File $file, array $extraHeaders = []) : ?ApiResponse {
        return $this->purgeRecordWithAbsoluteLink($file, $extraHeaders);
    }

    /**
     * Purge an object
     */
    public function purgeRecord(object $object, array $extraHeaders = []) : ?ApiResponse {
        try {
            if(method_exists($object, 'getPurgeUrlList') || (method_exists($object, 'hasMethod') && $object->hasMethod('getPurgeUrlList'))) {
                // custom record handling - allows purge of multiple URLs linked to an object
                $urls = $object->getPurgeUrlList();
                if(!is_array($urls)) {
                    throw new \InvalidArgumentException("Object with getPurgeUrlList method should return an array of urls from that method");
                } else {
                    return $this->logResultOf($this->purgeURLs($urls, $extraHeaders));
                }
            } else if($object instanceof SiteTree) {
                return $this->logResultOf($this->purgePage($object, $extraHeaders));
            } else if($object instanceof File) {
                return $this->logResultOf($this->purgeFile($object, $extraHeaders));
            } else {
                throw new \InvalidArgumentException("Object should be a SiteTree, File or have a getPurgeUrlList method");
            }
        } catch (\InvalidArgumentException $invalidArgumentException) {
            Logger::log("purgeRecord failed: " . $invalidArgumentException->getMessage(), "NOTICE");
        } catch (\Exception $exception) {
            Logger::log("purgeRecord general exception: " . $exception->getMessage(), "NOTICE");
        }
        return null;
    }

    /**
     * Proxy the ApiResponse, log the result, and return it
     */
    protected function logResultOf(?ApiResponse $apiResponse = null): ?ApiResponse {
        if($apiResponse) {
            $results = $apiResponse->getAllResults();
            if($results != []) {

                // Log results at expected levels
                array_walk($results, function($result, $key) {
                    $level = $result == "success" ? "INFO" : "NOTICE";
                    Logger::log("CloudflarePurgeService: {$key}={$result}", $level);
                });

                if(static::config()->get('emit_headers_in_modeladmin')) {
                    // Add headers to response if in administration area
                    // NB: due to the way AssetAdmin handles responses, these are not added to the final response
                    $controller = Controller::has_curr() ? Controller::curr() : null;
                    if($controller && ($controller instanceof \SilverStripe\Admin\LeftAndMain)) {
                        $response = $controller->getResponse();
                        if(isset($results['success'])) {
                            $response->addHeader("X-CF-Purge-Success", $results['success']);
                        }
                        if(isset($results['exception'])) {
                            $response->addHeader("X-CF-Purge-Exception", $results['exception']);
                        }
                        if(isset($results['error'])) {
                            $response->addHeader("X-CF-Purge-Error", $results['error']);
                        }
                    }
                }
            }
        }
        return $apiResponse;
    }

}
