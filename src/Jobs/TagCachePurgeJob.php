<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;

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
        $this->checkPurgeResult( $this->getPurgeClient()->purgeTags( $this->checkRecordForErrors() ) );
    }

}
