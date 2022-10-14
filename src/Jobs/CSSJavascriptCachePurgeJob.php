<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;
use Symbiote\Cloudflare\Cloudflare;

/**
 * Shorthand job for purging files by extensions returned from {@link CloudflarePurgeService::purgeCSSAndJavascript()}
 * Triggered by publishing/unpublishing a PurgeRecord of type 'CSSJavascript'
 * @author James
 */
class CSSJavascriptCachePurgeJob extends AbstractRecordCachePurgeJob
{

    /**
     * @inheritdoc
     */
    public function getPurgeType() : string {
        return CloudflarePurgeService::TYPE_CSS_JAVASCRIPT;
    }

    /**
     * @inheritdoc
     */
    public function getTitle() {
        return parent::getTitle() . " - " . _t(__CLASS__ . '.JOB_TITLE', 'CF purge URL(s) for css, json and js files');
    }

    /**
     * Process the job
     */
    public function process() {
        try {
            return $this->checkPurgeResult( $this->getPurgeClient()->purgeCSSAndJavascript( $this->checkRecordForErrors() ) );
        } catch (\Exception $e) {
            $this->addMessage("Cloudflare: failed to purge files (urls) with error=" . $e->getMessage() . " of type " . get_class($e), "NOTICE");
            $this->isComplete = false;
        }
    }




}
