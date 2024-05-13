<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use SilverStripe\Core\Config\Config;

require_once(dirname(__FILE__) . '/CloudflarePurgeTestAbstract.php');

/**
 * Test base_url configuration
 * @author James
 */
class BaseURLTest extends CloudflarePurgeTestAbstract
{

    protected $usesDatabase = false;

    public function testBaseURL() {

        $scheme = "https";
        $host = "alt.example.com";
        $baseUrl = $scheme . "://" . $host;
        Config::modify()->set(CloudflarePurgeService::class, 'base_url', $baseUrl);

        // these URLS should all have their scheme + host updated
        $urls = [
            "https://example.com/testversionedrecord.html?stage=Stage&alternateformat=1"
                => "{$baseUrl}/testversionedrecord.html?stage=Stage&alternateformat=1",

            "/testversionedrecord.html?stage=Stage&alternateformat=1"
                => "{$baseUrl}/testversionedrecord.html?stage=Stage&alternateformat=1",

            "https://example.com/testversionedrecord.html?stage=Stage"
                => "{$baseUrl}/testversionedrecord.html?stage=Stage",

            // this URL will produce a host as below
            "example.com/testversionedrecord.html?stage=Stage"
                => "{$baseUrl}example.com/testversionedrecord.html?stage=Stage",

            "https://example.org/testversionedrecord.html?alternateformat=1"
                => "{$baseUrl}/testversionedrecord.html?alternateformat=1",

            "https://example.org/testversionedrecord/"
                => "{$baseUrl}/testversionedrecord/",

            "https://example.org/"
                => "{$baseUrl}/",

            "https://example.org"
                => "{$baseUrl}",
        ];

        foreach($urls as $inUrl => $expectedUrl) {
            $outUrls = CloudflarePurgeService::replaceWithBaseUrl( [$inUrl] );
            $this->assertEquals($expectedUrl, $outUrls[0], "Returned URL does not match expected");
        }

    }

}
