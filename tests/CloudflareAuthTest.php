<?php

declare(strict_types=1);

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Environment;
use SilverStripe\Dev\SapphireTest;

/**
 * Test API Token / legacy Key handling
 * @author James
 */
class CloudflareAuthTest extends SapphireTest
{
    protected $usesDatabase = false;

    #[\Override]
    protected function setUp(): void
    {
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
    public function testAPITokenAuthAdapter(): void
    {
        // Token based auth
        $authToken = 'test-auth-token';
        Environment::setEnv('NSWDPC_CFPURGE_AUTHTOKEN', $authToken);
        Config::modify()->set(CloudflarePurgeService::class, 'auth_token', '');
        Config::modify()->set(MockCloudflarePurgeService::class, 'enabled', true);
        $service = Injector::inst()->get(CloudflarePurgeService::class);
        $this->assertInstanceOf(MockCloudflarePurgeService::class, $service, "Service is not a MockCloudflarePurgeService");
        $urls = ['https://example.com/foo'];
        $service->purgeUrls($urls);
        $client = $service->getApiClient();
        $this->assertInstanceOf(MockApiClient::class, $client, "Service is not a MockApiClient");
        $data = MockApiClient::getLastRequestData();
        $this->assertEquals('Bearer ' . $authToken, $data['options']['headers']['Authorization']);
    }

}
