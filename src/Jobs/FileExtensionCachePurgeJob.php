<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;
use Symbiote\Cloudflare\Cloudflare;

/**
 * Job purges URLs linked to the provided file extensions
 * Triggered by publishing/unpublishing a PurgeRecord of type 'FileExtension'
 * @author James
 * @deprecated will be removed in an upcoming release
 */
class FileExtensionCachePurgeJob extends AbstractRecordCachePurgeJob
{

    /**
     * @inheritdoc
     */
    public function getPurgeType() : string {
        return CloudflarePurgeService::TYPE_FILE_EXTENSION;
    }

    /**
     * @inheritdoc
     */
    public function getTitle() {
        return parent::getTitle() . " - " . _t(__CLASS__ . '.JOB_TITLE', 'CF purge URL(s) by file extension');
    }

    /**
     * Process the job
     */
    public function process() {
        try {
            return $this->checkPurgeResult( $this->getPurgeClient()->purgeByFileExtension( $this->checkRecordForErrors() ) );
        } catch (\Exception $e) {
            $this->addMessage("Cloudflare: failed to purge files (urls) with error=" . $e->getMessage() . " of type " . get_class($e), "NOTICE");
            $this->isComplete = false;
        }
    }




}
