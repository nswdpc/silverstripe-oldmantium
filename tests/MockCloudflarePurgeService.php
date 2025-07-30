<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use GuzzleHttp\Client as GuzzleHttpClient;
use NSWDPC\Utilities\Cloudflare\ApiClient;
use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use SilverStripe\Dev\TestOnly;

class MockCloudflarePurgeService extends CloudflarePurgeService implements TestOnly
{
    /**
     * Retrieve the ApiClient
     */
    #[\Override]
    public function createApiClient(): ApiClient
    {
        $client = new GuzzleHttpClient();
        $token = self::config()->get('auth_token');
        // when a new API client is created, clear the request history
        MockApiClient::clearRequestHistory();
        return new MockApiClient($client, $token);
    }

    /**
     * Return the absolute path to the test public resources dir
     */
    protected function getPublicResourcesDir(): string
    {
        return __DIR__ . "/public/_resources/";
    }

}
