<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;
use Symbiote\Cloudflare\Cloudflare;

/**
 * Purge cache by prefix or prefixes
 * @author James Ellis <james.ellis@dpc.nsw.gov.au>
 */
class PrefixCachePurgeJob extends AbstractRecordCachePurgeJob
{

    public function getTitle() {
        return _t(__CLASS__ . '.JOB_TITLE', 'Cloudflare purge cache by prefix(es)');
    }
    /**
     * Process the job
     */
    public function process() {
        $record = $this->getObject('Record');
        $result = false;
        if($record && $record instanceof Purgeable) {
            $urls = $record->getPurgeValues();
            if(!empty($urls['prefixes'])) {
                $result = Injector::inst()->get(Cloudflare::CLOUDFLARE_CLASS)->purgePrefixes($urls['prefixes']);
            }
        }
        return $result;
    }

}
