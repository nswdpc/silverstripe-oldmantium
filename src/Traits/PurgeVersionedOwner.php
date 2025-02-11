<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;

/**
 * Apply this trait to any extension of an object that purges after publishing
 */
trait PurgeVersionedOwner {

    /**
     * Purge the owner record URL on publish
     */
    public function onAfterPublish()
    {
        Logger::log('onAfterPublish Purge ' . (get_class($this->getOwner())), 'INFO');
        if (!Config::inst()->get(CloudflarePurgeService::class, 'enabled') ) {
            return;
        }
        $result = Injector::inst()->get(CloudflarePurgeService::class)->purgeRecord($this->getOwner());
    }

    /**
     * Purge the owner record URL on publish recursive
     * Note that it's possible that onAfterPublish is also hit in the same action, leading to two requests
     * to the Cloudflare API
     */
    public function onAfterPublishRecursive()
    {
        Logger::log('onAfterPublishRecursive Purge ' . (get_class($this->getOwner())), 'INFO');
        if (!Config::inst()->get(CloudflarePurgeService::class, 'enabled') ) {
            return;
        }
        $result = Injector::inst()->get(CloudflarePurgeService::class)->purgeRecord($this->getOwner());
    }

    /**
     * Purge the owner record URL on unpublish
     */
    public function onAfterUnpublish()
    {
        Logger::log('onAfterUnpublish Purge ' . (get_class($this->getOwner())), 'INFO');
        if (!Config::inst()->get(CloudflarePurgeService::class, 'enabled') ) {
            return;
        }
        $result = Injector::inst()->get(CloudflarePurgeService::class)->purgeRecord($this->getOwner());
    }

}
