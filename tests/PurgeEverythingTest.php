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

require_once(dirname(__FILE__) . '/CloudflarePurgeTestAbstract.php');

/**
 * Test purge cache everything
 * @author James
 */
class PurgeEverythingTest extends CloudflarePurgeTestAbstract
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
        $data = $this->client->getAdapter()->getMockRequestData();
        // request data should include a purge_everything key
        $this->assertArrayHasKey('purge_everything', $data['options']['json'], "'purge_everything' does not exist in POST data");
        $this->assertTrue($data['options']['json']['purge_everything']);
    }

}
