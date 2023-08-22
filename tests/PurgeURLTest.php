<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\DataObjectPurgeable;
use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use NSWDPC\Utilities\Cloudflare\Logger;
use NSWDPC\Utilities\Cloudflare\PurgeRecord;
use NSWDPC\Utilities\Cloudflare\URLCachePurgeJob;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

require_once(dirname(__FILE__) . '/CloudflarePurgeTest.php');

/**
 * Test purge URLs
 * @author James
 */
class PurgeURLTest extends CloudflarePurgeTest
{

    public function testPurgeRecordURL() {

        $urls = [
            'https://example.com',
            '/foo/bar'
        ];
        $purge = PurgeRecord::create([
            'Title' => 'Purge URLs',
            'Type' => CloudflarePurgeService::TYPE_URL,
            'TypeValues'  => $urls
        ]);

        $purge->write();
        $purge->publishSingle();

        $values = $purge->TypeValues;

        // test that a job was created for this record
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ URLCachePurgeJob::class ] );
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
        $job->process();// request / resppnse

        // check data
        $this->validatePurgeRequest($purge, 'files');

        $purge->doUnpublish();

        // test that a job was created for this record reason = 'write'
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ URLCachePurgeJob::class ] );

        $this->assertEquals(0, $descriptors->count(), "Jobs count should be 0");

        $purge->delete();

        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ URLCachePurgeJob::class ] );
        $this->assertEquals(0, $descriptors->count(), "Jobs count should be 0 after delete");

    }

}
