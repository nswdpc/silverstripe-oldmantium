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

    protected $totalSteps = 1;

    const RECORD_NAME = 'PurgeRecord';

    public function __construct($reason = null, DataObject $object = null)
    {
        if($reason) {
            $this->reason = $reason;
        }
        if($object) {
            $this->setObject($object, self::RECORD_NAME);
        }

        $this->totalSteps = 1;
        $this->currentStep = 0;
    }

    public function getTitle() {
        $object = $this->getObject(self::RECORD_NAME);
        if($object) {
            $title = $object->singular_name() ?: get_class($object);
            return "Record: {$title}";
        }
        return "";
    }

    /**
     * Checks the provided record for existence and whether it can return values for the required purge type
     * @return array the values that shalle be purged
     * @param string $type the purge type e.g 'hosts'
     */
    final protected function checkRecordForErrors($type) {
        $record = $this->getObject(self::RECORD_NAME);
        if(!$record) {
            throw new \Exception("Record not found");
        }
        if(!$record instanceof Purgeable) {
            throw new \Exception("Record is not implementing Purgeable");
        }
        $values = $record->getPurgeValues();
        if(empty($values[ $type ])) {
            throw new \Exception("Record has no '{$type}' values to purge");
        }
        return $values;
    }

    final protected function checkPurgeResult($result) {
        if(!$result || !$result instanceof CloudflareResult) {
            throw new \Exception("Result is not a CloudflareResult instance");
        }
        $errors = $result->getErrors();
        if(empty($errors)) {
            Logger::log("Job completed without errors", "INFO");
            $this->currentStep++;
            $this->isComplete = true;
            return true;
        } else {
            throw new \Exception("Purge had errors:" . json_encode($errors));
        }
    }

    /**
     * If the record has a cache max-age...
     * Purge on that schedule by creating a job of the same type set to run then
     */
    public function afterComplete()
    {
        Logger::log("Job -> afterComplete", "INFO");
        $record = $this->getObject(self::RECORD_NAME);
        if(!$record) {
            // record no longer exists
            Logger::log("Cloudflare: record in job no longer exists or could not be found");
            return;
        }
        if($record->CacheMaxAge && $record->CacheMaxAge > 0) {
            $next = new DateTime();
            $next->modify('+' . $record->CacheMaxAge . ' seconds');
            $next_formatted = $next->format('Y-m-d H:i:s');
            $job = Injector::inst()->createWithArgs( get_class($this),  [ $this->reason, $record ] );
            Logger::log("Cloudflare: requeuing job for {$next_formatted}");
            QueuedJobService::singleton()->queueJob($job, $next_formatted);
        } else {
            Logger::log("Cloudflare: record has no cache max-age, not recreating");
        }
    }
}
