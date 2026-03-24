<?php

declare(strict_types=1);

namespace App\Console\Commands\Scraper;

use App\Actions\ExecuteScrapeRun;
use App\Services\Scraper\ScraperRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scrape:all')]
#[Description('Scrape products from all enabled supermarkets')]
class ScrapeAllCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ScraperRegistry $registry, ExecuteScrapeRun $action): int
    {
        $this->info('Starting scrape for all enabled supermarkets...');

        $scrapers = $registry->enabled();

        if ($scrapers->isEmpty()) {
            $this->warn('No enabled scrapers found');

            return Command::SUCCESS;
        }

        $results = [];
        $hasFailures = false;

        foreach ($scrapers as $scraper) {
            $identifier = $scraper->getIdentifier();

            $this->info("Scraping {$identifier}...");

            try {
                $scrapeRun = $action->execute($scraper, null, 100);

                if ($scrapeRun->status === 'completed') {
                    $results[$identifier] = [
                        'status' => 'success',
                        'count' => $scrapeRun->product_count,
                    ];
                    $this->info("  ✓ {$identifier}: {$scrapeRun->product_count} products");
                } else {
                    $results[$identifier] = [
                        'status' => 'failed',
                        'error' => $scrapeRun->error_message,
                    ];
                    $this->error("  ✗ {$identifier}: {$scrapeRun->error_message}");
                    $hasFailures = true;
                }
            } catch (\Throwable $e) {
                $results[$identifier] = [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ];
                $this->error("  ✗ {$identifier}: {$e->getMessage()}");
                $hasFailures = true;
            }
        }

        // Summary
        $this->newLine();
        $this->info('Summary:');
        $successCount = collect($results)->where('status', 'success')->count();
        $failCount = collect($results)->where('status', 'failed')->count();

        $this->info("  Successful: {$successCount}");
        if ($failCount > 0) {
            $this->error("  Failed: {$failCount}");
        }

        return $hasFailures ? Command::FAILURE : Command::SUCCESS;
    }
}
