<?php

declare(strict_types=1);

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
    private static string $url_segment = 'cloudflare';

    /**
     * @inheritdoc
     */
    private static string $menu_title = 'Cloudflare';

    /**
     * @inheritdoc
     */
    private static string $menu_icon_class = 'font-icon-globe-1';

    /**
     * @inheritdoc
     */
    private static array $managed_models = [
        PurgeRecord::class
    ];

}
