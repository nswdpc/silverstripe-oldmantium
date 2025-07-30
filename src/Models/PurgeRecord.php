<?php

namespace NSWDPC\Utilities\Cloudflare;

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
 * @property string $Title
 * @property ?string $Type
 * @property mixed $TypeValues
 * @mixin \NSWDPC\Utilities\Cloudflare\DataObjectPurgeable
 * @mixin \Silverstripe\Versioned\Versioned
 */
class PurgeRecord extends DataObject implements PermissionProvider {

    /**
     * @inheritdoc
     */
    private static string $table_name = 'CloudflarePurgeRecord';

    /**
     * @inheritdoc
     */
    private static string $singular_name = 'Cloudflare purge record';

    /**
     * @inheritdoc
     */
    private static string $plural_name = 'Cloudflare purge records';

    /**
     * @inheritdoc
     */
    private static array $db = [
        'Title' => 'Varchar(255)',
        'Type' => 'Varchar(16)',
        'TypeValues' => 'MultiValueField'
    ];

    /**
     * @inheritdoc
     */
    private static array $summary_fields = [
        'Title' => 'Title',
        'TypeString' => 'Type',
        'TypeValuesSummary' => 'Values',
    ];

    /**
     * Return TypeValues values as a string
     */
    public function getTypeValuesSummary(): string {
        return $this->dbObject('TypeValues')->Implode();
    }

    /**
     * Get available types to select from in the administration screen
     * The values of these types map to *CachePurgeJob class names
     */
    public function getTypes(): array {
        $types = [
            CloudflarePurgeService::TYPE_HOST,
            CloudflarePurgeService::TYPE_PREFIX,
            CloudflarePurgeService::TYPE_URL,
            CloudflarePurgeService::TYPE_TAG
        ];
        $result = [];
        foreach($types as $type) {
            $result[ $type ] = $this->getTypeString($type);
        }

        return $result;
    }

    /**
     * Helper method to get translated version of Type value
     */
    public function getTypeString($type = null) : string {
        $type = $type ?: $this->Type;
        if(!$type) {
            return _t(self::class . '.UNKNOWN', 'Unknown');
        } else {
            return _t(self::class . '.TYPE_' . strtoupper((string) $type), $type);
        }
    }

    #[\Override]
    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->addFieldsToTab(
            'Root.Main', [
            DropdownField::create(
                'Type',
                _t(self::class . '.TYPE', 'Type'),
                $this->getTypes()
            )->setEmptyString('')->setDescription(
                _t(
                    self::class .'.CHANGE_TYPE_REMOVES_VALUES',
                    "Changing this value will remove currently saved 'Values' entries"
                )
            ),
            MultiValueTextField::create(
                'TypeValues',
                _t(self::class . '.TYPE_VALUES', 'Values')
            )->setDescription(
                _t(
                    self::class .'.ADD_PATHS_OR_URLS',
                    "Add paths or URLs in the currently configured zone"
                )
            )
        ]);
        return $fields;
    }

    /**
     * This instance of PurgeRecord only has the configured type
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
            } catch (\Exception) {
                // log a notice
            }
        }

        return [];
    }

    /**
     * Clear related jobs when this record is unpublished
     */
    public function clearPurgeJobsOnUnPublish() : bool {
        return true;
    }

    /**
     * Retrict types that require values
     */
    public function requiresTypeValue() : bool {
        return true;
    }

    /**
     * Actions to preform pre-write
     */
    #[\Override]
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if($this->exists() && $this->isChanged('Type')) {
            $this->TypeValues = null;
        }

        $values = $this->getPurgeTypeValues( $this->Type );

        if(count($values) == 0 && $this->requiresTypeValue()) {
            throw ValidationException::create(
                _t(self::class . '.PROVIDE_VALUES', 'Please provide one or more values')
            );
        }

        if($this->Type == CloudflarePurgeService::TYPE_URL) {
            foreach($values as $i => $value) {
                $values[$i] = Director::absoluteURL($value);
            }

            $this->TypeValues = $values;
        }
    }

    #[\Override]
    public function canView($member = null)
    {
        return Permission::checkMember($member, 'CLOUDFLARE_PURGERECORD_VIEW');
    }

    #[\Override]
    public function canCreate($member = null, $context = [])
    {
        return Permission::checkMember($member, 'CLOUDFLARE_PURGERECORD_CREATE');
    }

    #[\Override]
    public function canEdit($member = null)
    {
        return Permission::checkMember($member, 'CLOUDFLARE_PURGERECORD_EDIT');
    }

    #[\Override]
    public function canDelete($member = null)
    {
        return Permission::checkMember($member, 'CLOUDFLARE_PURGERECORD_DELETE');
    }

    public function providePermissions()
    {
        return [
            'CLOUDFLARE_PURGERECORD_VIEW' => [
                'name' => _t(self::class . '.PERMISSION_VIEW', 'View Cloudflare purge records'),
                'category' => 'Cloudflare',
            ],
            'CLOUDFLARE_PURGERECORD_EDIT' => [
                'name' => _t(self::class . '.PERMISSION_EDIT', 'Edit Cloudflare purge records'),
                'category' => 'Cloudflare',
            ],
            'CLOUDFLARE_PURGERECORD_CREATE' => [
                'name' => _t(self::class . '.PERMISSION_CREATE', 'Create Cloudflare purge records'),
                'category' => 'Cloudflare',
            ],
            'CLOUDFLARE_PURGERECORD_DELETE' => [
                'name' => _t(self::class . '.PERMISSION_DELETE', 'Delete Cloudflare purge records'),
                'category' => 'Cloudflare',
            ]
        ];
    }

}
