<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\DataObjectPurgeable;
use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use NSWDPC\Utilities\Cloudflare\Logger;
use NSWDPC\Utilities\Cloudflare\PurgeRecord;
use NSWDPC\Utilities\Cloudflare\TagCachePurgeJob;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

require_once(dirname(__FILE__) . '/CloudflarePurgeTestAbstract.php');

/**
 * Test purge cache tag
 * @author James
 */
class PurgeTagTest extends CloudflarePurgeTestAbstract
{

    public function testPurgeRecordTag() {

        $tags= [
            'foo',
            'bar',
            'tag-three'
        ];

        $purge = PurgeRecord::create([
            'Title' => 'Purge TAG',
            'Type' => CloudflarePurgeService::TYPE_TAG,
            'TypeValues'  => $tags
        ]);

        $purge->write();
        $purge->publishSingle();

        $values = $purge->getPurgeTypeValues($purge->Type);

        $this->assertEquals(count($tags), count($values), "Prefix count mismatch");

        // test that a job was created for this record
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ TagCachePurgeJob::class ] );
        $this->assertEquals(1, $descriptors->count(), "Jobs count should be 1");

        $descriptor = $descriptors->first();

        $job_data = unserialize($descriptor->SavedJobData);
        $this->assertEquals(DataObjectPurgeable::REASON_PUBLISH, $job_data->reason);

        $job = Injector::inst()->createWithArgs(
                $descriptor->Implementation,
                [
                    DataObjectPurgeable::REASON_PUBLISH,
                    $purge
                ]
        );

        $job->setup();
        $job->process();// request / response

        // check data
        $this->validatePurgeRequest($purge, 'tags');

        $purge->doUnpublish();

        // test that a job was created for this record reason = 'write'
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ TagCachePurgeJob::class ] );

        $this->assertEquals(0, $descriptors->count(), "Jobs count should be 0");

        $purge->delete();

        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ TagCachePurgeJob::class ] );
        $this->assertEquals(0, $descriptors->count(), "Jobs count should be 0 after delete");

    }

}
