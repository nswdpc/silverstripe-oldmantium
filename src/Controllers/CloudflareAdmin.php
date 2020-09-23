<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Admin\ModelAdmin;
use Symbiote\Cloudflare\Cloudflare;

/**
 * Admin for managing Cloudflare purgeable urls
 * @author james.ellis@dpc.nsw.gov.au
 */
class CloudflareAdmin extends ModelAdmin
{
    private static $url_segment = 'cloudflare';
    private static $menu_title = 'Cloudflare';
    private static $menu_icon_class = 'font-icon-globe';
    private static $managed_models = [
        URL::class
    ];
}
