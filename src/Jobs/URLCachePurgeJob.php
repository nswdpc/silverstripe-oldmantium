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
            Logger::log("Cloudflare: URL purging");
            $values = $this->checkRecordForErrors('files');
            Logger::log("Cloudflare: URL purging - checking");
            $this->checkPurgeResult(Injector::inst()->get(Cloudflare::CLOUDFLARE_CLASS)->purgeURLs($values['files']));
        } catch (\Exception $e) {
            Logger::log("Cloudflare: failed to purge files (urls) with error=" . $e->getMessage());
            $this->isComplete = false;
        }
        return false;
    }


}
