<?php

namespace NSWDPC\Utilities\Cloudflare;

use Silverstripe\ORM\DataExtension;

/**
 * SiteTree purge handling
 * @author James
 */

class SiteTreeExtension extends DataExtension {

    use PurgeVersionedOwner;

}
