<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;
use Symbiote\Cloudflare\Cloudflare;

/**
 * Job purges assocaited record URLs
 * @todo delete other jobs for this record (e.g 1-1 record-active job relation)
 * @author James Ellis <james.ellis@dpc.nsw.gov.au>
 */
class URLCachePurgeJob extends AbstractRecordCachePurgeJob
{

    public function getTitle() {
        return _t(__CLASS__ . '.JOB_TITLE', 'Record Cloudflare cache purge job');
    }

    /**
     * Process the job
     */
    public function process() {
        $record = $this->getObject('Record');
        $result = false;
        if($record && $record instanceof Purgeable) {
            $urls = $record->getPurgeUrls();
            if(!empty($urls['files'])) {
                $result = Injector::inst()->get(Cloudflare::class)->purgeFiles($urls['files']);
            }
        }
        return $result;
    }


}
