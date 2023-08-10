<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\NumericField;
use Silverstripe\ORM\ArrayList;
use Silverstripe\ORM\DataExtension;
use SilverStripe\Versioned\Versioned;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

/**
 * Extension that decorates a purgeable dataobject, currently support URLs only
 * @author James
 */

class DataObjectPurgeable extends DataExtension implements CloudflarePurgeable {

    /**
     * @var string
     */
    const REASON_WRITE = 'write';

    /**
     * @var string
     */
    const REASON_DELETE = 'delete';

    /**
     * @var string
     */
    const REASON_PUBLISH = 'publish';

    /**
     * @var string
     */
    const REASON_UNPUBLISH = 'unpublish';

    /**
     * @var array
     */
    private static $db = [
        'CachePurgeAt' => 'Datetime', // add ability to purge dataobject at a certain date / time
        'CacheMaxAge' => 'Double'// minutes TTL
    ];

    /**
     * Prior to write, remove any pending jobs for this record
     */
    public function onBeforeWrite()
    {
        if($this->owner->exists()) {
            $this->clearCurrentJobs();
        }
    }

    /**
     * Prior to delete, remove any pending jobs for this record
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
                $start = new \DateTime( $this->owner->CachePurgeAt );
            }
            $this->createPurgeJobs('publish', $start);
        }
    }

    /**
     * After unpublish, selectively handle purge jobs
     */
    public function onAfterUnpublish()
    {
        if ($this->owner->hasExtension(Versioned::class)) {
            if($this->owner->clearPurgeJobsOnUnPublish()) {
                $this->clearCurrentJobs();
            } else {
                // Logger::log("Cloudflare: creating jobs for reason=unpublish");
                $start = null;
                if($this->owner->CachePurgeAt) {
                    $start = new \DateTime( $this->owner->CachePurgeAt );
                }
                $this->createPurgeJobs('unpublish');
            }
        }
    }

    /**
     * BC: do not clear jobs on publish
     */
    public function clearPurgeJobsOnUnPublish() : bool {
        return false;
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
        $result = [];

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
            // a $type is one of the TYPE_* constant values
            if( isset( $mappings[ $type ] ) ) {
                /**
                 * Example $type=Tag, mapping type = "tags"
                 * e.g $result[ 'Tag' ] => ['blog','support','security'] ]
                 * result is keyed by the allowed purge_cache options
                 */
                $result[ $type ] = $this->owner->getPurgeTypeValues( $type );
                // Logger::log("Cloudflare: getPurgeValues returning " .  count($result[ $type ]) . " records for type {$type}");
            }
        }

        return $result;
    }

    /**
     * Support owner records that only purge their URLs
     * The owner record can implement a method of this name (see PurgeRecord for example)
     * to return specific purge values
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
        $jobs = $this->getCurrentPurgeJobDescriptors();
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

        $jobs = QueuedJobDescriptor::get();
        $jobs = $jobs->filter([
            'Implementation' => $implementations
        ]);
        $jobs = $jobs->exclude([
            'JobStatus' => $statii
        ]);

        $name = $this->getPurgeRecordName();
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
     * Attempt to return the classname for the job linked to the purge type
     * @param string $type being one of the CloudflarePurgeService::TYPE_ constant values
     * @return string|false
     */
    public static function getJobClassForType($type) {
        $class = "NSWDPC\\Utilities\\Cloudflare\\{$type}CachePurgeJob";
        if(class_exists($class)) {
            return $class;
        } else {
            return false;
        }
    }

    /**
     * Based on the purge values returned for this record, create jobs to assist with record purging
     * @return array jobs created
     */
    public function getPurgeJobs($reason): array {
        $jobs = [];
        // get all possible values this record may have, keys define jobs
        $values  = $this->getPurgeValues();
        // no values means no jobs
        if(empty($values)) {
            return [];
        }
        foreach($values as $type => $spec) {
            $class = self::getJobClassForType($type);
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

            $client = Injector::inst()->get( CloudflarePurgeService::class );
            if ( !Config::inst()->get( CloudflarePurgeService::class, 'enabled') ) {
                Logger::log("Cloudflare: createPurgeJobs called but not enabled in configuration","NOTICE");
                return false;
            }
            if(!$start) {
                $start = new \DateTime(); // run job 'now'
            }

            $start_after = $start->format('Y-m-d H:i:s');
            // Logger::log("Cloudflare: createPurgeJobs reason={$reason} from=" . get_class($this) . " starts={$start_after}");

            // get all possible jobs for this record
            $jobs = $this->getPurgeJobs($reason);
            if(empty($jobs)) {
                Logger::log("Cloudflare: createPurgeJobs there are no jobs available for reason={$reason}","NOTICE");
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
                        // Logger::log("Cloudflare: createPurgeJobs reason={$reason} job #{$job_id}/" . get_class($job));
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
