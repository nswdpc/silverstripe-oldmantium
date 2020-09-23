<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use Symbiote\Cloudflare\CloudflareResult;
use Symbiote\Cloudflare\Cloudflare;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use DateTime;
use DateTimeZone;
use Exception;

/**
 * Abstract record cache purge job
 * @author James Ellis <james.ellis@dpc.nsw.gov.au>
 */
abstract class AbstractRecordCachePurgeJob extends AbstractQueuedJob
{
    protected $reason = '';

    const RECORD_NAME = 'PurgeRecord';

    public function __construct($reason)
    {
        $this->reason = $reason;
    }

    public function setReason() {
        $this->reason = $reason;
    }

    /**
     * Ensure naming is locked down
     */
    protected function setObject(DataObject $object, $name = 'PurgeRecord')
    {
        return parent::setObject($object, self::RECORD_NAME);
    }

    /**
     * If the record has a cache max-age...
     * Purge on that schedule by creating a job of the same type set to run then
     */
    public function afterComplete()
    {
        $record = $this->getObject(self::RECORD_NAME);
        if($record  && $record->CacheMaxAge && $record->CacheMaxAge > 0) {
            $next = new DateTime();
            $next->modify('+' . $record->CacheMaxAge . ' seconds');
            $job = new self($this->reason);
            $job->setRecord($record);
            QueuedJobService::singleton()->queueJob(
                $job,
                $next->format('Y-m-d H:i:s')
            );
        }
    }
}
