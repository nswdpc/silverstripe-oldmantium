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
        $client = new GuzzleHttpClient();
        $token = self::config()->get('auth_token');
        $this->client = new ApiClient($client, $token);
        return $this->client;
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
    public function purgeTags(array $tags) : ?ApiResponse {
        if(empty($tags)) {
            return null;
        }
        $client = $this->getApiClient();
        if(!$client) {
            return null;
        }
        $response = $client->purgeTags($this->getZoneIdentifier(), $tags);
        return $response;
    }

    /**
     * Purge cache by hosts immediately
     */
    public function purgeHosts(array $hosts) : ?ApiResponse {
        if(empty($hosts)) {
            return null;
        }
        $client = $this->getApiClient();
        if(!$client) {
            return null;
        }
        $response = $client->purgeHosts($this->getZoneIdentifier(), $hosts);
        return $response;
    }

    /**
     * Purge cache by urls immediately
     * This method modifies the URLs provided to ensure they are absolute URLs
     */
    public function purgeURLs(array $urls) : ?ApiResponse {

        if(empty($urls)) {
            return null;
        }
        $client = $this->getApiClient();
        if(!$client) {
            return null;
        }
        $urls = $this->prepUrls($urls);
        $response = $client->purgeUrls($this->getZoneIdentifier(), $urls);
        return $response;
    }

    /**
     * Purge by prefix
     */
    public function purgePrefixes(array $prefixes) {
        if(empty($prefixes)) {
            return null;
        }
        $client = $this->getApiClient();
        if(!$client) {
            return null;
        }
        $response = $client->purgePrefixes($this->getZoneIdentifier(), $prefixes);
        return $response;
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
     * Given a page, purge its absolute links
     * @param SiteTree $page page record
     */
    public function purgePage(SiteTree $page) : ApiResponse {
        $urls = [];
        $baseURL = self::config()->get('base_url');
        if($baseURL) {
            $url = Controller::join_links($baseURL, $page->Link());
        } else {
            $url = $page->AbsoluteLink();
        }
        $urls[] = $url;
        return $this->purgeURLs($urls);
    }

}
