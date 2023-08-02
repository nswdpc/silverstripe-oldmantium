<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\DataObjectPurgeable;
use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use NSWDPC\Utilities\Cloudflare\Logger;
use NSWDPC\Utilities\Cloudflare\PurgeRecord;
use NSWDPC\Utilities\Cloudflare\FileExtensionCachePurgeJob;
use SilverStripe\Assets\File;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
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

        $extensions = ['jpg','pdf'];

        $purge = PurgeRecord::create([
            'Title' => 'Purge File Extension',
            'Type' => CloudflarePurgeService::TYPE_FILE_EXTENSION,
            'TypeValues'  => $extensions
        ]);

        $purge->write();
        $purge->publishSingle();

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
        $data = $this->client->getAdapter()->getMockRequestData();
        $expected = $this->client->prepUrls( $this->client->getPublicFilesByExtension($extensions) );
        sort($data['options']['json']['files']);
        sort($expected);
        $this->assertEquals($expected, $data['options']['json']['files']);

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
        $data = $this->client->getAdapter()->getMockRequestData();
        $expected = $this->client->prepUrls( $this->client->getPublicFilesByExtension($extensions) );
        sort($data['options']['json']['files']);
        sort($expected);
        $this->assertEquals($expected, $data['options']['json']['files']);

        $purge->delete();

        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ FileExtensionCachePurgeJob::class ] );
        $this->assertEquals(0, $descriptors->count(), "Jobs count should be 0 after delete");

    }

    public function testGetPublicFilesByExtension() {
        $extensions = ['js','png', 'pdf'];
        $expected = [
            '_resources/themes/website/assets/banner.png',
            '_resources/themes/website/assets/app.js',
            '_resources/vendor/other-org/module/assets/image.png',
            '_resources/vendor/other-org/module/assets/script.js',
            '_resources/vendor/org/module/assets/image.png',
            '_resources/vendor/org/module/assets/script.js',
            '/assets/PurgeFileExtensionTest/file.pdf'
        ];
        $result = $this->client->getPublicFilesByExtension( $extensions );
        sort($result);
        sort($expected);
        $this->assertEquals($expected, $result);

        Config::modify()->update( Director::class, 'alternate_base_url', 'https://something.example.com/');

        $urls = $this->client->prepUrls($result);
        $expectedUrls = [
            'https://something.example.com/_resources/themes/website/assets/banner.png',
            'https://something.example.com/_resources/themes/website/assets/app.js',
            'https://something.example.com/_resources/vendor/other-org/module/assets/image.png',
            'https://something.example.com/_resources/vendor/other-org/module/assets/script.js',
            'https://something.example.com/_resources/vendor/org/module/assets/image.png',
            'https://something.example.com/_resources/vendor/org/module/assets/script.js',
            'https://something.example.com/assets/PurgeFileExtensionTest/file.pdf'
        ];
        sort($urls);
        sort($expectedUrls);
        $this->assertEquals($expectedUrls, $urls);


        // unpublish
        $file = $this->objFromFixture(File::class, 'pdf');
        $file->doUnpublish();

        $expected = [
            '_resources/themes/website/assets/banner.png',
            '_resources/themes/website/assets/app.js',
            '_resources/vendor/other-org/module/assets/image.png',
            '_resources/vendor/other-org/module/assets/script.js',
            '_resources/vendor/org/module/assets/image.png',
            '_resources/vendor/org/module/assets/script.js'
        ];
        $result = $this->client->getPublicFilesByExtension( $extensions );
        sort($result);
        sort($expected);
        $this->assertEquals($expected, $result);
    }

}
