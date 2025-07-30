<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\ApiClient;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

require_once(__DIR__ . '/CloudflarePurgeTestAbstract.php');

/**
 * Test purge File objects
 * @author James
 */
class FilePurgeTest extends CloudflarePurgeTestAbstract
{
    protected $usesDatabase = true;

    protected static $fixture_file = "./FilesFixture.yml";

    #[\Override]
    public function setUp(): void
    {
        parent::setUp();

        TestAssetStore::activate('FilePurgeTest');

        Config::modify()->set(
            File::class,
            'allowed_extensions',
            [
                'csv', 'txt',
                'jpg','jpeg','gif','png',
                'txt',
                'doc','docx','xls','xlsx','ppt','pptx',
                'pdf'
            ]
        );

        $this->logInWithPermission('ADMIN');

        // Create test files for each of the fixture references
        $fileIDs = $this->allFixtureIDs(File::class);
        foreach ($fileIDs as $fileID) {
            /** @var File $file */
            $file = DataObject::get_by_id(File::class, $fileID);
            $file->setFromString(str_repeat('x', 1000000), $file->getFilename());
        }
    }

    public function testPurgeOnPublishSingle(): void
    {
        $file = $this->objFromFixture(File::class, 'file-pdf');
        $file->publishSingle();

        $data = MockApiClient::getLastRequestData();
        $this->assertEquals('after-write', $data['options']['headers'][ApiClient::HEADER_PURGE_REASON]);
        $this->assertEquals($file->AbsoluteLink(), $data['options']['json']['files'][0]);
    }

    public function testPurgeOnPublishRecursive(): void
    {
        $file = $this->objFromFixture(File::class, 'file-pdf');
        $file->publishRecursive();

        $data = MockApiClient::getLastRequestData();
        $this->assertEquals('after-write', $data['options']['headers'][ApiClient::HEADER_PURGE_REASON]);
        $this->assertEquals($file->AbsoluteLink(), $data['options']['json']['files'][0]);
    }

    public function testPurgeOnUnpublish(): void
    {
        $file = $this->objFromFixture(File::class, 'file-pdf');
        $file->publishSingle();// need to have a published version in order to unpublish
        // grab the link while the file is still published
        $absoluteLink = $file->AbsoluteLink();
        $file->doUnpublish();
        $data = MockApiClient::getLastRequestData();
        $this->assertEquals('before-delete', $data['options']['headers'][ApiClient::HEADER_PURGE_REASON]);
        $this->assertEquals($absoluteLink, $data['options']['json']['files'][0]);
    }

    public function testPurgeOnDeleteFromLiveStage(): void
    {
        $file = $this->objFromFixture(File::class, 'file-pdf');
        $file->publishSingle();// need to have a published version in order to unpublish
        // grab the link while the file is still published
        $absoluteLink = $file->AbsoluteLink();
        $file->deleteFromStage(Versioned::LIVE);
        $data = MockApiClient::getLastRequestData();
        $this->assertEquals('before-delete', $data['options']['headers'][ApiClient::HEADER_PURGE_REASON]);
        $this->assertEquals($absoluteLink, $data['options']['json']['files'][0]);
    }

    public function testPurgeOnDeleteFromDraftStage(): void
    {
        $file = $this->objFromFixture(File::class, 'file-pdf');
        // grab link while the object exists, the draft file link
        $absoluteLink = $file->AbsoluteLink();
        $file->deleteFromStage(Versioned::DRAFT);
        $data = MockApiClient::getLastRequestData();
        $this->assertEquals('before-delete', $data['options']['headers'][ApiClient::HEADER_PURGE_REASON]);
        $this->assertEquals($absoluteLink, $data['options']['json']['files'][0]);
    }

}
