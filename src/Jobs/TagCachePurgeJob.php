<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;
use Symbiote\Cloudflare\Cloudflare;

/**
 * Purge cache by tag or tags
 * Note: requires a CF Enterprise account
 * @author James
 */
class TagCachePurgeJob extends AbstractRecordCachePurgeJob
{

    /**
     * @inheritdoc
     */
    public function getPurgeType() : string {
        return CloudflarePurgeService::TYPE_TAG;
    }

    /**
     * @inheritdoc
     */
    public function getTitle() {
        return parent::getTitle() . " - " . _t(__CLASS__ . '.JOB_TITLE', 'CF purge tag(s)');
    }

    /**
     * Process the job
     */
    public function process() {
        try {
            $this->checkPurgeResult( $this->getPurgeClient()->purgeTags( $this->checkRecordForErrors() ) );
        } catch (\Exception $e) {
            $this->addMessage("Cloudflare: failed to purge tags with error=" . $e->getMessage() . " of type " . get_class($e));
            $this->isComplete = false;
        }
        return false;
    }

}
