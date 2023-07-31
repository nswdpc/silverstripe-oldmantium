<?php

namespace NSWDPC\Utilities\Cloudflare;

use Cloudflare\API\Auth\Auth;
use Cloudflare\API\Auth\APIKey;
use Cloudflare\API\Auth\APIToken;
use Cloudflare\API\Adapter\Guzzle as CloudflareGuzzleAdapter;
use Cloudflare\API\Endpoints\Zones;
use NSWDPC\Utilities\Cloudflare\EntireCachePurgeJob;
use SilverStripe\Assets\File;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Path;
use SilverStripe\Security\Security;
use SilverStripe\Security\Permission;

use Symbiote\Cloudflare\Cloudflare;// @deprecate
use Symbiote\Cloudflare\CloudflareResult;// @deprecate

use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Extends Cloudflare to provide:
 *
 * + Purging by tag, host, prefix (Enterprise)
 * + Purging URLs associated with non SiteTree records (using DataObjectPurgeable)
 * + Usage of the Cloudflare SDK
 *
 * This class overrides methods in {@link Symbiote\Cloudflare\Cloudflare}
 *
 * @author James
 */
class CloudflarePurgeService extends Cloudflare {

    /**
     * @var int
     * Delay purge all by this many hours (allows undo)
     */
    private static $purge_all_delay = 1;

    /**
     * @var \Cloudflare\API\Adapter\Guzzle
     */
    protected $sdk_client = null;

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
     * @var string
     */
    const TYPE_FILE_EXTENSION = 'FileExtension';

    /**
     * @var string
     */
    const TYPE_IMAGE = 'Image';

    /**
     * @var string
     */
    const TYPE_CSS_JAVASCRIPT = 'CSSJavascript';

    /**
     * @var int
     */
    const URL_LIMIT_PER_REQUEST = 30;

    /**
     * @inheritdoc
     */
    public function __construct()
    {
        parent::__construct();
        /**
         * Avoid using parent client class
         * See: self::getSdkClient()
         */
        $this->client = null;// avoid using parent client
    }

    /**
     * @return CloudflareResult|null
     */
    protected function result($body, bool $response, array $values = []) {
        $errors = isset($body->errors) && is_array($body->errors) ? $body->errors : [];
        $result = new CloudflareResult(
            $values,// what was passed in
            $errors// error records
        );
        return $result;
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
     * Get the auth handler based on configuration (APIToken or APIKey)
     * @see https://developers.cloudflare.com/api/tokens/
     * @return Auth|null
     */
    public function getAuthHandler() : ?Auth {
        $authToken = $this->config()->get('auth_token');// recommended
        $authKey = $this->config()->get('auth_key');// legacy behaviour
        $auth = null;
        if($authToken) {
            $auth = new APIToken($authToken);
        } else if($authKey) {
            Logger::log("Cloudflare purge handling should be configured with an auth_token", "NOTICE");
            $auth = new APIKey(
                $this->config()->get('email'),
                $authKey
            );
        }
        return $auth;
    }

    /**
     * Retrieve a cloudflare/sdk client
     * @return CloudflareGuzzleAdapter|null
     */
    public function getSdkClient() : ?CloudflareGuzzleAdapter {
        if(!self::config()->get('enabled')) {
            return null;
        }
        if($this->sdk_client) {
            return $this->sdk_client;
        }
        if($auth = $this->getAuthHandler()) {
            $this->sdk_client = new CloudflareGuzzleAdapter($auth);
        }
        return $this->sdk_client;
    }

    /**
     * Purge all from zone by creating a cache purge job in the future (which handles the purging)
     * The idea here is that job will be created in the future with a configured delay (hrs)
     * This allows job cancellation and manual actioning
     * Only members with the permission ADMIN may create this job (in this method)
     * @return CloudflareResult|null
     * @deprecated will be removed in an upcoming release
     */
    public function purgeAll()
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
        // this response has no Zone or values passed in
        return $this->result(null, $result, []);
    }

    /**
     * Purge cache by tags immediately using cloudflare/sdk
     * @return CloudflareResult|false
     */
    public function purgeTags(array $tags) {
        if(empty($tags)) {
            return false;
        }
        $adapter = $this->getSdkClient();
        if(!$adapter) {
            return false;
        }
        $zones = new Zones( $adapter );
        // Logger::log("Cloudflare: purging tags " . implode(",", $tags));
        $result = $zones->cachePurge(
            $this->getZoneIdentifier(),
            null, // files
            $tags, // tags
            null  //hosts
        );
        return $this->result($zones->getBody(), $result, $tags);
    }

    /**
     * Purge cache by hosts immediately using cloudflare/sdk
     * @return CloudflareResult|false
     */
    public function purgeHosts(array $hosts) {
        if(empty($hosts)) {
            return false;
        }
        $adapter = $this->getSdkClient();
        if(!$adapter) {
            return false;
        }
        $zones = new Zones( $adapter );
        // Logger::log("Cloudflare: purging hosts " . implode(",", $hosts));
        $result = $zones->cachePurge(
            $this->getZoneIdentifier(),
            null, // files
            null, // tags
            $hosts  //hosts
        );
        return $this->result($zones->getBody(), $result, $hosts);
    }

    /**
     * Purge cache by urls immediately using cloudflare/sdk
     * This method modifies the URLs provided to ensure they are absolute URLs
     * @return CloudflareResult|false
     */
    public function purgeURLs(array $urls) {

        if(empty($urls)) {
            return false;
        }

        $adapter = $this->getSdkClient();
        if(!$adapter) {
            return false;
        }

        // Remove any reading mode added to the URL in query string
        static::removeReadingMode($urls);

        // ensure URLs are absolute
        array_walk(
            $urls,
            function(&$value, $key) {
                $value = Director::absoluteURL($value);
            }
        );

        $zones = new Zones( $adapter );

        // Logger::log("Cloudflare: zones->cachePurge() with " . count($urls) . " URLs");
        $result = $zones->cachePurge(
            $this->getZoneIdentifier(),
            $urls, // files
            null, // tags
            null  //hosts
        );
        // @link {Cloudflare\API\Traits\BodyAccessorTrait}
        return $this->result($zones->getBody(), $result, $urls);
    }

    /**
     * Have to do this directly via the Adapter for the moment
     * @return CloudflareResult|false
     */
    public function purgePrefixes(array $prefixes) {
        try {

            if(empty($prefixes)) {
                return false;
            }

            $adapter = $this->getSdkClient();
            if(!$adapter) {
                return false;
            }

            $options = [
                'prefixes' => $prefixes
            ];

            // Logger::log("Cloudflare: purging prefixes " . implode(",", $prefixes));
            $user = $adapter->post('zones/' . $this->getZoneIdentifier() . '/purge_cache', $options);
            $body = json_decode($user->getBody());
            $result = isset($body->result->id);
            return $this->result($body, $result, $prefixes);
        } catch (\Exception $e) {
            // TODO log
        }
        return false;
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
            self::TYPE_URL => 'files',
            self::TYPE_IMAGE => 'files',
            self::TYPE_CSS_JAVASCRIPT => 'files',
            self::TYPE_FILE_EXTENSION => 'files'
        ];
    }

    /**
     * Given a page, purge its absolute links
     * @param SiteTree $page page record
     * @return CloudflareResult|false
     */
    public function purgePage(SiteTree $page) {
        $urls = [];
        $urls[] = $page->AbsoluteLink();
        return $this->purgeURLs($urls);
    }

    /**
     * Return the absolute path to the public resources dir
     * e.g /var/www/example.com/public/_resources/
     * @return string
     */
    protected function getPublicResourcesDir() : string {
        return PUBLIC_PATH . "/" . RESOURCES_DIR;
    }

    /**
     * Return a list of all files in vendor exposed directories with matching extensions
     * along with all published File records ending with extension
     * @return array
     */
    public function getPublicFilesByExtension(array $extensions) : array {


        $publicLinks = [];
        if($publicResourcesDir = $this->getPublicResourcesDir()) {
            $directory = new \RecursiveDirectoryIterator(
                $publicResourcesDir,// base of _resources dir
                \FilesystemIterator::FOLLOW_SYMLINKS
            );
            $iterator = new \RecursiveIteratorIterator($directory);

            // Escape extension
            $patternExtensions = $extensions;
            array_walk(
                $patternExtensions,
                function(&$value, $key) {
                    $value = preg_quote($value);
                }
            );

            $pattern = '/\.(' . implode("|", $patternExtensions) . ')$/i';
            $publicFiles = new \RegexIterator(
                $iterator,
                $pattern,
                \RecursiveRegexIterator::GET_MATCH
            );

            $publicFiles->rewind();
            while($publicFiles->valid()) {
                // prefix sub path with RESOURCES_DIR to ensure correct URL
                $publicLinks[] = Path::join( RESOURCES_DIR, $publicFiles->getSubPathName() );// relative file
                $publicFiles->next();
            }

        }

        // Look up matching assets in DB
        $prefixedExtensions = $extensions;
        array_walk(
            $prefixedExtensions,
            function(&$value, $key) {
                $value = "." . ltrim($value, ".");
            }

        );

        $files = File::get()->filter([
            'Name:EndsWith' => $prefixedExtensions
        ]);

        $result = array_merge(
            $publicLinks,
            $files->map('ID','Link')->toArray()
        );

        return $result;
    }

    /**
     * Shorthand for purge files by extension
     * The CF API sets a 30 URL limit per purge request
     * This is fundementally the same as calling purgeURLs with 30 URLs
     * @return CloudflareResult|null
     * @deprecated will be removed in an upcoming release
     */
    public function purgeImages() {
        $categories = File::config()->get('app_categories');
        $extensions = [];
        if( isset( $categories['image'] ) && is_array( $categories['image'] ) ) {
            $extensions = $categories['image'];
        }
        return $this->purgeFilesByExtensions($extensions);
    }

    /**
     * Purge files by extensions
     * @param array $extensions
     * @return CloudflareResult|null
     * @deprecated will be removed in an upcoming release
     */
    protected function purgeFilesByExtensions(array $extensions)
    {
        if(count($extensions) == 0) {
            return null;
        }
        $files = $this->getPublicFilesByExtension($extensions, false);
        if(count($files) == 0) {
            return null;
        }
        $chunks = array_chunk($files, self::URL_LIMIT_PER_REQUEST);
        $errors = [];
        $result = null;
        foreach($chunks as $chunk) {
            // @var CloudflareResult
            $result = $this->purgeURLs($chunk);
        }
        // returns last result (in the case of multiple chunks)
        return $result;
    }

    /**
     * Shorthand for purging css, js and json files
     * @return CloudflareResult|null
     * @deprecated will be removed in an upcoming release
     */
    public function purgeCSSAndJavascript()
    {
        return $this->purgeFilesByExtensions([
            'css',
            'js',
            'json',
        ]);
    }

    /**
     * Public method to access purging by extension
     * @param array $extensions
     * @return CloudflareResult|null
     */
    public function purgeByFileExtension(array $extensions) {
        return $this->purgeFilesByExtensions($extensions);
    }

}
