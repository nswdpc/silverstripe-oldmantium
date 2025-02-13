<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use Silverstripe\ORM\DataExtension;
use SilverStripe\Versioned\Versioned;

/**
 * SiteTree purge handling
 * @author James
 */
class SiteTreeExtension extends DataExtension {

    /**
     * Purge the owner record URL on publish
     */
    public function onAfterPublish()
    {
        if (!Config::inst()->get(CloudflarePurgeService::class, 'enabled') ) {
            return;
        }
        $result = Injector::inst()->get(CloudflarePurgeService::class)->purgeRecord($this->getOwner(), [ApiClient::HEADER_PURGE_REASON => 'after-publish']);
    }

    /**
     * Purge the owner record URL on publish recursive
     * Note that it's possible that onAfterPublish is also hit in the same process.
     */
    public function onAfterPublishRecursive()
    {
        if (!Config::inst()->get(CloudflarePurgeService::class, 'enabled') ) {
            return;
        }
        $result = Injector::inst()->get(CloudflarePurgeService::class)->purgeRecord($this->getOwner(), [ApiClient::HEADER_PURGE_REASON => 'after-publishrecursive']);
    }

    /**
     * Purge the owner record URL on unpublish
     * This needs to be done prior to the delete action to ensure the AbsoluteLink
     * matches the cached URL at the time of unpublish
     */
    public function onBeforeUnpublish()
    {
        if (!Config::inst()->get(CloudflarePurgeService::class, 'enabled') ) {
            return;
        }
        $result = Injector::inst()->get(CloudflarePurgeService::class)->purgeRecord($this->getOwner(), [ApiClient::HEADER_PURGE_REASON => 'before-unpublish']);
    }
}
