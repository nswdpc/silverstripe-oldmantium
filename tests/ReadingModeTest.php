<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use NSWDPC\Utilities\Cloudflare\Logger;

require_once(dirname(__FILE__) . '/CloudflarePurgeTestAbstract.php');

/**
 * Test reading mode removal from URLs
 * @author James
 */
class ReadingModeTest extends CloudflarePurgeTestAbstract
{

    protected $usesDatabase = false;

    public function testReadingMode() {

        // URLs should have reading mode removed from query string
        $urls = [
            "https://example.com/testversionedrecord.html?stage=Stage&alternateformat=1"
                => "https://example.com/testversionedrecord.html?alternateformat=1",

            "/testversionedrecord.html?stage=Stage&alternateformat=1"
                => "/testversionedrecord.html?alternateformat=1",

            "https://example.com/testversionedrecord.html?stage=Stage"
                => "https://example.com/testversionedrecord.html",

            "example.com/testversionedrecord.html?stage=Stage"
                => "example.com/testversionedrecord.html",

            // no reading mode -> unchanged
            "https://example.org/testversionedrecord.html?alternateformat=1"
                => "https://example.org/testversionedrecord.html?alternateformat=1",

            "https://example.org/testversionedrecord/"
                => "https://example.org/testversionedrecord/",

            "https://example.org/"
                => "https://example.org/",
        ];

        foreach($urls as $in => $expected) {
            $parseUrls = [$in];
            CloudflarePurgeService::removeReadingMode($parseUrls);
            $this->assertEquals($expected, $parseUrls[0], "Returned URL does not match expected");
        }

    }

}
