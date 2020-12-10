<?php

namespace NSWDPC\Utilities\Cloudflare;

use gorriecoe\LinkField\LinkField;
use gorriecoe\Link\Models\Link;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use Symbiote\Cloudflare\Cloudflare;
use Symbiote\MultiValueField\Fields\MultiValueListField;
use Symbiote\MultiValueField\Fields\MultiValueTextField;
use Symbiote\MultiValueField\Fields\MultiValueCheckboxField;

/**
 * A PurgeRecord
 * Note: requires a Cloudflare Enterprise account
 * @link DataObjectPurgeable
 * @author james.ellis@dpc.nsw.gov.au
 */

class PurgeRecord extends DataObject implements PermissionProvider {

    private static $table_name = 'CloudflarePurgeRecord';

    private static $singular_name = 'Cloudflare purge record';
    private static $plural_name = 'Cloudflare purge records';

    private static $db = [
        'Type' => 'Varchar(8)',
        'TypeValues' => 'MultiValueField'
    ];

    private static $summary_fields = [
        'Type' => 'Type',
        'TypeValues.csv' => 'Values',
    ];

    public function getTitle() {
        $values = [];
        if($this->Type) {
            $values = $this->getPurgeTypeValues($this->Type);
        }
        $title =$this->Type;
        if(is_array($values)) {
            $title .= " - " . implode(",", $values);
        }
        return $title;
    }

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
            )->setEmptyString('')->setDescription(
                _t(
                    __CLASS__ .'.CHANGE_TYPE_REMOVES_VALUES',
                    "Changing this value will remove currently saved 'Values' entries"
                )
            ),
            MultiValueTextField::create(
                'TypeValues',
                _t(__CLASS__ . '.TYPE_VALUES', 'Values')
            )->setDescription(
                _t(
                    __CLASS__ .'.ADD_PATHS_OR_URLS',
                    "Add paths or URLs in the currently configured zone"
                )
            )
        ]);
        return $fields;
    }

    /**
     * This instance of PurgeRecord only has the configured type
     * @return array
     */
    public function getPurgeTypes() : array  {
        if($this->Type) {
            return [
                $this->Type
            ];
        }
        return [];
    }

    /**
     * Get the type values that need to be purged
     * @return array
     */
    public function getPurgeTypeValues($type) : array {
        if($type == $this->Type && ($type_values = $this->getField('TypeValues'))) {
            return $this->TypeValues->getValue();
        }
        return [];
    }

    /**
     * Get the record name for identification in the queuedjob
     */
    public function getPurgeRecordName() : string {
        return 'PurgeRecord';
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if($this->exists() && $this->isChanged('Type')) {
            Logger::log("Cloudflare: remove values as type changed in PurgeRecord");
            $this->TypeValues = '';
        }

    }

    public function canView($member = null)
    {
        return Permission::checkMember($member, 'CLOUDFLARE_PURGERECORD_VIEW');
    }

    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CLOUDFLARE_PURGERECORD_CREATE');
    }

    public function canEdit($member = null)
    {
        return Permission::checkMember($member, 'CLOUDFLARE_PURGERECORD_EDIT');
    }

    public function canDelete($member = null)
    {
        return Permission::checkMember($member, 'CLOUDFLARE_PURGERECORD_DELETE');
    }

    public function providePermissions()
    {
        return [
            'CLOUDFLARE_PURGERECORD_VIEW' => [
                'name' => 'View Cloudflare purge record',
                'category' => 'Cloudflare',
            ],
            'CLOUDFLARE_PURGERECORD_EDIT' => [
                'name' => 'Edit Cloudflare purge record',
                'category' => 'Cloudflare',
            ],
            'CLOUDFLARE_PURGERECORD_CREATE' => [
                'name' => 'Create Cloudflare purge record',
                'category' => 'Cloudflare',
            ],
            'CLOUDFLARE_PURGERECORD_DELETE' => [
                'name' => 'Delete Cloudflare purge record',
                'category' => 'Cloudflare',
            ]
        ];
    }


}
