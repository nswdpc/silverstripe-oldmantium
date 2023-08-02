<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use Silverstripe\ORM\DataExtension;

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
        $result = Injector::inst()->get(CloudflarePurgeService::class)->purgePage($this->owner);
    }

    /**
     * Purge the owner record URL on unpublish
     */
    public function onAfterUnpublish()
    {
        if (!Config::inst()->get(CloudflarePurgeService::class, 'enabled') ) {
            return;
        }
        $result = Injector::inst()->get(CloudflarePurgeService::class)->purgePage($this->owner);
    }

}
