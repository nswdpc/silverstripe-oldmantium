<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJob;
use DateTime;
use DateTimeZone;
use Exception;

/**
 * Abstract record cache purge job
 * @author James
 */
abstract class AbstractRecordCachePurgeJob extends AbstractQueuedJob implements QueuedJob
{

    /**
     * @inheritdoc
     */
    protected $totalSteps = 1;

    /**
     * @var string
     */
    const RECORD_NAME = 'PurgeRecord';

    /**
     * Return the purge type for this job
     */
    abstract public function getPurgeType() : string;

    /**
     * Job constructor
     */
    public function __construct($reason = null, DataObject $object = null)
    {
        if($reason) {
            $this->reason = $reason;
        }
        if($object) {
            if( !$object->hasExtension( DataObjectPurgeable::class ) ) {
                throw new \Exception("Record must have DataObjectPurgeable extension applied");
            }
            $this->setObject($object, self::RECORD_NAME);
        }
    }

    /**
     * Opportunity to add some logging here
     */
    public function addMessage($msg, $level = null) {
        return parent::addMessage($msg, $level);
    }

    public function getPurgeClient() {
        return Injector::inst()->get( CloudflarePurgeService::class );
    }

    public function getTitle() {
        $object = $this->getObject(self::RECORD_NAME);
        if($object) {
            $type = $object->singular_name();
            $title = "{$type}#{$object->ID}/{$object->Title}";
            return $title;
        }
        return "";
    }

    /**
     * Checks the provided record for existence and whether it can return values for the required purge type
     * @return array the values that shalle be purged
     */
    final protected function checkRecordForErrors() : array {

        $record = $this->getObject(self::RECORD_NAME);
        if(!$record) {
            throw new \Exception("Record not found");
        }

        $type = $this->getPurgeType();
        if(!$type) {
            throw new \Exception("This job does not specify a purge type");
        }

        $purgeValues = $record->getPurgeValues();
        // The record must have a set of values key by Type to purge (can be empty)
        if( isset($purgeValues[ $type ]) && is_array($purgeValues[ $type ]) ) {

            if( count($purgeValues[ $type ]) > 0 ) {
                foreach($purgeValues[$type] as $value) {
                    $this->addMessage("Will purge '{$value}' of type '{$type}'...");
                }
                return $purgeValues[$type];
            }

        } else {
            throw new \Exception("Record missing '{$type}' values");
        }

        return [];
    }

    /**
     * Checks the result of the purge, if not an error the job is marked as complete
     * @throws \Exception
     */
    final protected function checkPurgeResult(?ApiResponse $response) {
        // Record errors
        $errors = $response->getErrors();
        if(!empty($errors)) {
            foreach($errors as $error) {
                $this->addMessage("Error: code={$error->code} message={$error->message}");
            }
            throw new \Exception("Response contained errors");
        }

        // Record successes
        $successes = $response->getSuccesses();
        if(!empty($successes)) {
            foreach($successes as $s => $file) {
                $this->addMessage('Success: ' . $file);
            }
        }

        $this->currentStep++;
        $this->isComplete = true;
    }

    /**
     * If the record has a cache max-age...
     * Purge on that schedule by creating a job of the same type set to run then
     */
    public function afterComplete()
    {
        $record = $this->getObject(self::RECORD_NAME);
        if(!$record) {
            // record no longer exists
            $this->addMessage("Cloudflare: record in job no longer exists or could not be found");
            return;
        }
        if($record->CacheMaxAge && $record->CacheMaxAge > 0) {
            $next = new DateTime();
            $next->modify('+' . $record->CacheMaxAge . ' minutes');
            $next_formatted = $next->format('Y-m-d H:i:s');
            $job = Injector::inst()->createWithArgs( get_class($this),  [ $this->reason, $record ] );
            $this->addMessage("Cloudflare: requeuing job for {$next_formatted}");
            QueuedJobService::singleton()->queueJob($job, $next_formatted);
        } else {
            $this->addMessage("Cloudflare: record has no cache max-age, not recreating");
        }
    }
}
