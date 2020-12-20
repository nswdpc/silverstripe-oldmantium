<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use Cloudflare\API\Auth\APIKey;
use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use SilverStripe\Dev\TestOnly;

class MockCloudflarePurgeService extends CloudflarePurgeService implements TestOnly
{

    private static $enabled = true;
    private static $email = "test@example.com";
    private static $auth_key = "test-123-abcd";
    private static $base_url = '';
    private static $zone_id = "test-zone";

    private static $endpoint_base_uri = "";

    protected $adapter;

    /**
     * Retrieve a cloudflare/sdk client
     * @return NSWDPC\Utilities\Cloudflare\Tests\MockCloudflareAdapter
     */
    public function getSdkClient() {

        $auth = new APIKey(
            $this->config()->get('email'),
            $this->config()->get('auth_key')
        );
        $this->adapter = new MockCloudflareAdapter($auth);
        return $this->adapter;
    }

    public function getAdapter() {
        return $this->adapter;
    }

}
