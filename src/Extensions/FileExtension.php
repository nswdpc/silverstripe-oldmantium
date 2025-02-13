<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use Silverstripe\ORM\DataExtension;
use SilverStripe\Versioned\Versioned;

/**
 * File purge handling
 * As files can have different URLs depending on their stage, this hooks into
 * onAfterWrite() on onBeforeDelete()
 * @author James
 */

class FileExtension extends DataExtension {

    /**
     * Purge the owner record URL after write
     * File records have a different URL depending on the stage they are on.
     * Publishing an unpublished file purges both the public URL and the draft URL
     */
    public function onAfterWrite()
    {
        if (!Config::inst()->get(CloudflarePurgeService::class, 'enabled') ) {
            return;
        }
        $result = Injector::inst()->get(CloudflarePurgeService::class)->purgeRecord($this->getOwner(), [ApiClient::HEADER_PURGE_REASON => 'after-write']);
    }

    /**
     * Purge the owner record on delete
     * This request needs to be sent prior to the delete action to ensure the AbsoluteLink
     * matches the cached URL at the time of delete
     * File records have a different URL depending on the stage they are on.
     * Deleting a published file purges both the public URL and the draft URL
     */
    public function onBeforeDelete()
    {
        if (!Config::inst()->get(CloudflarePurgeService::class, 'enabled') ) {
            return;
        }
        $result = Injector::inst()->get(CloudflarePurgeService::class)->purgeRecord($this->getOwner(), [ApiClient::HEADER_PURGE_REASON => 'before-delete']);
    }
}
