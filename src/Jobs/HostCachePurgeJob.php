<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;
use Symbiote\Cloudflare\Cloudflare;

/**
 * Purge cache by host or hosts
 * @author James Ellis <james.ellis@dpc.nsw.gov.au>
 */
class HostCachePurgeJob extends AbstractRecordCachePurgeJob
{

    public function getTitle() {
        return _t(__CLASS__ . '.JOB_TITLE', 'Cloudflare purge cache by host(s)');
    }
    /**
     * Process the job
     */
    public function process() {
        $record = $this->getObject('Record');
        $result = false;
        if($record && $record instanceof Purgeable) {
            $urls = $record->getPurgeValues();
            if(!empty($urls['hosts'])) {
                $result = Injector::inst()->get(Cloudflare::CLOUDFLARE_CLASS)->purgeHosts($urls['hosts']);
            }
        }
        return $result;
    }

}
