<?php

declare(strict_types=1);

namespace App\Console\Commands\Scraper;

use App\Application\Scraper\Actions\ExecuteScrapeRun;
use App\Domain\Scraper\Services\ScraperRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scrape:jumbo {--category= : Optional category ID to scrape} {--max-results=100 : Maximum number of results to fetch}')]
#[Description('Scrape products from Jumbo')]
class ScrapeJumboCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ScraperRegistry $registry, ExecuteScrapeRun $action): int
    {
        $this->info('Starting Jumbo scrape...');

        $startTime = now();

        try {
            $scraper = $registry->get('jumbo');

            $category = $this->option('category');
            $maxResults = (int) $this->option('max-results');

            $scrapeRun = $action->execute($scraper, $category, $maxResults);

            if ($scrapeRun->status === 'completed') {
                $duration = round($startTime->floatDiffInSeconds(now()), 2);

                $this->info('✓ Scrape completed successfully!');
                $this->info("  Products scraped: {$scrapeRun->product_count}");
                $this->info("  Duration: {$duration}s");

                return Command::SUCCESS;
            }

            $this->error("✗ Scrape failed: {$scrapeRun->error_message}");

            return Command::FAILURE;

        } catch (\Throwable $e) {
            $this->error("✗ Scrape failed: {$e->getMessage()}");

            return Command::FAILURE;
        }
    }
}
