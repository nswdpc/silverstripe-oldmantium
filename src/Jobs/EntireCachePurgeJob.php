<?php

namespace NSWDPC\Utilities\Cloudflare;

use Cloudflare\API\Auth\APIKey;
use Cloudflare\API\Adapter\Guzzle;
use Cloudflare\API\Endpoints\Zones;
use SilverStripe\Core\Injector\Injector;
use Symbiote\Cloudflare\Cloudflare;

/**
 * Purge all records in zone
 * NOTE: this can have negative consequences for system load and availability on a high traffic websites.
 * @author James Ellis <james.ellis@dpc.nsw.gov.au>
 */
class EntireCachePurgeJob extends AbstractRecordCachePurgeJob
{

    public function getTitle() {
        return _t(__CLASS__ . '.JOB_TITLE', 'Purge all in zone (WARNING!)');
    }

    /**
     * Process the job via the cloudflare/sdk directly
     */
    public function process() {
        try {
            $client = Injector::inst()->get(Cloudflare::CLOUDFLARE_CLASS);
            $key = new APIKey(
                $client->config()->get('email'),
                $client->config()->get('auth_key')
            );
            $adapter = new Guzzle($key);
            $zones = new Zones( $adapter );
            $zone_id = $client->getZoneIdentifier();
            Logger::log("Cloudflare: purging all from zone {$zone_id}");
            $result = $zones->cachePurge( $zone_id );
            if($result) {
                $this->addMessage("Purged all in zone {$zone_id}");
                $this->isComplete = true;
                return true;
            } else {
                // job fail
                throw new \Exception("Could not purge all in zone {$zone_id}");
            }
        } catch (\Exception $e) {
            // log an error
        }
        $this->isComplete = false;
    }

    /**
     * Do not do anything once this job is complete
     */
    public function afterComplete() {}

}
