<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\ApiClient;
use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use NSWDPC\Utilities\Cloudflare\Logger;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Dev\TestAssetStore;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

require_once(dirname(__FILE__) . '/CloudflarePurgeTestAbstract.php');

/**
 * Test purge SiteTree objects
 * @author James
 */
class SiteTreePurgeTest extends CloudflarePurgeTestAbstract
{

    protected $usesDatabase = true;

    protected static $fixture_file = "./SiteTreeFixture.yml";

    public function setUp(): void
    {
        parent::setUp();
    }

    public function testPurgeOnPublishSingle() {
        $sitetree = $this->objFromFixture(SiteTree::class, 'page-1');
        $sitetree->publishSingle();
        $data = MockApiClient::getLastRequestData();
        $this->assertEquals('after-publish', $data['options']['headers'][ApiClient::HEADER_PURGE_REASON]);
        $this->assertEquals($sitetree->AbsoluteLink(), $data['options']['json']['files'][0]);
    }

    public function testPurgeOnPublishRecursive() {
        $sitetree = $this->objFromFixture(SiteTree::class, 'page-1');
        $sitetree->publishRecursive();
        $data = MockApiClient::getLastRequestData();
        $this->assertEquals('after-publishrecursive', $data['options']['headers'][ApiClient::HEADER_PURGE_REASON]);
        $this->assertEquals($sitetree->AbsoluteLink(), $data['options']['json']['files'][0]);
    }

    public function testPurgeOnUnpublish() {
        $sitetree = $this->objFromFixture(SiteTree::class, 'page-1');
        $sitetree->publishSingle();// need to have a published version in order to unpublish
        // grab the link while the file is still published
        $absoluteLink = $sitetree->AbsoluteLink();
        $sitetree->doUnpublish();
        $data = MockApiClient::getLastRequestData();
        $this->assertEquals('before-unpublish', $data['options']['headers'][ApiClient::HEADER_PURGE_REASON]);
        $this->assertEquals($absoluteLink, $data['options']['json']['files'][0]);
    }

}
