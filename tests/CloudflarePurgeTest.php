<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\DataObjectPurgeable;
use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use NSWDPC\Utilities\Cloudflare\Logger;
use NSWDPC\Utilities\Cloudflare\PurgeRecord;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Folder;
use SilverStripe\Assets\Image;
use Silverstripe\Assets\Dev\TestAssetStore;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Abstract test class for purge testing
 * Test functionality provided by the module
 * @author James
 */
abstract class CloudflarePurgeTest extends SapphireTest
{

    protected $usesDatabase = true;

    protected $client;

    protected $enabled = true;
    protected $auth_token = "TOKEN-test-123-abcd";
    protected $zone_id = "test-zone";

    protected static $extra_dataobjects = [
        TestVersionedRecord::class,
        PurgeRecord::class
    ];

    protected static $required_extensions = [
        TestVersionedRecord::class => [
            Versioned::class,
            DataObjectPurgeable::class
        ],
        PurgeRecord::class => [
            Versioned::class,
            DataObjectPurgeable::class
        ]
    ];

    public function setUp() : void {
        parent::setUp();

        // Mock a CloudflarePurgeService
        Injector::inst()->load([
            CloudflarePurgeService::class => [
                'class' => MockCloudflarePurgeService::class,
            ]
        ]);

        Config::modify()->update(CloudflarePurgeService::class, 'enabled', $this->enabled);

        // Token based auth
        Config::modify()->update(CloudflarePurgeService::class, 'auth_token', $this->auth_token);

        // Zone to purge
        Config::modify()->update(CloudflarePurgeService::class, 'zone_id', $this->zone_id);


        $this->client = Injector::inst()->get( CloudflarePurgeService::class );

        $this->assertTrue($this->client instanceof MockCloudflarePurgeService, "Client is not a MockCloudflarePurgeService");

        $adapter = $this->client->getAdapter();

        $this->assertTrue($adapter instanceof MockApiClient, "Adapter is not a MockApiClient");

        $this->assertTrue( MockCloudflarePurgeService::config()->get('enabled') );

        QueuedJobService::config()->set('use_shutdown_function', false);

    }

    /**
     * Some tests require a test asset store
     */
    protected function requireAssetStore($storeName) {
        // Set backend root to the StoreName
        TestAssetStore::activate($storeName);
        // Create a test files for each of the fixture references
        $fileIDs = array_merge(
            $this->allFixtureIDs(File::class),
            $this->allFixtureIDs(Image::class)
        );
        foreach ($fileIDs as $fileID) {
            /** @var File $file */
            $file = DataObject::get_by_id(File::class, $fileID);
            $file->setFromString(str_repeat('x', 1000000), $file->getFilename());
            $file->publishSingle();
        }
    }

    /**
     * Called from tearDown in tests requiring asset store
     */
    protected function resetAssetStore() {
        TestAssetStore::reset();
    }

    /**
     * @return QueuedJobService
     */
    protected function getQueuedJobService()
    {
        return singleton(QueuedJobService::class);
    }

    /**
     * Check the request against what was provided
     */
    protected function validatePurgeRequest(PurgeRecord $record, string $type) {
        $data = $this->client->getAdapter()->getMockRequestData();
        $values = $record->getPurgeTypeValues( $record->Type );
        $this->assertEquals($values, $data['options']['json'][ $type ], "Purge type={$type} request values match record getPurgeTypeValues");
        $this->assertEquals("http://localhost/client/v4/zones/{$this->client->getZoneIdentifier()}/purge_cache", $data['url'], "URI mismatch");

        $this->assertEquals(
            "Bearer " . $this->client->config()->get('auth_token'),
            $data['options']['headers']['Authorization']
        );
    }

}
