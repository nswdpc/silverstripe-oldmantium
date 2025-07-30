<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;

/**
 * Purge cache by host or hosts
 * Note: requires a CF Enterprise account
 * @author James
 */
class HostCachePurgeJob extends AbstractRecordCachePurgeJob
{

    /**
     * @inheritdoc
     */
    public function getPurgeType() : string {
        return CloudflarePurgeService::TYPE_HOST;
    }

    /**
     * @inheritdoc
     */
    #[\Override]
    public function getTitle(): string {
        return parent::getTitle() . " - " . _t(self::class . '.JOB_TITLE', 'CF purge host(s)');
    }

    /**
     * Process the job
     */
    public function process() {
        $this->checkPurgeResult( $this->getPurgeClient()->purgeHosts( $this->checkRecordForErrors() ) );
    }

}
