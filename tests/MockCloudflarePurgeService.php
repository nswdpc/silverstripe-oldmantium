<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use Cloudflare\API\Auth\Auth;
use Cloudflare\API\Auth\APIKey;
use Cloudflare\API\Auth\APIToken;
use Cloudflare\API\Adapter\Guzzle as CloudflareGuzzleAdapter;
use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use SilverStripe\Dev\TestOnly;

class MockCloudflarePurgeService extends CloudflarePurgeService implements TestOnly
{

    /**
     * Retrieve a cloudflare/sdk client
     * @return NSWDPC\Utilities\Cloudflare\Tests\MockCloudflareAdapter
     */
    public function getSdkClient() : ?CloudflareGuzzleAdapter {

        if($this->sdk_client) {
            return $this->sdk_client;
        }

        if($auth = $this->getAuthHandler()) {
            $this->sdk_client = new MockCloudflareAdapter($auth);
        }

        return $this->sdk_client;
    }

    /**
     * Helper method to get SKD client
     * @return NSWDPC\Utilities\Cloudflare\Tests\MockCloudflareAdapter
     */
    public function getAdapter() {
        return $this->getSdkClient();
    }

    /**
     * Return the absolute path to the test public resources dir
     * @return string
     */
    protected function getPublicResourcesDir() : string {
        return dirname(__FILE__) . "/public/_resources/";
    }

}
