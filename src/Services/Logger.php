<?php

namespace NSWDPC\Utilities\Cloudflare;

use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Security\Security;

/**
 * Shorthand logging helper class
 * @author James
 */
class Logger
{
    public static function log($message, $level = "DEBUG")
    {
        Injector::inst()->get(LoggerInterface::class)->log($level, $message);
    }
}
