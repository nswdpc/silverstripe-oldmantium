<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\DataObjectPurgeable;
use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use NSWDPC\Utilities\Cloudflare\Logger;
use NSWDPC\Utilities\Cloudflare\PurgeRecord;
use NSWDPC\Utilities\Cloudflare\HostCachePurgeJob;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

require_once(dirname(__FILE__) . '/CloudflarePurgeTest.php');

/**
 * Test purge hosts
 * @author James
 */
class PurgeHostTest extends CloudflarePurgeTest
{

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

        $this->assertArrayHasKey('hosts', $data, "'hosts' does not exist in POST data");
        $this->assertEquals(1, count( array_keys($data) ), "There should only be one key in the data, found: " . count($data));

        $values = $purge->getPurgeTypeValues( $purge->Type );
        $this->assertEquals($values, $data['hosts'], "Purged hosts sent in data does not match record getPurgeTypeValues");
        $this->assertEquals("zones/{$this->client->getZoneIdentifier()}/purge_cache", $uri, "URI mismatch");

        $this->assertEquals(
            [
                "Bearer " . $this->client->config()->get('auth_token')
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

        $this->assertArrayHasKey('hosts', $data, "'hosts' does not exist in POST data");
        $this->assertEquals(1, count( array_keys($data) ), "There should only be one key in the data, found: " . count($data));

        $values = $purge->getPurgeTypeValues( $purge->Type );
        $this->assertEquals($values, $data['hosts'], "Purged hosts sent in data does not match record getPurgeTypeValues");
        $this->assertEquals("zones/{$this->client->getZoneIdentifier()}/purge_cache", $uri, "URI mismatch");

        $this->assertEquals(
            [
                "Bearer " . $this->client->config()->get('auth_token')
            ],
            array_values($client_headers),
            "Client AUTH headers mismatch"
        );

        $purge->delete();

        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ HostCachePurgeJob::class ] );
        $this->assertEquals(0, $descriptors->count(), "Jobs count should be 0 after delete");

    }

}