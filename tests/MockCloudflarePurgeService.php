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

    private static $enabled = true;
    private static $email = "test@example.com";
    private static $auth_key = "KEY-test-123-abcd";
    private static $auth_token = "TOKEN-test-123-abcd";
    private static $base_url = '';
    private static $zone_id = "test-zone";

    private static $endpoint_base_uri = "";

    protected $adapter;

    /**
     * Retrieve a cloudflare/sdk client
     * @return NSWDPC\Utilities\Cloudflare\Tests\MockCloudflareAdapter
     */
    public function getSdkClient() : ?CloudflareGuzzleAdapter {
        if($auth = $this->getAuthHandler()) {
            $this->adapter = new MockCloudflareAdapter($auth);
        }
        return $this->adapter;
    }

    public function getAdapter() {
        return $this->adapter;
    }

}
