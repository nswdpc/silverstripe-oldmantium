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
     * @return ApiClient|null
     */
    public function getApiClient() : ?ApiClient {
        if(!self::config()->get('enabled')) {
            return null;
        }
        if($this->client) {
            return $this->client;
        }
        $client = new GuzzleHttpClient();
        $token = self::config()->get('auth_token');
        $this->client = new MockApiClient($client, $token);
        return $this->client;
    }

    /**
     * Helper method to get client
     */
    public function getAdapter() : ?ApiClient {
        return $this->getApiClient();
    }

    /**
     * Return the absolute path to the test public resources dir
     * @return string
     */
    protected function getPublicResourcesDir() : string {
        return dirname(__FILE__) . "/public/_resources/";
    }

}
