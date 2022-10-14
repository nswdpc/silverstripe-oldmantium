<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\DataObjectPurgeable;
use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use NSWDPC\Utilities\Cloudflare\Logger;
use NSWDPC\Utilities\Cloudflare\PurgeRecord;
use NSWDPC\Utilities\Cloudflare\FileExtensionCachePurgeJob;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

require_once(dirname(__FILE__) . '/CloudflarePurgeTest.php');

/**
 * Test purge by file extension
 * @author James
 */
class PurgeFileExtensionTest extends CloudflarePurgeTest
{

    /**
     * @var string
     */
    protected static $fixture_file = 'FilesFixture.yml';

    public function setUp() : void {
        parent::setUp();
        $this->requireAssetStore('PurgeFileExtensionTest');
    }

    protected function tearDown(): void
    {
        $this->resetAssetStore();
        parent::tearDown();
    }

    /**
     * Test purging images
     */
    public function testPurgeRecordFileExtension() {

        $purge = PurgeRecord::create([
            'Title' => 'Purge File Extension',
            'Type' => CloudflarePurgeService::TYPE_FILE_EXTENSION,
            'TypeValues'  => ['jpg','pdf']
        ]);

        $purge->write();
        $purge->doPublish();

        $values = $purge->getPurgeTypeValues($purge->Type);

        $this->assertNotEmpty($values, "This purge record should have values");

        // test that a job was created for this record
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ FileExtensionCachePurgeJob::class ] );
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
        $job->process();

        // check data
        $data = $this->client->getAdapter()->getData();
        $headers = $this->client->getAdapter()->getHeaders();
        $client_headers = $this->client->getAdapter()->getClientHeaders();
        $uri = $this->client->getAdapter()->getLastUri();

        $this->assertArrayHasKey('files', $data, "'files' does not exist in POST data");
        $this->assertEquals(1, count( array_keys($data) ), "There should only be one key in the data, found: " . count($data));

        $this->assertEquals("zones/{$this->client->getZoneIdentifier()}/purge_cache", $uri, "URI mismatch");

        $this->assertEquals(
            [
                "Bearer " . $this->client->config()->get('auth_token')
            ],
            array_values($client_headers),
            "Client AUTH headers mismatch"
        );


        $purge->doUnpublish();

        // test that a job was created for this record reason = 'write'
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ FileExtensionCachePurgeJob::class ] );

        $this->assertEquals(1, $descriptors->count(), "Jobs count should be 1");

        $descriptor = $descriptors->first();

        $job_data = unserialize($descriptor->SavedJobData);
        $this->assertEquals(DataObjectPurgeable::REASON_UNPUBLISH, $job_data->reason);

        $job = Injector::inst()->createWithArgs(
                $descriptor->Implementation,
                [
                    DataObjectPurgeable::REASON_UNPUBLISH,
                    $purge
                ]
        );

        $job->setup();
        $job->process();

        // check data
        $data = $this->client->getAdapter()->getData();
        $headers = $this->client->getAdapter()->getHeaders();
        $client_headers = $this->client->getAdapter()->getClientHeaders();
        $uri = $this->client->getAdapter()->getLastUri();

        $this->assertArrayHasKey('files', $data, "'files' does not exist in POST data");
        $this->assertEquals(1, count( array_keys($data) ), "There should only be one key in the data, found: " . count($data));

        $values = $purge->getPurgeTypeValues( $purge->Type );
        $this->assertEquals("zones/{$this->client->getZoneIdentifier()}/purge_cache", $uri, "URI mismatch");

        $this->assertEquals(
            [
                "Bearer " . $this->client->config()->get('auth_token')
            ],
            array_values($client_headers),
            "Client AUTH headers mismatch"
        );

        $purge->delete();

        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ FileExtensionCachePurgeJob::class ] );
        $this->assertEquals(0, $descriptors->count(), "Jobs count should be 0 after delete");

    }

}
