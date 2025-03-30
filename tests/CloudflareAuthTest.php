<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Config;
use SilverStripe\Dev\SapphireTest;

/**
 * Test API Token / legacy Key handling
 * @author James
 */
class CloudflareAuthTest extends SapphireTest
{

    protected $usesDatabase = false;

    protected function setUp() : void {
        parent::setUp();
        // Mock a CloudflarePurgeService
        Injector::inst()->load([
            CloudflarePurgeService::class => [
                'class' => MockCloudflarePurgeService::class,
            ]
        ]);
    }

    /**
     * Test that the service returns the APIToken adapter by default
     */
    public function testAPITokenAuthAdapter() {
        Config::modify()->set( MockCloudflarePurgeService::class, 'auth_token', 'test-auth-token');
        Config::modify()->set( MockCloudflarePurgeService::class, 'enabled', true);
        $service = Injector::inst()->get( CloudflarePurgeService::class );
        $this->assertInstanceOf(MockCloudflarePurgeService::class, $service, "Service is not a MockCloudflarePurgeService");
        $urls = ['https://example.com/foo'];
        $response = $service->purgeUrls($urls);
        $client = $service->getApiClient();
        $this->assertInstanceOf(MockApiClient::class, $client, "Service is not a MockApiClient");
        $data = MockApiClient::getLastRequestData();
        $this->assertEquals( 'Bearer test-auth-token', $data['options']['headers']['Authorization'] );
    }

}
