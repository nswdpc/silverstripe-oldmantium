<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\NumericField;
use Silverstripe\ORM\ArrayList;
use Silverstripe\ORM\DataExtension;
use SilverStripe\Versioned\Versioned;
use Symbiote\Cloudflare\CloudflareResult;
use Symbiote\Cloudflare\Cloudflare;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

/**
 * Extension that decorates a purgeable dataobject, currently support URLs only
 * @author james.ellis@dpc.nsw.gov.au
 */

class DataObjectPurgeable extends DataExtension implements CloudflarePurgeable {

    private static $cache_max_age = 0;

    const REASON_WRITE = 'write';
    const REASON_DELETE = 'delete';

    const REASON_PUBLISH = 'publish';
    const REASON_UNPUBLISH = 'unpublish';

    private static $db = [
        'CachePurgeAt' => 'Datetime', // add ability to purge dataobject at a certain date / time
        'CacheMaxAge' => 'Double'// minutes TTL
    ];

    /**
     * Prior to write, remove any pending jobs for this record
     * Allows the afterDelete to create the correct job(s)
     */
    public function onBeforeWrite()
    {
        if($this->owner->exists()) {
            $this->clearCurrentJobs();
        }
    }

    /**
     * Prior to delete, remove any pending jobs for this record
     * Allows the afterDelete to create the correct job(s)
     */
    public function onBeforeDelete()
    {
        if($this->owner->exists()) {
            $this->clearCurrentJobs();
        }
    }

    /**
     * After publish, create any purge jobs that should be fired for the 'publish' reason
     * For versioned records when they are published
     */
    public function onAfterPublish()
    {
        if ($this->owner->hasExtension(Versioned::class)) {
            // Logger::log("Cloudflare: creating jobs for reason=publish");
            $start = null;
            if($this->owner->CachePurgeAt) {
                $start = new \DataTime( $this->owner->CachePurgeAt );
            }
            $this->owner->createPurgeJobs('publish', $start);
        }
    }

    /**
     * After unpublish, create any purge jobs that should be fired for the 'unpublish' reason
     * For versioned records when the Live stage record is removed
     */
    public function onAfterUnpublish()
    {
        if ($this->owner->hasExtension(Versioned::class)) {
            // Logger::log("Cloudflare: creating jobs for reason=unpublish");
            $start = null;
            if($this->owner->CachePurgeAt) {
                $start = new \DataTime( $this->owner->CachePurgeAt );
            }
            $this->owner->createPurgeJobs('unpublish');
        }
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.Cloudflare', [
                DatetimeField::create(
                    'CachePurgeAt',
                    _t(__CLASS__ . '.CACHE_PURGE_AT', 'Set purge to occur from this date and time (Timezone: UTC)')
                ),
                NumericField::create(
                    'CacheMaxAge',
                    _t(__CLASS__ . '.CACHE_MAX_AGE', 'Cache maximum age (minutes)')
                )->setDescription(
                    _t(__CLASS__ . '.CACHE_MAX_AGE_DESCRIPTION', 'Record URL(s) will be purged at this interval in minutes')
                )->setRightTitle(
                    _t(__CLASS__ . '.CACHE_MAX_AGE_LEAVE_ZERO', 'Leave empty for no regular purge')
                )->setHTML5(true)
            ]
        );
    }

    /**
     * Only the record knows which types to return, but return URL type here as the default
     * Owner records will take precedence
     * @return array
     */
    public function getPurgeTypes() : array {
        return [
            CloudflarePurgeService::TYPE_URL
        ];
    }

    /**
     * The name of the record for usage in QueuedJobs
     * @throws \Exception
     */
    public function getPurgeRecordName() : string {
        return AbstractRecordCachePurgeJob::RECORD_NAME;
    }

    /**
     * Return an array of values that can be purged
     * @return array
     */
    final public function getPurgeValues() : array {

        // keys are the options that can be sent to purge_cache
        $result = [
            'files' => [],
            'tags' => [],
            'hosts' => [],
            'prefixes' => [],
        ];

        // mapping internal TYPE_* contants to purge_cache options
        $mappings = CloudflarePurgeService::getTypeMappings();
        // the types this record supports (could be only one !)
        $types = $this->owner->getPurgeTypes();
        if(empty($types)) {
            // no purge types means no values
            return [];
        }

        // Logger::log("Cloudflare: getPurgeValues types=" . json_encode($types) );

        foreach($types as $type) {
            // a $type is one of the TYPE_* constants
            if( isset( $mappings[ $type ] ) && array_key_exists($mappings[ $type ], $result) ) {
                // e.g result['tags'] = ['blog','support','security']
                // result is keyed by the allowed purge_cache options
                $result[ $mappings[ $type ] ] = $this->owner->getPurgeTypeValues( $type );
                Logger::log("Cloudflare: getPurgeValues returning " .  count($result[ $mappings[ $type ] ]) . " records for type {$type}");
            }
        }

        return $result;
    }

    /**
     * Only the record knows which values to return for the given type
     * For the moment, return URLs that can be purged
     * @return array
     */
    public function getPurgeTypeValues($type) : array {
        $values = [];
        switch($type) {
            case CloudflarePurgeService::TYPE_URL:
                if ($this->owner->hasMethod('getPurgeUrlList')) {
                    // the record can specify its own list of URLs to purge
                    $values = $this->owner->getPurgeUrlList();
                } elseif ($this->owner->hasMethod('AbsoluteLink')) {
                    // otherwise use the URL of the record, provided by the record
                    $values[] = $this->owner->AbsoluteLink();
                }
                break;
        }
        return $values;
    }

    private function clearCurrentJobs() {
        $jobs = $this->owner->getCurrentPurgeJobDescriptors();
        foreach($jobs as $job) {
            $job->delete();
        }
    }

    /**
     * Return QueuedJobDescriptor records linked to the owner record
     */
    public function getCurrentPurgeJobDescriptors( array $implementations = []) : ArrayList {
        $list = ArrayList::create();
        // ignore these status
        $statii = [
            QueuedJob::STATUS_RUN,
            QueuedJob::STATUS_COMPLETE
        ];

        // jobs with this implementation
        if(empty($implementations)) {
            $implementations = ClassInfo::subclassesFor( AbstractRecordCachePurgeJob::class, false);
        }

        // still none !
        if(empty($implementations)) {
            return $list;
        }

        $jobs = QueuedJobDescriptor::get()
                    ->filter([
                        'Implementation' => $implementations
                    ])
                    ->exclude([
                        'JobStatus' => $statii
                    ]);

        $name = $this->owner->getPurgeRecordName();
        $record_id = "{$name}ID";
        $record_type = "{$name}Type";

        foreach($jobs as $job) {
            $data = @unserialize($job->SavedJobData);
            if(!empty($data->{$record_id})
                && !empty($data->{$record_type})
                && $data->{$record_id} == $this->owner->ID
                && $data->{$record_type} == get_class($this->owner)) {
                // matching job, push onto list
                $list->push($job);
            }
        }
        return $list;
    }

    /**
     * Attempt to return an instance of the job related to the Task
     * @param string $type being one of the PurgeRecord::TYPE_ constants
     * @return AbstractRecordCachePurgeJob|false
     */
    public function getJobClassForType($type) {
        $option = CloudflarePurgeService::getOptionForType($type);
        if(!$option) {
            // Logger::log("Cloudflare: getJobClassForType no option found for type={$type}");
            return false;
        }
        if($option == CloudflarePurgeService::TYPE_ENTIRE) {
            // Logger::log("Cloudflare: ignoring request by " . get_class($this->owner) . " to create an EntireCachePurgeJob", "NOTICE");
            return false;
        }
        $class = "NSWDPC\\Utilities\\Cloudflare\\{$option}CachePurgeJob";
        if(class_exists($class)) {
            return $class;
        }
        // Logger::log("Cloudflare: getJobClassForType no matching job found for class={$class}");
        return false;
    }

    /**
     * Based on the purge values returned for this record, create jobs to assist with record purging
     * @return array
     */
    public function getPurgeJobs($reason) {
        $jobs = [];
        // get all possible values this record may have, keys define jobs
        $values  = $this->owner->getPurgeValues();
        // no values means no jobs
        if(empty($values)) {
            // Logger::log("Cloudflare: getPurgeJobs there are no purge values for reason={$reason}");
            return [];
        }
        foreach($values as $key => $value) {
            if(empty($value)) {
                // value is an array of possible things to purge
                // Logger::log("Cloudflare: getPurgeJobs nothing found to purge for type={$key}");
                continue;
            }
            // Logger::log("Cloudflare: getPurgeJobs getting job for type={$key}");
            $class = self::getJobClassForType($key);
            if($class && class_exists($class)) {
                $job = Injector::inst()->createWithArgs(
                        $class,
                        [
                            $reason, // reason for job creation
                            $this->owner // this record
                        ]
                );
                if(!$job instanceof AbstractRecordCachePurgeJob) {
                    // Logger::log("Cloudflare: getPurgeJobs ignoring job as it is not an AbstractRecordCachePurgeJob");
                    continue;
                }
                $jobs[] = $job;
            } else {
                // Logger::log("Cloudflare: getPurgeJobs no job found for type {$key}");
                continue;
            }
        }
        return $jobs;
    }

    /**
     * Create a purge URL job for the record
     * Returns an array of QueuedJob instances queued successfull (not QueuedJobDescriptor) or false on error
     * @return array|false
     */
    final public function createPurgeJobs($reason, \DateTime $start = null) {
        try {
            $jobs_queued = [];
            if (!Cloudflare::config()->enabled) {
                Logger::log("Cloudflare: enabled=off","NOTICE");
                return false;
            }
            if(!$start) {
                $start = new \DateTime(); // run job 'now'
            }

            $start_after = $start->format('Y-m-d H:i:s');
            // Logger::log("Cloudflare: createPurgeJobs reason={$reason} from=" . get_class($this) . " starts={$start_after}");

            // get all possible jobs for this record
            $jobs = $this->owner->getPurgeJobs($reason);
            if(empty($jobs)) {
                // Logger::log("Cloudflare: createPurgeJobs there are no jobs available for reason={$reason}");
                return false;
            }
            foreach($jobs as $job) {
                if(!$job instanceof AbstractRecordCachePurgeJob) {
                    // Logger::log("Cloudflare: createPurgeJobs job " .  get_class($job) . " is not an instance of AbstractRecordCachePurgeJob");
                    continue;
                }
                if($job_id = QueuedJobService::singleton()
                                ->queueJob(
                                    $job,
                                    $start_after
                                )
                ) {
                    $descriptor = QueuedJobDescriptor::get()->byId($job_id);
                    if($descriptor && $descriptor->exists()) {
                        Logger::log("Cloudflare: createPurgeJobs reason={$reason} job #{$job_id}/" . get_class($job));
                        $jobs_queued[] = $job;
                    }
                }
            }

        } catch (\Exception $e) {
            Logger::log("Cloudflare: createPurgeJobs reason={$reason} failed with error={$e->getMessage()}", "WARNING");
        }
        return $jobs_queued;
    }

}
