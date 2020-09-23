<?php

namespace NSWDPC\Utilities\Cloudflare;

use Cloudflare\API\Auth\APIKey;
use Cloudflare\API\Adapter\Guzzle;
use Cloudflare\API\Endpoints\Zones;
use NSWDPC\Utilities\Cloudflare\EntireCachePurgeJob;
use Symbiote\Cloudflare\Cloudflare;
use Symbiote\Cloudflare\CloudflareResult;

/**
 * Extends Cloudflare to provide enterprise level cache purging support
 * @author James
 */
class CloudflarePurgeService extends Cloudflare {

    private static $purge_all_delay = 6;//hrs

    const TYPE_HOST = 'Host';
    const TYPE_TAG = 'Tag';
    const TYPE_PREFIX = 'Prefix';
    const TYPE_URL = 'URL';

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
     */
    public function getSdkClient() {
        $key = new APIKey(
            $this->config()->get('email'),
            $this->config()->get('auth_key')
        );
        $adapter = new Guzzle($key);
        return $adapter;
    }

    /**
     * Purge all from zone by creating a cache purge job in the future (which handles the purging)
     * The idea here is that job will be created in the future with a configured delay (hrs)
     * @return CloudflareResult|null
     */
    public function purgeAll()
    {
        $job = new EntireCachePurgeJob();
        $start = new \DateTime();
        $delay = $this->config()->get('purge_all_delay');
        if($delay > 0) {
            $start->modify("+1 {$delay} hours");
        }
        $result = false;
        Logger::log("Cloudflare: purging all (via job)");
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
        Logger::log("Cloudflare: purging tags " . implode(",", $tags));
        $result = $zones->cachePurge(
            $this->getZoneIdentifier(),
            null, // hosts
            $tags,
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
        Logger::log("Cloudflare: purging hosts " . implode(",", $hosts));
        $result = $zones->cachePurge(
            $this->getZoneIdentifier(),
            null, // files
            null, // tags
            $hosts  //hosts
        );
        return $this->result($zones->getBody(), $result, $hosts);
    }

    /**
     * Purge cache by hosts immediately using cloudflare/sdk
     * @return CloudflareResult|false
     */
    public function purgeFiles(array $files) {
        if(empty($files)) {
            return false;
        }
        $zones = new Zones( $this->getSdkClient() );
        Logger::log("Cloudflare: purging files " . implode(",", $files));
        $result = $zones->cachePurge(
            $this->getZoneIdentifier(),
            $files, // files
            null, // tags
            null  //hosts
        );
        return $this->result($zones->getBody(), $result, $hosts);
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
            Logger::log("Cloudflare: purging prefixes " . implode(",", $prefixes));
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
