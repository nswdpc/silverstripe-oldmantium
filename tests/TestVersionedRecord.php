<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use NSWDPC\Utilities\Cloudflare\DataObjectPurgeable;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class TestVersionedRecord extends DataObject implements TestOnly
{
    private static array $db = [
        'Title' => 'Varchar(255)'
    ];

    private static string $table_name = "TestVersionedRecord";

    private static array $extensions = [
        Versioned::class,
        DataObjectPurgeable::class
    ];

    public function AbsoluteLink(): string
    {
        return "https://example.com/testversionedrecord.html";
    }

    public function SomeRelatedLink(): string
    {
        return "https://example.com/testversionedrecord.html?alternateformat=1";
    }

    public function SomeReadingModeLink(): string
    {
        return "https://example.com/testversionedrecord.html?stage=Stage&format=html";
    }

    public function getPurgeUrlList(): array
    {
        return [
            $this->AbsoluteLink(),
            $this->SomeRelatedLink(),
            $this->SomeReadingModeLink()
        ];
    }

    /**
     * This record has a URL that is support
     */
    public function getPurgeTypes(): array
    {
        return [
            CloudflarePurgeService::TYPE_URL
        ];
    }

}
