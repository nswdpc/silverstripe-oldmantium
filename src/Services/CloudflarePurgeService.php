<?php

namespace NSWDPC\Utilities\Cloudflare;

use Cloudflare\API\Auth\APIKey;
use Cloudflare\API\Adapter\Guzzle;
use Cloudflare\API\Endpoints\Zones;
use NSWDPC\Utilities\Cloudflare\EntireCachePurgeJob;
use SilverStripe\Control\Director;
use SilverStripe\Control\Controller;
use SilverStripe\Security\Security;
use SilverStripe\Security\Permission;
use Symbiote\Cloudflare\Cloudflare;
use Symbiote\Cloudflare\CloudflareResult;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Extends Cloudflare to provide:
 * + Purging by tag, host, prefix (Enterprise)
 * + Purging URLs associated with non SiteTree records (using DataObjectPurgeable)
 * + Usage of the Cloudflare SDK
 *
 * This class overrides the following methids in {@link Symbiote\Cloudflare\Cloudflare}
 * + purgeAll()
 * + purgeURLs()
 *
 *
 * Certain methods are handled by {@link Symbiote\Cloudflare\Cloudflare}:
 * + purgePage()
 * + purgeImages()
 * + purgeCSSAndJavascript()
 * + purgeFilesByExtensions()
 * + purgeFiles()
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
     * @var Cloudflare\API\Adapter\Guzzle
     */
    private $sdk_client;

    const TYPE_HOST = 'Host';
    const TYPE_TAG = 'Tag';
    const TYPE_PREFIX = 'Prefix';
    const TYPE_URL = 'URL';
    const TYPE_ENTIRE = 'Entire';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @return CloudflareResult|null
     */
    protected function result($body = null, bool $response, array $values = []) {
        $errors = isset($body->errors) && is_array($body->errors) ? $body->errors : [];
        $result = new CloudflareResult(
            $values,// what was passed in
            $errors// error records
        );
        return $result;
    }

    /**
     * Retrieve a cloudflare/sdk client
     * @return Cloudflare\API\Adapter\Guzzle
     */
    protected function getSdkClient() {
        if($this->sdk_client) {
            return $this->sdk_client;
        }
        $auth = new APIKey(
            $this->config()->get('email'),
            $this->config()->get('auth_key')
        );
        $this->sdk_client = new Guzzle($auth);
        return $this->sdk_client;
    }

    /**
     * Purge all from zone by creating a cache purge job in the future (which handles the purging)
     * The idea here is that job will be created in the future with a configured delay (hrs)
     * This allows job cancellation and manual actioning
     * Only members with the permission ADMIN may create this job (in this method)
     * @return CloudflareResult|null
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
            $start->modify("+1 {$delay} hours");
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
        $zones = new Zones( $this->getSdkClient() );
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
        $zones = new Zones( $this->getSdkClient() );
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

        $purge_urls = [];
        $base_url = $this->config()->get('base_url');
        if (!$base_url) {
            $base_url = Director::absoluteBaseURL();
        }

        foreach($urls as $url) {
            $purge_urls[] = Director::absoluteURL($url);
            // Logger::log("Cloudflare: purging {$url}");
        }

        $zones = new Zones( $this->getSdkClient() );

        // Logger::log("Cloudflare: zones->cachePurge() with " . count($purge_urls) . " URLs");
        $result = $zones->cachePurge(
            $this->getZoneIdentifier(),
            $purge_urls, // files
            null, // tags
            null  //hosts
        );
        // @link {Cloudflare\API\Traits\BodyAccessorTrait}
        return $this->result($zones->getBody(), $result, $purge_urls);
    }

    /**
     * Have to do this directly via the Adapter for the moment
     * @return CloudflareResult|false
     */
    public function purgePrefixes(array $prefixes) {
        if(empty($prefixes)) {
            return false;
        }
        try {
            $adapter = $this->getSdkClient();
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
     * Get the option for the type
     */
    public static function getOptionForType($type) {
        $mappings = self::getTypeMappings();
        $key = array_search ( $type , $mappings );
        return $key;
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
        ];
    }
}
