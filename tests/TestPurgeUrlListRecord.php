<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use NSWDPC\Utilities\Cloudflare\DataObjectPurgeable;
use SilverStripe\Dev\TestOnly;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class TestPurgeUrlListRecord extends DataObject implements TestOnly
{
    private static array $db = [
        'Title' => 'Varchar(255)'
    ];

    private static string $table_name = "TestPurgeUrlListRecord";

    public function AbsoluteLink(): string {
        return "https://example.com/TestPurgeUrlListRecord.html";
    }

    public function SomeRelatedLink(): string {
        return "https://example.com/TestPurgeUrlListRecord.html?alternateformat=1";
    }

    public function SomeReadingModeLink(): string {
        return "https://example.com/TestPurgeUrlListRecord.html?stage=Stage&format=html";
    }

    public function getPurgeUrlList(): array {
        return [
            $this->AbsoluteLink(),
            $this->SomeRelatedLink(),
            $this->SomeReadingModeLink()
        ];
    }

}
