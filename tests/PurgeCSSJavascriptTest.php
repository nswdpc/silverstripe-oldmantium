<?php

namespace NSWDPC\Utilities\Cloudflare\Tests;

use NSWDPC\Utilities\Cloudflare\DataObjectPurgeable;
use NSWDPC\Utilities\Cloudflare\CloudflarePurgeService;
use NSWDPC\Utilities\Cloudflare\Logger;
use NSWDPC\Utilities\Cloudflare\PurgeRecord;
use NSWDPC\Utilities\Cloudflare\CSSJavascriptCachePurgeJob;
use SilverStripe\Core\Injector\Injector;
use Symbiote\QueuedJobs\DataObjects\QueuedJobDescriptor;
use Symbiote\QueuedJobs\Services\QueuedJobService;

require_once(dirname(__FILE__) . '/CloudflarePurgeTest.php');

/**
 * Test purge CSS and JS
 * @author James
 */
class PurgeCSSJavascriptTest extends CloudflarePurgeTest
{

    /**
     * Test purging images
     */
    public function testPurgeRecordCSSJavascript() {

        $purge = PurgeRecord::create([
            'Title' => 'Purge CSS & JS',
            'Type' => CloudflarePurgeService::TYPE_CSS_JAVASCRIPT,
            'TypeValues'  => null
        ]);

        $purge->write();
        $purge->publishSingle();

        $values = $purge->getPurgeTypeValues($purge->Type);

        $this->assertEmpty($values, "This purge record should have no values");

        // test that a job was created for this record
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ CSSJavascriptCachePurgeJob::class ] );
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
        $data = $this->client->getAdapter()->getMockRequestData();
        $expected = $this->client->prepUrls( $this->client->getPublicFilesByExtension(['css','js','json']) );
        sort($data['options']['json']['files']);
        sort($expected);
        $this->assertEquals($expected, $data['options']['json']['files']);

        $purge->doUnpublish();

        // test that a job was created for this record reason = 'write'
        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ CSSJavascriptCachePurgeJob::class ] );

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
        $data = $this->client->getAdapter()->getMockRequestData();
        $expected = $this->client->prepUrls( $this->client->getPublicFilesByExtension(['css','js','json']) );
        sort($data['options']['json']['files']);
        sort($expected);
        $this->assertEquals($expected, $data['options']['json']['files']);

        $purge->delete();

        $descriptors = $purge->getCurrentPurgeJobDescriptors( [ CSSJavascriptCachePurgeJob::class ] );
        $this->assertEquals(0, $descriptors->count(), "Jobs count should be 0 after delete");

    }

}
