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
        return parent::getTitle() . " - " . _t(__CLASS__ . '.JOB_TITLE', 'Cloudflare purge cache by host(s)');
    }
    /**
     * Process the job
     */
    public function process() {
        try {
            $values = $this->checkRecordForErrors('hosts');
            $result = Injector::inst()->get(Cloudflare::CLOUDFLARE_CLASS)->purgeHosts($values['hosts']);
            $this->checkPurgeResult();
        } catch (\Exception $e) {
            Logger::log("Cloudflare: failed to purge hosts with error=" . $e->getMessage());
            $this->isComplete = false;
        }
        return false;
    }

}
