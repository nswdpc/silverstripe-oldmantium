<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;
use Symbiote\Cloudflare\Cloudflare;

/**
 * Purge cache by tag or tags
 * Note: requires a CF Enterprise account
 * @author James Ellis <james.ellis@dpc.nsw.gov.au>
 */
class TagCachePurgeJob extends AbstractRecordCachePurgeJob
{

    public function getTitle() {
        return parent::getTitle() . " - " . _t(__CLASS__ . '.JOB_TITLE', 'CF purge tag(s)');
    }
    /**
     * Process the job
     */
    public function process() {
        try {
            $values = $this->checkRecordForErrors('tags');
            $this->checkPurgeResult( $this->getPurgeClient()->purgeTags($values['tags']) );
        } catch (\Exception $e) {
            $this->addMessage("Cloudflare: failed to purge tags with error=" . $e->getMessage() . " of type " . get_class($e));
            $this->isComplete = false;
        }
        return false;
    }

}
