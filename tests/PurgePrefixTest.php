<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\DataObjectPurgeable;
use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use NSWDPC\Utilities\Cloudflare\Logger;
use NSWDPC\Utilities\Cloudflare\PurgeRecord;
use NSWDPC\Utilities\Cloudflare\PrefixCachePurgeJob;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

require_once(dirname(__FILE__) . '/CloudflarePurgeTestAbstract.php');

/**
 * Test purge prefixes
 * @author James
 */
class PurgePrefixTest extends CloudflarePurgeTestAbstract
{

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
        $purge->publishSingle();

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
        $this->validatePurgeRequest($purge, 'prefixes');

        $purge->doUnpublish();

        // test that a job was created for this record reason = 'write'
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ PrefixCachePurgeJob::class ] );

        $this->assertEquals(0, $descriptors->count(), "Jobs count should be 0");

        $purge->delete();

        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ PrefixCachePurgeJob::class ] );
        $this->assertEquals(0, $descriptors->count(), "Jobs count should be 0 after delete");

    }

}
