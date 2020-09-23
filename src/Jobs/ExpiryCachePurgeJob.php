<?php

namespace NSWDPC\Utilities\Cloudflare;

/**
 * When a given record is deemed 'expired', purge any urls associated with it
 * This is a one-off job run for a given expiry datetime
 * For instance a record has an EndDatetime at a certain datetime, related URLs need to be purged then
 * @author James Ellis <james.ellis@dpc.nsw.gov.au>
 */
class ExpiryCachePurgeJob extends URLCachePurgeJob
{

    public function getTitle() {
        return _t(__CLASS__ . '.JOB_TITLE', 'Purge Cloudflare cache on expiry job');
    }

    /**
     * Process the job
     * @todo enforce only run after the 'expiry' date of a record has passed
     */
    public function process() {
        return parent::process();
    }

    /**
     * Do not do anything once the job is complete
     */
    public function afterComplete() {}

}
