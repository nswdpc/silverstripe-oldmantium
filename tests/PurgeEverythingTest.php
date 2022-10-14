<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\DataObjectPurgeable;
use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use NSWDPC\Utilities\Cloudflare\Logger;
use NSWDPC\Utilities\Cloudflare\PurgeRecord;
use NSWDPC\Utilities\Cloudflare\EntireCachePurgeJob;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

require_once(dirname(__FILE__) . '/CloudflarePurgeTest.php');

/**
 * Test purge cache everything
 * @author James
 */
class PurgeEverythingTest extends CloudflarePurgeTest
{

    public function testPurgeAll() {

        // request to purge all
        $result = $this->client->purgeAll();
        $this->assertNotEquals(false, $result, "purgeAll returned false");

        // job should exist
        $descriptors = QueuedJobDescriptor::get()->filter(['Implementation' => EntireCachePurgeJob::class]);
        $this->assertEquals(1, $descriptors->count(), "Jobs count should be 1");

        // process job
        $job = Injector::inst()->create(EntireCachePurgeJob::class);
        $job->setUp();
        $job->process();

        // check data
        $data = $this->client->getAdapter()->getData();
        $headers = $this->client->getAdapter()->getHeaders();
        $client_headers = $this->client->getAdapter()->getClientHeaders();
        $uri = $this->client->getAdapter()->getLastUri();

        // request data should include a purge_everything key
        $this->assertArrayHasKey('purge_everything', $data, "'purge_everything' does not exist in POST data");

        $keys = array_keys($data);
        $this->assertEquals(1, count($keys), "There should only be one key in the data, found: " . count($data));

        $this->assertEquals(
            [
                "Bearer " . $this->client->config()->get('auth_token')
            ],
            array_values($client_headers),
            "Client AUTH headers mismatch"
        );

        $this->assertEquals("zones/{$this->client->getZoneIdentifier()}/purge_cache", $uri, "URI mismatch");
    }

}
