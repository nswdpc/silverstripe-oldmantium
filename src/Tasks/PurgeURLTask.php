<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

/**
 * Given a set of URLs, attempt to purge them
 */
class PurgeURLTask extends BuildTask
{

    protected $title = 'Cloudflare purge one or more URLs';

    protected $description = 'Provide URLs as comma delimited values';

    private static $segment = "PurgeURLTask";

    public function run($request)
    {
        try {

            if(!CloudflarePurgeService::config()->get('enabled')) {
                throw new \Exception("Not enabled");
            }

            $urls = $request->getVar('url');
            if(!is_string($urls)) {
                throw new \Exception("Please provide a url parameter, with one or more URLs");
            }
            $urls = explode(",", $urls);
            $urlCount = count($urls);
            if($urlCount == 0) {
                throw new \Exception("Please provide a url parameter, with one or more URLs");
            }
            $response = Injector::inst()->create( CloudflarePurgeService::class )->purgeURLs($urls);
            $count = $response->getResultCount();
            $successes = $response->getSuccesses();
            $errors = $response->getErrors();
            if($count == 0) {
                DB::alteration_message("No response / check logs", "error");
            } else {
                DB::alteration_message("Completed count={$count} urls={$urlCount}", "changed");
            }
            foreach($successes as $id) {
                DB::alteration_message("Success", "changed");
            }
            foreach($errors as $error) {
                DB::alteration_message("Error code={$error->code} message={$error->message}", "error");
            }
        } catch (\Exception $e) {
            DB::alteration_message("Error: " . $e->getMessage(), "error");
        }
    }

}
