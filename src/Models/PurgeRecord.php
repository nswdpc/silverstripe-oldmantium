<?php

namespace NSWDPC\Utilities\Cloudflare;

use gorriecoe\LinkField\LinkField;
use gorriecoe\Link\Models\Link;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use Symbiote\MultiValueField\Fields\MultiValueListField;
use Symbiote\MultiValueField\Fields\MultiValueTextField;
use Symbiote\MultiValueField\Fields\MultiValueCheckboxField;
use Symbiote\MultiValueField\ORM\FieldType\MultiValueField;

/**
 * A PurgeRecord
 * {@link NSWDPC\Utilities\Cloudflare\DataObjectPurgeable} provides event handling for this class
 * @author James
 */

class PurgeRecord extends DataObject implements PermissionProvider {

    /**
     * @inheritdoc
     */
    private static $table_name = 'CloudflarePurgeRecord';

    /**
     * @inheritdoc
     */
    private static $singular_name = 'Cloudflare purge record';

    /**
     * @inheritdoc
     */
    private static $plural_name = 'Cloudflare purge records';

    /**
     * @inheritdoc
     */
    private static $db = [
        'Title' => 'Varchar(255)',
        'Type' => 'Varchar(16)',
        'TypeValues' => 'MultiValueField'
    ];

    /**
     * @inheritdoc
     */
    private static $summary_fields = [
        'Title' => 'Title',
        'TypeString' => 'Type',
        'TypeValues.csv' => 'Values',
    ];

    /**
     * Get available types to select from in the administration screen
     * The values of these types map to *CachePurgeJob class names
     * @return array
     */
    public function getTypes() {
        $types = [
            CloudflarePurgeService::TYPE_HOST,
            CloudflarePurgeService::TYPE_PREFIX,
            CloudflarePurgeService::TYPE_URL,
            CloudflarePurgeService::TYPE_TAG,
            CloudflarePurgeService::TYPE_FILE_EXTENSION,// @deprecated
            CloudflarePurgeService::TYPE_IMAGE,// @deprecated
            CloudflarePurgeService::TYPE_CSS_JAVASCRIPT,// @deprecated
        ];
        $result = [];
        foreach($types as $type) {
            $result[ $type ] = $this->getTypeString($type);
        }
        return $result;
    }

    /**
     * Helper method to get translated version of Type value
     * @return string
     */
    public function getTypeString($type = null) : string {
        $type = $type ?: $this->Type;
        if(!$type) {
            return _t(__CLASS__ . '.UNKNOWN', 'Unknown');
        } else {
            return _t(__CLASS__ . '.TYPE_' . strtoupper($type), $type);
        }
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
        if($type == $this->Type) {
            try {
                $items = $this->TypeValues;
                if($items instanceof MultiValueField) {
                    $items = $items->getValue();
                }
                if(is_array($items)) {
                    return $items;
                }
            } catch (\Exception $e) {
                // log a notice
            }
        }
        return [];
    }

    /**
     * Get the record name for identification in the queuedjob
     */
    public function getPurgeRecordName() : string {
        return AbstractRecordCachePurgeJob::RECORD_NAME;
    }

    /**
     * Retrict types that require values
     */
    public function requiresTypeValue() : bool {
        switch($this->Type) {
            case CloudflarePurgeService::TYPE_IMAGE:
            case CloudflarePurgeService::TYPE_CSS_JAVASCRIPT:
                return false;
                break;
            default:
                return true;
                break;
        }
    }

    /**
     * Actions to preform pre-write
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if($this->exists() && $this->isChanged('Type')) {
            $this->TypeValues = null;
        }

        $values = $this->getPurgeTypeValues( $this->Type );

        if(count($values) == 0 && $this->requiresTypeValue()) {
            throw new ValidationException(
                _t(__CLASS__ . '.PROVIDE_VALUES', 'Please provide one or more values')
            );
        }

        if($this->Type == CloudflarePurgeService::TYPE_URL) {
            if(is_array($values)) {
                foreach($values as $i => $value) {
                    $values[$i] = Director::absoluteURL($value);
                }
            }
            $this->TypeValues = $values;
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
                'name' => _t(__CLASS__ . '.PERMISSION_VIEW', 'View Cloudflare purge records'),
                'category' => 'Cloudflare',
            ],
            'CLOUDFLARE_PURGERECORD_EDIT' => [
                'name' => _t(__CLASS__ . '.PERMISSION_EDIT', 'Edit Cloudflare purge records'),
                'category' => 'Cloudflare',
            ],
            'CLOUDFLARE_PURGERECORD_CREATE' => [
                'name' => _t(__CLASS__ . '.PERMISSION_CREATE', 'Create Cloudflare purge records'),
                'category' => 'Cloudflare',
            ],
            'CLOUDFLARE_PURGERECORD_DELETE' => [
                'name' => _t(__CLASS__ . '.PERMISSION_DELETE', 'Delete Cloudflare purge records'),
                'category' => 'Cloudflare',
            ]
        ];
    }

}
