<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;
use Symbiote\Cloudflare\Cloudflare;

/**
 * Purge cache by tag or tags
 * @author James Ellis <james.ellis@dpc.nsw.gov.au>
 */
class TagCachePurgeJob extends AbstractRecordCachePurgeJob
{

    public function getTitle() {
        return _t(__CLASS__ . '.JOB_TITLE', 'Cloudflare purge cache by tag(s)');
    }
    /**
     * Process the job
     */
    public function process() {
        $record = $this->getObject('Record');
        $result = false;
        if($record && $record instanceof Purgeable) {
            $urls = $record->getPurgeValues();
            if(!empty($urls['tags'])) {
                $result = Injector::inst()->get(Cloudflare::CLOUDFLARE_CLASS)->purgeTags($urls['tags']);
            }
        }
        return $result;
    }

}
