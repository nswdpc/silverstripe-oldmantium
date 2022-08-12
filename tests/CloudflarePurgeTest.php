<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\DataObjectPurgeable;
use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use NSWDPC\Utilities\Cloudflare\Logger;
use NSWDPC\Utilities\Cloudflare\PurgeRecord;
use NSWDPC\Utilities\Cloudflare\URLCachePurgeJob;
use NSWDPC\Utilities\Cloudflare\HostCachePurgeJob;
use NSWDPC\Utilities\Cloudflare\EntireCachePurgeJob;
use NSWDPC\Utilities\Cloudflare\PrefixCachePurgeJob;
use NSWDPC\Utilities\Cloudflare\TagCachePurgeJob;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Versioned\Versioned;
use Symbiote\Cloudflare\Cloudflare;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Test functionality provided by the module
 * Note: requires a service to accept HTTP requests
 * @author James
 */
class CloudflarePurgeTest extends SapphireTest
{

    protected $usesDatabase = true;

    protected $client;

    protected static $extra_dataobjects = [
        TestVersionedRecord::class,
        PurgeRecord::class
    ];

    protected static $required_extensions = [
        TestVersionedRecord::class => [
            Versioned::class,
            DataObjectPurgeable::class
        ],
        PurgeRecord::class => [
            Versioned::class,
            DataObjectPurgeable::class
        ]
    ];

    public function setUp() : void {
        parent::setUp();

        // Mock a CloudflarePurgeService
        Injector::inst()->load([
            Cloudflare::class => [
                'class' => MockCloudflarePurgeService::class,
            ]
        ]);

        $this->client = Injector::inst()->get( Cloudflare::class );

        $this->assertTrue($this->client instanceof MockCloudflarePurgeService, "Client is not a MockCloudflarePurgeService");

        QueuedJobService::config()->set('use_shutdown_function', false);

    }

    /**
     * @return QueuedJobService
     */
    protected function getQueuedJobService()
    {
        return singleton(QueuedJobService::class);
    }

    public function testPurgeRecordURL() {

        $purge = PurgeRecord::create([
            'Title' => 'Purge URLs',
            'Type' => CloudflarePurgeService::TYPE_URL,
            'TypeValues'  => [
                'https://example.com',
                '/foo/bar'
            ]
        ]);

        $purge->write();
        $purge->doPublish();

        $values = $purge->TypeValues;

        // test that a job was created for this record
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ URLCachePurgeJob::class ] );
        $this->assertEquals(1, $descriptors->count(), "Jobs count should be 1");

        $descriptor = $descriptors->first();

        $job_data = unserialize($descriptor->SavedJobData);
        $this->assertEquals(DataObjectPurgeable::REASON_PUBLISH, $job_data->reason);

        $job = Injector::inst()->createWithArgs(
                $descriptor->Implementation,
                [
                    DataObjectPurgeable::REASON_PUBLISH,
                    $purge
                ]
        );

        $job->setup();
        $job->process();

        // check data
        $data = $this->client->getAdapter()->getData();
        $headers = $this->client->getAdapter()->getHeaders();
        $client_headers = $this->client->getAdapter()->getClientHeaders();
        $uri = $this->client->getAdapter()->getLastUri();

        $this->assertTrue(isset($data['files']), "'files' does not exist in POST data");

        $keys = array_keys($data);
        $this->assertEquals(1, count($keys), "There should only be one key in the data, found: " . count($data));

        $values = $purge->getPurgeTypeValues( $purge->Type );
        $this->assertEquals($values, $data['files'], "Purged files sent in data does not match record getPurgeTypeValues");
        $this->assertEquals("zones/{$this->client->getZoneIdentifier()}/purge_cache", $uri, "URI mismatch");

        $this->assertEquals(
            [
                $this->client->config()->get('email'),
                $this->client->config()->get('auth_key')
            ],
            array_values($client_headers),
            "Client AUTH headers mismatch"
        );


        $purge->doUnpublish();

        // test that a job was created for this record reason = 'write'
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ URLCachePurgeJob::class ] );

        $this->assertEquals(1, $descriptors->count(), "Jobs count should be 1");

        $descriptor = $descriptors->first();

        $job_data = unserialize($descriptor->SavedJobData);
        $this->assertEquals(DataObjectPurgeable::REASON_UNPUBLISH, $job_data->reason);

        $job = Injector::inst()->createWithArgs(
                $descriptor->Implementation,
                [
                    DataObjectPurgeable::REASON_UNPUBLISH,
                    $purge
                ]
        );

        $job->setup();
        $job->process();

        // check data
        $data = $this->client->getAdapter()->getData();
        $headers = $this->client->getAdapter()->getHeaders();
        $client_headers = $this->client->getAdapter()->getClientHeaders();
        $uri = $this->client->getAdapter()->getLastUri();

        $this->assertTrue(isset($data['files']), "'files' does not exist in POST data");

        $keys = array_keys($data);
        $this->assertEquals(1, count($keys), "There should only be one key in the data, found: " . count($data));

        $values = $purge->getPurgeTypeValues( $purge->Type );
        $this->assertEquals($values, $data['files'], "Purged files sent in data does not match record getPurgeTypeValues");
        $this->assertEquals("zones/{$this->client->getZoneIdentifier()}/purge_cache", $uri, "URI mismatch");

        $this->assertEquals(
            [
                $this->client->config()->get('email'),
                $this->client->config()->get('auth_key')
            ],
            array_values($client_headers),
            "Client AUTH headers mismatch"
        );

        $purge->delete();

        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ URLCachePurgeJob::class ] );
        $this->assertEquals(0, $descriptors->count(), "Jobs count should be 0 after delete");

    }

    private function createAndPublish() {

        $record = TestVersionedRecord::create([
            'Title' => 'Test record write'
        ]);

        // test values are sane
        $types = $record->getPurgeTypes();
        $this->assertEquals(1, count($types), "getPurgeTypes count is not 1");
        $this->assertEquals(CloudflarePurgeService::TYPE_URL, $types[0], "type is not CloudflarePurgeService::TYPE_URL");

        $urls = $record->getPurgeUrlList();

        $this->assertEquals(2, count($urls), "getPurgeUrlList count is not 2");
        $this->assertTrue(array_search($record->AbsoluteLink(), $urls) !== false, "AbsoluteLink not found in getPurgeUrlList");
        $this->assertTrue(array_search($record->SomeRelatedLink(), $urls) !== false, "SomeRelatedLink not found in getPurgeUrlList");

        $record->write();
        $record->doPublish();

        // test that a job was created for this record
        $descriptors = $record->getCurrentPurgeJobDescriptors( [ URLCachePurgeJob::class ] );
        $this->assertEquals(1, $descriptors->count(), "Jobs count should be 1");

        $descriptor = $descriptors->first();

        $job_data = unserialize($descriptor->SavedJobData);
        $this->assertEquals(DataObjectPurgeable::REASON_PUBLISH, $job_data->reason);

        $job = Injector::inst()->createWithArgs(
                $descriptor->Implementation,
                [
                    DataObjectPurgeable::REASON_PUBLISH,
                    $record
                ]
        );

        $job->setup();
        $job->process();

        // check data
        $data = $this->client->getAdapter()->getData();
        $headers = $this->client->getAdapter()->getHeaders();
        $client_headers = $this->client->getAdapter()->getClientHeaders();
        $uri = $this->client->getAdapter()->getLastUri();

        $this->assertTrue(isset($data['files']), "'files' does not exist in POST data");

        $keys = array_keys($data);
        $this->assertEquals(1, count($keys), "There should only be one key in the data, found: " . count($data));

        $this->assertEquals($record->getPurgeUrlList(), $data['files'], "Purged files sent in data does not match record getPurgeUrlList");
        $this->assertEquals("zones/{$this->client->getZoneIdentifier()}/purge_cache", $uri, "URI mismatch");

        $this->assertEquals(
            [
                $this->client->config()->get('email'),
                $this->client->config()->get('auth_key')
            ],
            array_values($client_headers),
            "Client AUTH headers mismatch"
        );

        return $record;

    }


    public function testRecordPublish() {
        $this->createAndPublish();
    }


    public function testRecordUnpublish() {

        $record = $this->createAndPublish();

        $record->doUnPublish();

        // test that a job was created for this record reason = 'write'
        $descriptors = $record->getCurrentPurgeJobDescriptors( [ URLCachePurgeJob::class ] );

        $this->assertEquals(1, $descriptors->count(), "Jobs count should be 1");

        $descriptor = $descriptors->first();

        $job_data = unserialize($descriptor->SavedJobData);
        $this->assertEquals(DataObjectPurgeable::REASON_UNPUBLISH, $job_data->reason);

        $job = Injector::inst()->createWithArgs(
                $descriptor->Implementation,
                [
                    DataObjectPurgeable::REASON_UNPUBLISH,
                    $record
                ]
        );

        $job->setup();
        $job->process();

        // check data
        $data = $this->client->getAdapter()->getData();
        $headers = $this->client->getAdapter()->getHeaders();
        $client_headers = $this->client->getAdapter()->getClientHeaders();
        $uri = $this->client->getAdapter()->getLastUri();

        $this->assertTrue(isset($data['files']), "'files' does not exist in POST data");

        $keys = array_keys($data);
        $this->assertEquals(1, count($keys), "There should only be one key in the data, found: " . count($data));

        $this->assertEquals($record->getPurgeUrlList(), $data['files'], "Purged files sent in data does not match record getPurgeUrlList");
        $this->assertEquals("zones/{$this->client->getZoneIdentifier()}/purge_cache", $uri, "URI mismatch");

        $this->assertEquals(
            [
                $this->client->config()->get('email'),
                $this->client->config()->get('auth_key')
            ],
            array_values($client_headers),
            "Client AUTH headers mismatch"
        );

        $record->delete();

        $descriptors = $record->getCurrentPurgeJobDescriptors( [ URLCachePurgeJob::class ] );
        $this->assertEquals(0, $descriptors->count(), "Jobs count should be 0 after delete");

    }


    public function testPurgeRecordHost() {

        $hosts = [
            'www.example.com',
            'images.example.com'
        ];

        $purge = PurgeRecord::create([
            'Title' => 'Purge HOST',
            'Type' => CloudflarePurgeService::TYPE_HOST,
            'TypeValues'  => $hosts
        ]);

        $purge->write();
        $purge->doPublish();

        $values = $purge->getPurgeTypeValues($purge->Type);

        $this->assertEquals(count($hosts), count($values), "Host count mismatch");

        // test that a job was created for this record
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ HostCachePurgeJob::class ] );
        $this->assertEquals(1, $descriptors->count(), "Jobs count should be 1");

        $descriptor = $descriptors->first();

        $job_data = unserialize($descriptor->SavedJobData);
        $this->assertEquals(DataObjectPurgeable::REASON_PUBLISH, $job_data->reason);

        $job = Injector::inst()->createWithArgs(
                $descriptor->Implementation,
                [
                    DataObjectPurgeable::REASON_PUBLISH,
                    $purge
                ]
        );

        $job->setup();
        $job->process();

        // check data
        $data = $this->client->getAdapter()->getData();
        $headers = $this->client->getAdapter()->getHeaders();
        $client_headers = $this->client->getAdapter()->getClientHeaders();
        $uri = $this->client->getAdapter()->getLastUri();

        $this->assertTrue(isset($data['hosts']), "'hosts' does not exist in POST data");

        $keys = array_keys($data);
        $this->assertEquals(1, count($keys), "There should only be one key in the data, found: " . count($data));

        $values = $purge->getPurgeTypeValues( $purge->Type );
        $this->assertEquals($values, $data['hosts'], "Purged hosts sent in data does not match record getPurgeTypeValues");
        $this->assertEquals("zones/{$this->client->getZoneIdentifier()}/purge_cache", $uri, "URI mismatch");

        $this->assertEquals(
            [
                $this->client->config()->get('email'),
                $this->client->config()->get('auth_key')
            ],
            array_values($client_headers),
            "Client AUTH headers mismatch"
        );


        $purge->doUnpublish();

        // test that a job was created for this record reason = 'write'
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ HostCachePurgeJob::class ] );

        $this->assertEquals(1, $descriptors->count(), "Jobs count should be 1");

        $descriptor = $descriptors->first();

        $job_data = unserialize($descriptor->SavedJobData);
        $this->assertEquals(DataObjectPurgeable::REASON_UNPUBLISH, $job_data->reason);

        $job = Injector::inst()->createWithArgs(
                $descriptor->Implementation,
                [
                    DataObjectPurgeable::REASON_UNPUBLISH,
                    $purge
                ]
        );

        $job->setup();
        $job->process();

        // check data
        $data = $this->client->getAdapter()->getData();
        $headers = $this->client->getAdapter()->getHeaders();
        $client_headers = $this->client->getAdapter()->getClientHeaders();
        $uri = $this->client->getAdapter()->getLastUri();

        $this->assertTrue(isset($data['hosts']), "'hosts' does not exist in POST data");

        $keys = array_keys($data);
        $this->assertEquals(1, count($keys), "There should only be one key in the data, found: " . count($data));

        $values = $purge->getPurgeTypeValues( $purge->Type );
        $this->assertEquals($values, $data['hosts'], "Purged hosts sent in data does not match record getPurgeTypeValues");
        $this->assertEquals("zones/{$this->client->getZoneIdentifier()}/purge_cache", $uri, "URI mismatch");

        $this->assertEquals(
            [
                $this->client->config()->get('email'),
                $this->client->config()->get('auth_key')
            ],
            array_values($client_headers),
            "Client AUTH headers mismatch"
        );

        $purge->delete();

        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ HostCachePurgeJob::class ] );
        $this->assertEquals(0, $descriptors->count(), "Jobs count should be 0 after delete");

    }


    public function testPurgeRecordPrefix() {

        $prefixes = [
            'www.example.com/foo',
            'images.example.com/bar'
        ];

        $purge = PurgeRecord::create([
            'Title' => 'Purge PREFIX',
            'Type' => CloudflarePurgeService::TYPE_PREFIX,
            'TypeValues'  => $prefixes
        ]);

        $purge->write();
        $purge->doPublish();

        $values = $purge->getPurgeTypeValues($purge->Type);

        $this->assertEquals(count($prefixes), count($values), "Prefix count mismatch");

        // test that a job was created for this record
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ PrefixCachePurgeJob::class ] );
        $this->assertEquals(1, $descriptors->count(), "Jobs count should be 1");

        $descriptor = $descriptors->first();

        $job_data = unserialize($descriptor->SavedJobData);
        $this->assertEquals(DataObjectPurgeable::REASON_PUBLISH, $job_data->reason);

        $job = Injector::inst()->createWithArgs(
                $descriptor->Implementation,
                [
                    DataObjectPurgeable::REASON_PUBLISH,
                    $purge
                ]
        );

        $job->setup();
        $job->process();

        // check data
        $data = $this->client->getAdapter()->getData();
        $headers = $this->client->getAdapter()->getHeaders();
        $client_headers = $this->client->getAdapter()->getClientHeaders();
        $uri = $this->client->getAdapter()->getLastUri();

        $this->assertTrue(isset($data['prefixes']), "'prefixes' does not exist in POST data");

        $keys = array_keys($data);
        $this->assertEquals(1, count($keys), "There should only be one key in the data, found: " . count($data));

        $values = $purge->getPurgeTypeValues( $purge->Type );
        $this->assertEquals($values, $data['prefixes'], "Purged prefixes sent in data does not match record getPurgeTypeValues");
        $this->assertEquals("zones/{$this->client->getZoneIdentifier()}/purge_cache", $uri, "URI mismatch");

        $this->assertEquals(
            [
                $this->client->config()->get('email'),
                $this->client->config()->get('auth_key')
            ],
            array_values($client_headers),
            "Client AUTH headers mismatch"
        );


        $purge->doUnpublish();

        // test that a job was created for this record reason = 'write'
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ PrefixCachePurgeJob::class ] );

        $this->assertEquals(1, $descriptors->count(), "Jobs count should be 1");

        $descriptor = $descriptors->first();

        $job_data = unserialize($descriptor->SavedJobData);
        $this->assertEquals(DataObjectPurgeable::REASON_UNPUBLISH, $job_data->reason);

        $job = Injector::inst()->createWithArgs(
                $descriptor->Implementation,
                [
                    DataObjectPurgeable::REASON_UNPUBLISH,
                    $purge
                ]
        );

        $job->setup();
        $job->process();

        // check data
        $data = $this->client->getAdapter()->getData();
        $headers = $this->client->getAdapter()->getHeaders();
        $client_headers = $this->client->getAdapter()->getClientHeaders();
        $uri = $this->client->getAdapter()->getLastUri();

        $this->assertTrue(isset($data['prefixes']), "'prefixes' does not exist in POST data");

        $keys = array_keys($data);
        $this->assertEquals(1, count($keys), "There should only be one key in the data, found: " . count($data));

        $values = $purge->getPurgeTypeValues( $purge->Type );
        $this->assertEquals($values, $data['prefixes'], "Purged prefixes sent in data does not match record getPurgeTypeValues");
        $this->assertEquals("zones/{$this->client->getZoneIdentifier()}/purge_cache", $uri, "URI mismatch");

        $this->assertEquals(
            [
                $this->client->config()->get('email'),
                $this->client->config()->get('auth_key')
            ],
            array_values($client_headers),
            "Client AUTH headers mismatch"
        );

        $purge->delete();

        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ PrefixCachePurgeJob::class ] );
        $this->assertEquals(0, $descriptors->count(), "Jobs count should be 0 after delete");

    }

    public function testPurgeRecordTag() {

        $tags= [
            'foo',
            'bar',
            'tag-three'
        ];

        $purge = PurgeRecord::create([
            'Title' => 'Purge TAG',
            'Type' => CloudflarePurgeService::TYPE_TAG,
            'TypeValues'  => $tags
        ]);

        $purge->write();
        $purge->doPublish();

        $values = $purge->getPurgeTypeValues($purge->Type);

        $this->assertEquals(count($tags), count($values), "Prefix count mismatch");

        // test that a job was created for this record
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ TagCachePurgeJob::class ] );
        $this->assertEquals(1, $descriptors->count(), "Jobs count should be 1");

        $descriptor = $descriptors->first();

        $job_data = unserialize($descriptor->SavedJobData);
        $this->assertEquals(DataObjectPurgeable::REASON_PUBLISH, $job_data->reason);

        $job = Injector::inst()->createWithArgs(
                $descriptor->Implementation,
                [
                    DataObjectPurgeable::REASON_PUBLISH,
                    $purge
                ]
        );

        $job->setup();
        $job->process();

        // check data
        $data = $this->client->getAdapter()->getData();
        $headers = $this->client->getAdapter()->getHeaders();
        $client_headers = $this->client->getAdapter()->getClientHeaders();
        $uri = $this->client->getAdapter()->getLastUri();

        $this->assertTrue(isset($data['tags']), "'tags' does not exist in POST data");

        $keys = array_keys($data);
        $this->assertEquals(1, count($keys), "There should only be one key in the data, found: " . count($data));

        $values = $purge->getPurgeTypeValues( $purge->Type );
        $this->assertEquals($values, $data['tags'], "Purged tags sent in data does not match record getPurgeTypeValues");
        $this->assertEquals("zones/{$this->client->getZoneIdentifier()}/purge_cache", $uri, "URI mismatch");

        $this->assertEquals(
            [
                $this->client->config()->get('email'),
                $this->client->config()->get('auth_key')
            ],
            array_values($client_headers),
            "Client AUTH headers mismatch"
        );


        $purge->doUnpublish();

        // test that a job was created for this record reason = 'write'
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ TagCachePurgeJob::class ] );

        $this->assertEquals(1, $descriptors->count(), "Jobs count should be 1");

        $descriptor = $descriptors->first();

        $job_data = unserialize($descriptor->SavedJobData);
        $this->assertEquals(DataObjectPurgeable::REASON_UNPUBLISH, $job_data->reason);

        $job = Injector::inst()->createWithArgs(
                $descriptor->Implementation,
                [
                    DataObjectPurgeable::REASON_UNPUBLISH,
                    $purge
                ]
        );

        $job->setup();
        $job->process();

        // check data
        $data = $this->client->getAdapter()->getData();
        $headers = $this->client->getAdapter()->getHeaders();
        $client_headers = $this->client->getAdapter()->getClientHeaders();
        $uri = $this->client->getAdapter()->getLastUri();

        $this->assertTrue(isset($data['tags']), "'tags' does not exist in POST data");

        $keys = array_keys($data);
        $this->assertEquals(1, count($keys), "There should only be one key in the data, found: " . count($data));

        $values = $purge->getPurgeTypeValues( $purge->Type );
        $this->assertEquals($values, $data['tags'], "Purged tags sent in data does not match record getPurgeTypeValues");
        $this->assertEquals("zones/{$this->client->getZoneIdentifier()}/purge_cache", $uri, "URI mismatch");

        $this->assertEquals(
            [
                $this->client->config()->get('email'),
                $this->client->config()->get('auth_key')
            ],
            array_values($client_headers),
            "Client AUTH headers mismatch"
        );

        $purge->delete();

        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ TagCachePurgeJob::class ] );
        $this->assertEquals(0, $descriptors->count(), "Jobs count should be 0 after delete");

    }

    public function testPurgeAll() {
        $result = $this->client->purgeAll();
        $this->assertNotEquals(false, $result, "purgeAll returned false");

        $descriptors = QueuedJobDescriptor::get()->filter(['Implementation' => EntireCachePurgeJob::class]);

        $this->assertEquals(1, $descriptors->count(), "Jobs count should be 1");

        $job = Injector::inst()->create(EntireCachePurgeJob::class);
        $job->setUp();
        $job->process();

        // check data
        $data = $this->client->getAdapter()->getData();
        $headers = $this->client->getAdapter()->getHeaders();
        $client_headers = $this->client->getAdapter()->getClientHeaders();
        $uri = $this->client->getAdapter()->getLastUri();

        $this->assertTrue(isset($data['purge_everything']), "'purge_everything' does not exist in POST data");

        $keys = array_keys($data);
        $this->assertEquals(1, count($keys), "There should only be one key in the data, found: " . count($data));

        $this->assertEquals(
            [
                $this->client->config()->get('email'),
                $this->client->config()->get('auth_key')
            ],
            array_values($client_headers),
            "Client AUTH headers mismatch"
        );

        $this->assertEquals("zones/{$this->client->getZoneIdentifier()}/purge_cache", $uri, "URI mismatch");
    }

}


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

    public function getPurgeUrlList() {
        return [
            $this->AbsoluteLink(),
            $this->SomeRelatedLink()
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
