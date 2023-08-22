<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;

/**
 * Purge cache by prefix or prefixes
 * Note: requires a CF Enterprise account
 * @author James
 */
class PrefixCachePurgeJob extends AbstractRecordCachePurgeJob
{

    /**
     * @inheritdoc
     */
    public function getPurgeType() : string {
        return CloudflarePurgeService::TYPE_PREFIX;
    }

    /**
     * @inheritdoc
     */
    public function getTitle() {
        return parent::getTitle() . " - " . _t(__CLASS__ . '.JOB_TITLE', 'CF purge prefix(es)');
    }

    /**
     * Process the job
     */
    public function process() {
        $this->checkPurgeResult( $this->getPurgeClient()->purgePrefixes( $this->checkRecordForErrors() ) );
    }

}
