<?php

namespace NSWDPC\Utilities\Cloudflare;

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
        foreach($types as $type) {
            if( isset( $mappings[ $type ] ) && array_key_exists($mappings[ $type ], $result) ) {
                // e.g result['tags'] = ['blog','support','security']
                $result[ $mappings[ $type ] ] = $this->getPurgeTypeValues( $type );
            }
        }
        return $result;
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
            return [];
        }
        foreach($values as $key => $value) {
            $job = CloudflarePurgeService::getJobForType($key);
            if($job) {
                $job->setObject($this);
                // the reason for the job, can be anything
                $job->setReason($reason);
            }
            $jobs[] = $job;
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
                return false;
            }
            if(!$start) {
                $start = new \DateTime(); // run job 'now'
            }
            // get all possible jobs for this record
            $jobs = $this->getPurgeJobs($reason);
            if(empty($jobs)) {
                return false;
            }
            foreach($jobs as $job) {
                if($job_id = QueuedJobService::singleton()
                                ->queueJob(
                                    $job,
                                    $start->format('Y-m-d H:i:s')
                                )
                ) {
                    $descriptor = QueuedJobDescriptor::get()->byId($job_id);
                    if($descriptor && $descriptor->exists()) {
                        $descriptors[] = $descriptor;
                    }
                }
            }

        } catch (\Exception $e) {
            // TODO: handle/show error when creating jobs ?
        }
        return $descriptors;
    }

}
