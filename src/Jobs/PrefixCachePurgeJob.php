<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;
use Symbiote\Cloudflare\Cloudflare;

/**
 * Purge cache by prefix or prefixes
 * Note: requires a CF Enterprise account
 * @author James Ellis <james.ellis@dpc.nsw.gov.au>
 */
class PrefixCachePurgeJob extends AbstractRecordCachePurgeJob
{

    public function getTitle() {
        return parent::getTitle() . " - " . _t(__CLASS__ . '.JOB_TITLE', 'Cloudflare purge cache by prefix(es)');
    }
    /**
     * Process the job
     */
    public function process() {
        try {
            $values = $this->checkRecordForErrors('prefixes');
            $result = Injector::inst()->get(Cloudflare::CLOUDFLARE_CLASS)->purgePrefixes($values['prefixes']);
            $this->checkPurgeResult();
        } catch (\Exception $e) {
            Logger::log("Cloudflare: failed to purge prefixes with error=" . $e->getMessage());
            $this->isComplete = false;
        }
        return false;
    }

}
