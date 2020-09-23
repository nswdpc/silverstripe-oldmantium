<?php

namespace NSWDPC\Utilities\Cloudflare;

use gorriecoe\LinkField\LinkField;
use gorriecoe\Link\Models\Link;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Security\PermissionProvider;

/**
 * A PurgeRecord
 * Note: requires a Cloudflare Enterprise account
 * @link DataObjectPurgeable
 * @author james.ellis@dpc.nsw.gov.au
 */

class PurgeRecord extends DataObject implements Purgeable, PermissionProvider {

    use PurgeJob;

    private static $table_name = 'CloudflarePurgeRecord';

    private static $singular_name = 'Cloudflare purge record';
    private static $plural_name = 'Cloudflare purge records';

    private static $db = [
        'Type' => 'Varchar(8)',
        'TypeValues' => 'MultiValueField'
    ];

    /**
     * Get available types to select from in the administration screen
     * @return array
     */
    public function getTypes() {
        $types = [
            CloudflarePurgeService::TYPE_HOST,
            CloudflarePurgeService::TYPE_PREFIX,
            CloudflarePurgeService::TYPE_URL,
            CloudflarePurgeService::TYPE_TAG
        ];
        $result = [];
        foreach($types as $type) {
            $result[ $type ] = _t(__CLASS__ . '.TYPE_' . strtoupper($type), $type);
        }
        return $result;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldsToTab(
            'Root.Main', [
            DropdownField::create(
                'Type',
                _t(__CLASS__ . '.TYPE', 'Type'),
                $this->getTypes()
            )->setEmptyString(''),
            MultiValueListField::create(
                'TypeValues',
                _t(__CLASS__ . '.TYPE_VALUES', 'Values')
            )
        ]);
        return $fields;
    }

    /**
     * PurgeRecord only has the configured type
     * @return array
     */
    public function getPurgeTypes() {
        if($this->Type) {
            return [
                $this->Type
            ];
        }
        return [];
    }

    /**
     * @return array
     */
    public function getPurgeTypeValues($type) {
        if($type == $this->Type) {
            return $this->TypeValues;
        }
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if($this->isChanged('CacheMaxAge')
            || $this->isChanged('CachePurgeAt')
            || $this->isChanged('URL') // (if the URL is changed !)
        ) {
            // handle options
            // first delete any jobs that are active

            // if there is no URL don't add a new job

            // if the CachePurgeAt is changed, create a job at that date

            // if the CacheMaxAge is changed, create a job immediately
        }
    }


}
