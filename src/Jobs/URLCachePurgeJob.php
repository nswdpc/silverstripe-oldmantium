<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;

/**
 * Job purges assocaited record URLs
 * @author James
 */
class URLCachePurgeJob extends AbstractRecordCachePurgeJob
{

    /**
     * @inheritdoc
     */
    public function getPurgeType() : string {
        return CloudflarePurgeService::TYPE_URL;
    }

    /**
     * @inheritdoc
     */
    public function getTitle() {
        return parent::getTitle() . " - " . _t(__CLASS__ . '.JOB_TITLE', 'CF purge URL(s)');
    }

    /**
     * Process the job
     */
    public function process() {
        try {
            return $this->checkPurgeResult( $this->getPurgeClient()->purgeURLs( $this->checkRecordForErrors() ) );
        } catch (\Exception $e) {
            $this->addMessage("Cloudflare: failed to purge files (urls) with error=" . $e->getMessage() . " of type " . get_class($e), "NOTICE");
            $this->isComplete = false;
        }
    }




}
