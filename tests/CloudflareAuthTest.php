<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use Cloudflare\API\Auth\Auth;
use Cloudflare\API\Auth\APIKey;
use Cloudflare\API\Auth\APIToken;
use Cloudflare\API\Adapter\Guzzle as CloudflareGuzzleAdapter;
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

    public function setUp() : void {
        parent::setUp();
        // Mock a CloudflarePurgeService
        Injector::inst()->load([
            Cloudflare::class => [
                'class' => MockCloudflarePurgeService::class,
            ]
        ]);
        $this->client = Injector::inst()->get( Cloudflare::class );
        $this->assertTrue($this->client instanceof MockCloudflarePurgeService, "Client is not a MockCloudflarePurgeService");

    }

    /**
     * Test that the service returns the APIToken adapter by default
     */
    public function testAPITokenAuthAdapter() {
        Config::modify()->set( MockCloudflarePurgeService::class, 'auth_token', 'the auth token');
        Config::modify()->set( MockCloudflarePurgeService::class, 'auth_key', 'the auth key');
        $authAdapter = $this->client->getAuthHandler();
        $this->assertInstanceOf(APIToken::class, $authAdapter);
        $sdkClient = $this->client->getSdkClient();
        $this->assertInstanceOf(CloudflareGuzzleAdapter::class, $sdkClient);
    }

    /**
     * Test that the service returns the APIKey adapter when no token is present
     */
    public function testAPIKeyAuthAdapter() {
        Config::modify()->set( MockCloudflarePurgeService::class, 'auth_token', null);
        Config::modify()->set( MockCloudflarePurgeService::class, 'auth_key', 'the auth key');
        $authAdapter = $this->client->getAuthHandler();
        $this->assertInstanceOf(APIKey::class, $authAdapter);
        $sdkClient = $this->client->getSdkClient();
        $this->assertInstanceOf(CloudflareGuzzleAdapter::class, $sdkClient);
    }

}
