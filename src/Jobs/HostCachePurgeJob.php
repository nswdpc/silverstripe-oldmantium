<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;
use Symbiote\Cloudflare\Cloudflare;
use Symbiote\Cloudflare\CloudflareResult;

/**
 * Purge cache by host or hosts
 * Note: requires a CF Enterprise account
 * @author James Ellis <james.ellis@dpc.nsw.gov.au>
 */
class HostCachePurgeJob extends AbstractRecordCachePurgeJob
{

    public function getTitle() {
        return parent::getTitle() . " - " . _t(__CLASS__ . '.JOB_TITLE', 'CF purge host(s)');
    }
    /**
     * Process the job
     */
    public function process() {
        try {
            $values = $this->checkRecordForErrors('hosts');
            $this->checkPurgeResult( $this->getPurgeClient()->purgeHosts($values['hosts']) );
        } catch (\Exception $e) {
            $this->addMessage("Cloudflare: failed to purge hosts with error=" . $e->getMessage() . " of type " . get_class($e));
            $this->isComplete = false;
        }
        return false;
    }

}
