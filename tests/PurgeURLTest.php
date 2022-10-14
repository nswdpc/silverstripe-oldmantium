<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\DataObjectPurgeable;
use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use NSWDPC\Utilities\Cloudflare\Logger;
use NSWDPC\Utilities\Cloudflare\PurgeRecord;
use NSWDPC\Utilities\Cloudflare\URLCachePurgeJob;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

require_once(dirname(__FILE__) . '/CloudflarePurgeTest.php');

/**
 * Test purge URLs
 * @author James
 */
class PurgeURLTest extends CloudflarePurgeTest
{

    public function testPurgeRecordURL() {

        $urls = [
            'https://example.com',
            '/foo/bar'
        ];
        $purge = PurgeRecord::create([
            'Title' => 'Purge URLs',
            'Type' => CloudflarePurgeService::TYPE_URL,
            'TypeValues'  => $urls
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
        $job->process();// request / resppnse

        // check data
        $data = $this->client->getAdapter()->getData();
        $headers = $this->client->getAdapter()->getHeaders();
        $client_headers = $this->client->getAdapter()->getClientHeaders();
        $uri = $this->client->getAdapter()->getLastUri();

        $this->assertArrayHasKey('files', $data, "'files' does not exist in POST data");
        $this->assertEquals(1, count( array_keys($data) ), "There should only be one key in the data, found: " . count($data));

        $values = $purge->getPurgeTypeValues( $purge->Type );
        $this->assertEquals($values, $data['files'], "Purged files sent in data does not match record getPurgeTypeValues");
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
        $job->process();// request / response

        // check data
        $data = $this->client->getAdapter()->getData();
        $headers = $this->client->getAdapter()->getHeaders();
        $client_headers = $this->client->getAdapter()->getClientHeaders();
        $uri = $this->client->getAdapter()->getLastUri();

        $this->assertArrayHasKey('files', $data, "'files' does not exist in POST data");
        $this->assertEquals(1, count( array_keys($data) ), "There should only be one key in the data, found: " . count($data));

        $values = $purge->getPurgeTypeValues( $purge->Type );
        $this->assertEquals($values, $data['files'], "Purged files sent in data does not match record getPurgeTypeValues");
        $this->assertEquals("zones/{$this->client->getZoneIdentifier()}/purge_cache", $uri, "URI mismatch");

        $this->assertEquals(
            [
                "Bearer " . $this->client->config()->get('auth_token')
            ],
            array_values($client_headers),
            "Client AUTH headers mismatch"
        );

        $purge->delete();

        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ URLCachePurgeJob::class ] );
        $this->assertEquals(0, $descriptors->count(), "Jobs count should be 0 after delete");

    }

}
