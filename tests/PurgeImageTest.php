<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\DataObjectPurgeable;
use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use NSWDPC\Utilities\Cloudflare\Logger;
use NSWDPC\Utilities\Cloudflare\PurgeRecord;
use NSWDPC\Utilities\Cloudflare\ImageCachePurgeJob;
use SilverStripe\Assets\File;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

require_once(dirname(__FILE__) . '/CloudflarePurgeTest.php');

/**
 * Test purge images
 * @author James
 */
class PurgeImageTest extends CloudflarePurgeTest
{

    /**
     * @var string
     */
    protected static $fixture_file = 'FilesFixture.yml';

    public function setUp() : void {
        parent::setUp();
        $this->requireAssetStore('PurgeImageTest');
    }

    protected function tearDown(): void
    {
        $this->resetAssetStore();
        parent::tearDown();
    }

    /**
     * Test purging images
     */
    public function testPurgeRecordImage() {

        $categories = File::config()->get('app_categories');
        $extensions = [];
        if( isset( $categories['image'] ) && is_array( $categories['image'] ) ) {
            $extensions = $categories['image'];
        }

        $this->assertNotEmpty($extensions);

        $purge = PurgeRecord::create([
            'Title' => 'Purge IMAGE',
            'Type' => CloudflarePurgeService::TYPE_IMAGE,
            'TypeValues'  => null
        ]);

        $purge->write();
        $purge->publishSingle();

        $values = $purge->getPurgeTypeValues($purge->Type);

        $this->assertEmpty($values, "This purge record should have no values");

        // test that a job was created for this record
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ ImageCachePurgeJob::class ] );
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
        $data = $this->client->getAdapter()->getMockRequestData();
        $expected = $this->client->prepUrls( $this->client->getPublicFilesByExtension($extensions) );
        sort($data['options']['json']['files']);
        sort($expected);
        $this->assertEquals($expected, $data['options']['json']['files']);

        $purge->doUnpublish();

        // test that a job was created for this record reason = 'write'
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ ImageCachePurgeJob::class ] );

        $this->assertEquals(0, $descriptors->count(), "Jobs count should be 0");

        $purge->delete();

        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ ImageCachePurgeJob::class ] );
        $this->assertEquals(0, $descriptors->count(), "Jobs count should be 0 after delete");

    }

}
