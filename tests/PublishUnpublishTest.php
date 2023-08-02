<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\DataObjectPurgeable;
use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use NSWDPC\Utilities\Cloudflare\Logger;
use NSWDPC\Utilities\Cloudflare\PurgeRecord;
use NSWDPC\Utilities\Cloudflare\URLCachePurgeJob;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\Services\QueuedJob;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

require_once(dirname(__FILE__) . '/CloudflarePurgeTest.php');

/**
 * Test publish/unpublish events on versioned record
 * @author James
 */
class PublishUnpublishTest extends CloudflarePurgeTest {

    /**
     * Method used on publish and unpublish tests
     */
    protected function createAndPublish() {

        $record = TestVersionedRecord::create([
            'Title' => 'Test record write'
        ]);

        // test values are sane
        $types = $record->getPurgeTypes();
        $this->assertEquals(1, count($types), "getPurgeTypes count is not 1");
        $this->assertEquals(CloudflarePurgeService::TYPE_URL, $types[0], "type is not CloudflarePurgeService::TYPE_URL");

        $urls = $record->getPurgeUrlList();

        $this->assertEquals(3, count($urls), "getPurgeUrlList count is not 2");
        $this->assertTrue(array_search($record->AbsoluteLink(), $urls) !== false, "AbsoluteLink not found in getPurgeUrlList");
        $this->assertTrue(array_search($record->SomeRelatedLink(), $urls) !== false, "SomeRelatedLink not found in getPurgeUrlList");

        $record->write();
        $record->publishSingle();

        // test that a job was created for this record
        $descriptors = $record->getCurrentPurgeJobDescriptors( [ URLCachePurgeJob::class ] );
        $this->assertEquals(1, $descriptors->count(), "Jobs count should be 1");

        $descriptor = $descriptors->first();

        $job_data = unserialize($descriptor->SavedJobData);
        $this->assertEquals(DataObjectPurgeable::REASON_PUBLISH, $job_data->reason);

        $job = Injector::inst()->createWithArgs(
                $descriptor->Implementation,
                [
                    DataObjectPurgeable::REASON_PUBLISH,
                    $record
                ]
        );

        $job->setup();
        $job->process();

        // check data
        $data = $this->client->getAdapter()->getMockRequestData();
        $urls = $record->getPurgeUrlList();
        CloudflarePurgeService::removeReadingMode($urls);

        $this->assertEquals( $urls, $data['options']['json']['files'], "Purged files sent in data does not match record getPurgeUrlList");
        return $record;

    }

    /**
     * Test record publishing
     */
    public function testRecordPublish() {
        $this->createAndPublish();
    }

    /**
     * Test record publishing & unpublishing
     */
    public function testRecordUnpublish() {

        $record = $this->createAndPublish();

        $record->doUnPublish();

        // test that a job was created for this record reason = 'write'
        $descriptors = $record->getCurrentPurgeJobDescriptors( [ URLCachePurgeJob::class ] );

        $this->assertEquals(1, $descriptors->count(), "Jobs count should be 1");

        $descriptor = $descriptors->first();

        $job_data = unserialize($descriptor->SavedJobData);
        $this->assertEquals(DataObjectPurgeable::REASON_UNPUBLISH, $job_data->reason);

        $job = Injector::inst()->createWithArgs(
                $descriptor->Implementation,
                [
                    DataObjectPurgeable::REASON_UNPUBLISH,
                    $record
                ]
        );

        $job->setup();
        $job->process();

        // check data
        $data = $this->client->getAdapter()->getMockRequestData();
        $urls = $record->getPurgeUrlList();
        CloudflarePurgeService::removeReadingMode($urls);

        $this->assertEquals( $urls, $data['options']['json']['files'], "Purged files sent in data does not match record getPurgeUrlList");

    }

    public function testRecordDelete() {

        $record = $this->createAndPublish();
        $record2 = $this->createAndPublish();

        $descriptors = QueuedJobDescriptor::get()->filter([
            'Implementation' => URLCachePurgeJob::class
        ])->exclude([
            'JobStatus' => [
                QueuedJob::STATUS_RUN,
                QueuedJob::STATUS_COMPLETE
            ]
        ]);

        $this->assertEquals(2, $descriptors->count() );

        $record->delete();

        $descriptors = QueuedJobDescriptor::get()->filter([
            'Implementation' => URLCachePurgeJob::class
        ])->exclude([
            'JobStatus' => [
                QueuedJob::STATUS_RUN,
                QueuedJob::STATUS_COMPLETE
            ]
        ]);

        $this->assertEquals(1, $descriptors->count() );

        // remaining descriptor
        $descriptor = $descriptors->first();
        $data = unserialize($descriptor->SavedJobData);
        $this->assertEquals(2, $data->PurgeRecordID);
        $this->assertEquals(TestVersionedRecord::class, $data->PurgeRecordType);


    }
}
