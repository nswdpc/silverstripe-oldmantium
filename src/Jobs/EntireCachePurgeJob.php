<?php

namespace NSWDPC\Utilities\Cloudflare;

use Cloudflare\API\Endpoints\Zones;

/**
 * Purge all records in zone
 * NOTE: this can have negative consequences for system load and availability on a high traffic website
 * @author James
 * @deprecated will be removed in an upcoming release
 */
class EntireCachePurgeJob extends AbstractRecordCachePurgeJob
{

    /**
     * @inheritdoc
     */
    public function __construct($params = null) {}

    /**
     * @inheritdoc
     */
    public function getPurgeType() : string {
        return CloudflarePurgeService::TYPE_ENTIRE;
    }

    /**
     * @inheritdoc
     */
    public function getTitle() {
        return _t(__CLASS__ . '.JOB_TITLE', 'CF Purge all in zone (WARNING!)');
    }

    /**
     * @inheritdoc
     */
    public function process() {
        $service = $this->getPurgeClient();
        $client = $service->getApiClient();
        if(!$client) {
            throw new \Exception("API client not available. Is enabled=true?");
        }
        $zoneId = $service->getZoneIdentifier();
        if(!$zoneId) {
            throw new \Exception("No zone_id found in configuration");
        }
        $this->addMessage("Cloudflare: purging all from zone");
        $response = $client->purgeEverything( $zoneId );
        if($response->allSuccess()) {
            $successes = $response->getSuccesses();
            $this->addMessage("Purged all in zone: " . json_encode($successes));
        } else {
            // job fail
            $errors = $response->getErrors();
            throw new \Exception("Could not purge all in zone. Errors=" . json_encode($errors));
        }
        $this->isComplete = true;
    }

    /**
     * Do not do anything once this job is complete
     */
    public function afterComplete() {}

}
