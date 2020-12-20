<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;
use Symbiote\Cloudflare\Cloudflare;

/**
 * Job purges assocaited record URLs
 * @author James Ellis <james.ellis@dpc.nsw.gov.au>
 */
class URLCachePurgeJob extends AbstractRecordCachePurgeJob
{

    public function getTitle() {
        return parent::getTitle() . " - " . _t(__CLASS__ . '.JOB_TITLE', 'Cloudflare purge cache by url(s)');
    }

    /**
     * Process the job
     */
    public function process() {
        try {
            $values = $this->checkRecordForErrors('files');
            return $this->checkPurgeResult( $this->getPurgeClient()->purgeURLs($values['files']) );
        } catch (\Exception $e) {
            $this->addMessage("Cloudflare: failed to purge files (urls) with error=" . $e->getMessage() . " of type " . get_class($e), "NOTICE");
            $this->isComplete = false;
        }
    }




}
