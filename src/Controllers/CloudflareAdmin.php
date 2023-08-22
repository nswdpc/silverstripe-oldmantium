<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Admin\ModelAdmin;

/**
 * Admin for managing records linked to Cloudflare support
 * @author James
 */
class CloudflareAdmin extends ModelAdmin
{

    /**
     * @inheritdoc
     */
    private static $url_segment = 'cloudflare';

    /**
     * @inheritdoc
     */
    private static $menu_title = 'Cloudflare';

    /**
     * @inheritdoc
     */
    private static $menu_icon_class = 'font-icon-globe-1';

    /**
     * @inheritdoc
     */
    private static $managed_models = [
        PurgeRecord::class
    ];

}
