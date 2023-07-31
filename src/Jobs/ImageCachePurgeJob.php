<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;
use Symbiote\Cloudflare\Cloudflare;

/**
 * Shorthand job purges URLs linked to common image extensions
 * Triggered by publishing/unpublishing a PurgeRecord of type 'Image'
 * @author James
 * @deprecated will be removed in an upcoming release
 */
class ImageCachePurgeJob extends AbstractRecordCachePurgeJob
{

    /**
     * @inheritdoc
     */
    public function getPurgeType() : string {
        return CloudflarePurgeService::TYPE_IMAGE;
    }

    /**
     * @inheritdoc
     */
    public function getTitle() {
        return parent::getTitle() . " - " . _t(__CLASS__ . '.JOB_TITLE', 'CF purge URL(s) for common image formats');
    }

    /**
     * Process the job
     */
    public function process() {
        try {
            return $this->checkPurgeResult( $this->getPurgeClient()->purgeImages( ) );
        } catch (\Exception $e) {
            $this->addMessage("Cloudflare: failed to purge files (urls) with error=" . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine(), "NOTICE");
            $this->isComplete = false;
        }
    }




}
