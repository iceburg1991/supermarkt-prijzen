<?php

declare(strict_types=1);

namespace App\Console\Commands\Scraper;

use App\Application\Scraper\Actions\ExecuteScrapeRun;
use App\Domain\Scraper\Services\ScraperRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as CommandAlias;

#[Signature('scrape:ah {--category= : Optional category ID to scrape} {--max-results=100 : Maximum number of results to fetch}')]
#[Description('Scrape products from Albert Heijn')]
class ScrapeAhCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ScraperRegistry $registry, ExecuteScrapeRun $action): int
    {
        $this->info('Starting Albert Heijn scrape...');

        $startTime = now();

        try {
            $scraper = $registry->get('ah');

            $category = $this->option('category');
            $maxResults = (int) $this->option('max-results');

            $scrapeRun = $action->execute($scraper, $category, $maxResults);

            if ($scrapeRun->status === 'completed') {
                $duration = now()->diffInSeconds($startTime);

                $this->info('✓ Scrape completed successfully!');
                $this->info("  Products scraped: {$scrapeRun->product_count}");
                $this->info("  Duration: {$duration}s");

                return CommandAlias::SUCCESS;
            }

            $this->error("✗ Scrape failed: {$scrapeRun->error_message}");

            return CommandAlias::FAILURE;

        } catch (\Throwable $e) {
            $this->error("✗ Scrape failed: {$e->getMessage()}");

            return CommandAlias::FAILURE;
        }
    }
}
