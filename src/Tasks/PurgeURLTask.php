<?php

namespace NSWDPC\Utilities\Cloudflare;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;

/**
 * Given a set of URLs, attempt to purge them
 */
class PurgeURLTask extends BuildTask
{
    protected string $title = 'Cloudflare purge one or more URLs';

    protected static string $description = 'Provide URLs as comma delimited values';

    protected static string $commandName = "PurgeURLTask";

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        try {

            if (!CloudflarePurgeService::config()->get('enabled')) {
                throw new \Exception("Not enabled");
            }

            $urls = $input->getOption('url');
            if (!is_string($urls)) {
                throw new \Exception("Please provide a url parameter, with one or more URLs");
            }

            $urls = explode(",", $urls);
            $urlCount = count($urls);
            if ($urlCount === 0) {
                throw new \Exception("Please provide a url parameter, with one or more URLs");
            }

            $response = Injector::inst()->create(CloudflarePurgeService::class)->purgeURLs($urls);
            $count = $response->getResultCount();
            $successes = $response->getSuccesses();
            $errors = $response->getErrors();
            if ($count == 0) {
                $output->writeln("No response / check logs");
            } else {
                $output->writeln("Completed count={$count} urls={$urlCount}");
            }

            foreach ($successes as $id) {
                $output->writeln("Success");
            }

            foreach ($errors as $error) {
                $output->writeln("Error code={$error->code} message={$error->message}");
            }

            return Command::SUCCESS;

        } catch (\Exception $exception) {
            $output->writeln("Error: " . $exception->getMessage());
            return Command::FAILURE;
        }
    }

    #[\Override]
    public function getOptions(): array
    {
        return [
            new InputOption('url', null, InputOption::VALUE_REQUIRED, 'URL(s) to purge in the configured zone. Separate multiple urls with a comma'),
        ];
    }

}
