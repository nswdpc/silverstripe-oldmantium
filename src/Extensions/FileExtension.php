<?php

namespace NSWDPC\Utilities\Cloudflare;

use Silverstripe\ORM\DataExtension;

/**
 * File purge handling
 * @author James
 */

class FileExtension extends DataExtension {

    use PurgeVersionedOwner;

}
