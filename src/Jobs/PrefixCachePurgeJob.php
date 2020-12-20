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
            $this->checkPurgeResult( $this->getPurgeClient()->purgePrefixes($values['prefixes']) );
        } catch (\Exception $e) {
            $this->addMessage("Cloudflare: failed to purge prefixes with error=" . $e->getMessage() . " of type " . get_class($e));
            $this->isComplete = false;
        }
        return false;
    }

}
