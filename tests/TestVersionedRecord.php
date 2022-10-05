<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use NSWDPC\Utilities\Cloudflare\DataObjectPurgeable;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class TestVersionedRecord extends DataObject implements TestOnly
{
    private static $db = [
        'Title' => 'Varchar(255)'
    ];

    private static $table_name = "TestVersionedRecord";

    private static $extensions = [
        Versioned::class,
        DataObjectPurgeable::class
    ];

    public function AbsoluteLink() {
        return "https://example.com/testversionedrecord.html";
    }

    public function SomeRelatedLink() {
        return "https://example.com/testversionedrecord.html?alternateformat=1";
    }

    public function SomeReadingModeLink() {
        return "https://example.com/testversionedrecord.html?stage=Stage&format=html";
    }

    public function getPurgeUrlList() {
        return [
            $this->AbsoluteLink(),
            $this->SomeRelatedLink(),
            $this->SomeReadingModeLink()
        ];
    }

    /**
     * This record has a URL that is support
     */
    public function getPurgeTypes() : array {
        return [
            CloudflarePurgeService::TYPE_URL
        ];
    }

}
