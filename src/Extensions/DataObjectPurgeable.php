<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\Forms\NumericField;
use Silverstripe\ORM\DataExtension;
use SilverStripe\Versioned\Versioned;
use Symbiote\Cloudflare\CloudflareResult;
use Symbiote\Cloudflare\Cloudflare;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;

/**
 * Extension that decorates a purgeable dataobject, currently support URLs only
 * @author james.ellis@dpc.nsw.gov.au
 */

class DataObjectPurgeable extends DataExtension {

    private static $cache_max_age = 0;

    private static $db = [
        'CachePurgeAt' => 'Datetime', // add ability to purge dataobject at a certain date / time
        'CacheMaxAge' => 'Int'// seconds
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldsToTab(
            'Root.Cloudflare', [
                DatetimeField::create(
                    'CachePurgeAt',
                    _t(__CLASS__ . '.CACHE_PURGE_AT', 'Purge record URL at this date and time (note: UTC)')
                ),
                NumericField::create(
                    'CacheMaxAge',
                    _t(__CLASS__ . '.CACHE_MAX_AGE', 'Cache maximum age (seconds)')
                )->setDescription(
                    _t(__CLASS__ . '.CACHE_MAX_AGE_DESCRIPTION', 'Record URL(s) will be purged at this interval (seconds)')
                )->setRightTitle(
                    _t(__CLASS__ . '.CACHE_MAX_AGE_LEAVE_ZERO', 'Leave empty for no regular purge')
                )
            ]
        );
    }

    /**
     * Only the record knows which types to return
     * @return array
     */
    public function getPurgeTypes() {
        if($this->Type) {
            return [
                CloudflarePurgeService::TYPE_URL
            ];
        }
        return [];
    }

    /**
     * Only the record knows which values to return for the given type
     * For the moment, return URLs that can be purged
     * @return array
     */
    public function getPurgeTypeValues($type) {
        $values = [];
        switch($type) {
            case CloudflarePurgeService::TYPE_URL:
                if ($this->owner->hasMethod('getPurgeUrlList')) {
                    // the record can specify its own list of URLs to purge
                    $values = $this->owner->getPurgeUrlList();
                } elseif ($this->owner->hasMethod('AbsoluteLink')) {
                    // otherwise use the URL of the record, provided by the record
                    $values[] = $this->owner->AbsoluteLink();
                }
                break;
        }
        return $values;
    }

    /**
     * After publish, create any purge jobs that should be fired for the 'publish' reason
     * For versioned records when they are published
     */
    public function onAfterPublish()
    {
        if ($this->owner->hasExtension(Versioned::class)) {
            $this->owner->createPurgeJobs('publish');
        }
    }

    /**
     * After write, create any purge jobs that should be fired for the 'write' reason
     * For non-versioned records when they are written
     */
    public function onAfterWrite()
    {
        if (!$this->owner->hasExtension(Versioned::class)) {
            Logger::log("Cloudflare: creating jobs for reason=write");
            $this->owner->createPurgeJobs('write');
        }
    }

    /**
     * After unpublish, create any purge jobs that should be fired for the 'unpublish' reason
     * For versioned records when the Live stage record is removed
     */
    public function onAfterUnpublish()
    {
        if ($this->owner->hasExtension(Versioned::class)) {
            $this->owner->createPurgeJobs('unpublish');
        }
    }

    /**
     * After delete, create any purge jobs that should be fired for the 'delete' reason
     * For non-versioned records when the record is deleted
     */
    public function onAfterDelete()
    {
        if (!$this->owner->hasExtension(Versioned::class)) {
            $this->owner->createPurgeJobs('delete');
        }
    }

}
