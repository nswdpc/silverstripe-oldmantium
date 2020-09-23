<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;
use Symbiote\Cloudflare\Cloudflare;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Trait provides the ability to create a queued job of a certain type and sets
 * the start datetime for that job
 * @author James
 */
trait PurgeJob {

    /**
     * Return an array of values that can be purged
     * @return array
     */
    final public function getPurgeValues()
    {

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
        $types = $this->getPurgeTypes();
        if(empty($types)) {
            // no purge types means no values
            return [];
        }

        Logger::log("Cloudflare: createPurgeJobs types=" . json_encode($types) );

        foreach($types as $type) {
            // a $type is one of the TYPE_* constants
            if( isset( $mappings[ $type ] ) && array_key_exists($mappings[ $type ], $result) ) {
                // e.g result['tags'] = ['blog','support','security']
                // result is keyed by the allowed purge_cache options
                $result[ $mappings[ $type ] ] = $this->getPurgeTypeValues( $type );
                Logger::log("Cloudflare: createPurgeJobs returning " .  count($result[ $mappings[ $type ] ]) . " records for type {$type}");
            }
        }

        return $result;
    }

    /**
     * Attempt to return an instance of the job related to the Task
     * @param string $type being one of the PurgeRecord::TYPE_ constants
     * @return AbstractRecordCachePurgeJob|false
     */
    public function getJobClassForType($type) {
        $option = CloudflarePurgeService::getOptionForType($type);
        if(!$option) {
            Logger::log("Cloudflare: getJobClassForType no option found for type={$type}");
            return false;
        }
        $class = "NSWDPC\\Utilities\\Cloudflare\\{$option}CachePurgeJob";
        if(class_exists($class)) {
            return $class;
        }
        Logger::log("Cloudflare: getJobClassForType no matching job found for class={$class}");
        return false;
    }

    /**
     * Based on the purge values returned for this record, create jobs to assist with record purging
     * @return array
     */
    public function getPurgeJobs($reason) {
        $jobs = [];
        // get all possible values this record may have, keys define jobs
        $values  = $this->getPurgeValues();
        // no values means no jobs
        if(empty($values)) {
            Logger::log("Cloudflare: createPurgeJobs there are no purge values for reason={$reason}");
            return [];
        }
        foreach($values as $key => $value) {
            if(empty($value)) {
                // value is an array of possible things to purge
                Logger::log("Cloudflare: createPurgeJobs nothing found to purge for type={$key}");
                continue;
            }
            Logger::log("Cloudflare: createPurgeJobs getting job for type={$key}");
            $class = self::getJobClassForType($key);
            if($class && class_exists($class)) {
                $job = Injector::inst()->createWithArgs(
                        $class,
                        [
                            $reason, // reason for job creation
                            $this // this record
                        ]
                );
                if(!$job instanceof AbstractRecordCachePurgeJob) {
                    Logger::log("Cloudflare: createPurgeJobs ignoring job as it is not an AbstractRecordCachePurgeJob");
                    continue;
                }
                $jobs[] = $job;
            } else {
                Logger::log("Cloudflare: createPurgeJobs no job found for type {$key}");
                continue;
            }
        }
        return $jobs;
    }

    /**
     * Create a purge URL job for the record
     * @return array|false
     */
    final public function createPurgeJobs($reason, \DateTime $start = null) {
        try {
            $descriptors = [];
            if (!Cloudflare::config()->enabled) {
                Logger::log("Cloudflare: enabled=off","NOTICE");
                return false;
            }
            if(!$start) {
                $start = new \DateTime(); // run job 'now'
            }

            $start_after = $start->format('Y-m-d H:i:s');
            Logger::log("Cloudflare: createPurgeJobs reason={$reason} from=" . get_class($this) . " starts={$start_after}");

            // get all possible jobs for this record
            $jobs = $this->getPurgeJobs($reason);
            if(empty($jobs)) {
                Logger::log("Cloudflare: createPurgeJobs there are no jobs available for reason={$reason}");
                return false;
            }
            foreach($jobs as $job) {
                if(!$job instanceof AbstractRecordCachePurgeJob) {
                    Logger::log("Cloudflare: createPurgeJobs job " .  get_class($job) . " is not an instance of AbstractRecordCachePurgeJob");
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
                        $descriptors[] = $descriptor;
                    }
                }
            }

        } catch (\Exception $e) {
            Logger::log("Cloudflare: createPurgeJobs reason={$reason} failed with error={$e->getMessage()}", "WARNING");
        }
        return $descriptors;
    }

}
