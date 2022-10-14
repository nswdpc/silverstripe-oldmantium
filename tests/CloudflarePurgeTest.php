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
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;
use Symbiote\Cloudflare\Cloudflare;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Abstract test class for purge testing
 * Test functionality provided by the module
 * Note: requires a service to accept HTTP requests
 * @author James
 */
abstract class CloudflarePurgeTest extends SapphireTest
{

    protected $usesDatabase = true;

    protected $client;

    protected $enabled = true;

    protected $email = "test@example.com";// legacy
    protected $auth_key = "KEY-test-123-abcd";// legacy
    protected $base_url = '';
    protected $auth_token = "TOKEN-test-123-abcd";
    protected $zone_id = "test-zone";
    protected $endpoint_base_uri = "";

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
            Cloudflare::class => [
                'class' => MockCloudflarePurgeService::class,
            ]
        ]);

        MockCloudflarePurgeService::config()->update('enabled', $this->enabled);

        // Token based auth
        MockCloudflarePurgeService::config()->update('auth_token', $this->auth_token);

        // Zone to purge
        MockCloudflarePurgeService::config()->update('zone_id', $this->zone_id);

        // legacy auth
        MockCloudflarePurgeService::config()->update('email', $this->email);
        MockCloudflarePurgeService::config()->update('auth_key', $this->auth_key);


        $this->client = Injector::inst()->get( Cloudflare::class );

        $this->assertTrue($this->client instanceof MockCloudflarePurgeService, "Client is not a MockCloudflarePurgeService");

        $adapter = $this->client->getAdapter();

        $this->assertTrue($adapter instanceof MockCloudflareAdapter, "SDK client is not a MockCloudflareAdapter");

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
            $file->doPublish();
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

}
