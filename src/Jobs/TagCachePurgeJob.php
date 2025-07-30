<?php

namespace NSWDPC\Utilities\Cloudflare;

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
    public function getPurgeType(): string
    {
        return CloudflarePurgeService::TYPE_TAG;
    }

    /**
     * @inheritdoc
     */
    #[\Override]
    public function getTitle(): string
    {
        return parent::getTitle() . " - " . _t(self::class . '.JOB_TITLE', 'CF purge tag(s)');
    }

    /**
     * Process the job
     */
    public function process()
    {
        $this->checkPurgeResult($this->getPurgeClient()->purgeTags($this->checkRecordForErrors()));
    }

}
