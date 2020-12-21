<?php

namespace NSWDPC\Utilities\Cloudflare;

use Cloudflare\API\Endpoints\Zones;

/**
 * Purge all records in zone
 * NOTE: this can have negative consequences for system load and availability on a high traffic website
 * @author James Ellis <james.ellis@dpc.nsw.gov.au>
 */
class EntireCachePurgeJob extends AbstractRecordCachePurgeJob
{

    public function __construct($params = null) {}

    public function getTitle() {
        return _t(__CLASS__ . '.JOB_TITLE', 'Purge all in zone (WARNING!)');
    }

    /**
     * Process the job via the cloudflare/sdk directly
     */
    public function process() {
        try {
            $client = $this->getPurgeClient();
            $adapter = $client->getSdkClient();
            $zones = new Zones( $adapter );
            $zone_id = $client->getZoneIdentifier();
            $msg = "Cloudflare: purging all from zone {$zone_id}";
            Logger::log($msg, "NOTICE");
            $this->addMessage($msg, "NOTICE");
            $result = $zones->cachePurgeEverything( $zone_id );
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
            Logger::log("EntireCachePurgeJob error - " . $e->getMessage(), "WARNING");
        }
        $this->isComplete = false;
    }

    /**
     * Do not do anything once this job is complete
     */
    public function afterComplete() {}

}
