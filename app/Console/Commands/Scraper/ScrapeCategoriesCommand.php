<?php

declare(strict_types=1);

namespace App\Console\Commands\Scraper;

use App\Domain\Scraper\Services\ScraperRegistry;
use App\Infrastructure\Scraper\Repositories\CategoryRepository;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scrape:categories {supermarket? : Supermarket identifier (ah, jumbo) or leave empty for all}')]
#[Description('Scrape and store categories from supermarket APIs')]
class ScrapeCategoriesCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ScraperRegistry $registry, CategoryRepository $categoryRepository): int
    {
        $supermarketArg = $this->argument('supermarket');

        // Get scrapers to process
        $scrapers = $supermarketArg
            ? collect([$registry->get($supermarketArg)])
            : $registry->enabled();

        if ($scrapers->isEmpty()) {
            $this->error('No scrapers found');

            return Command::FAILURE;
        }

        $this->info('Starting category scrape...');
        $this->newLine();

        $totalCategories = 0;

        foreach ($scrapers as $scraper) {
            $identifier = $scraper->getIdentifier();
            $this->info("Scraping categories for: {$identifier}");

            try {
                // Authenticate if needed
                if (! $scraper->authenticate()) {
                    $this->error("  ✗ Authentication failed for {$identifier}");

                    continue;
                }

                // Fetch categories from API
                $categories = $scraper->getCategories();

                if ($categories->isEmpty()) {
                    $this->warn("  ⚠ No categories found for {$identifier}");

                    continue;
                }

                // Store categories in database
                $stored = 0;
                foreach ($categories as $categoryData) {
                    $categoryRepository->upsert(
                        $identifier,
                        $categoryData['id'],
                        $categoryData['name'],
                        $categoryData['parent_id']
                    );
                    $stored++;
                }

                $totalCategories += $stored;
                $this->info("  ✓ Stored {$stored} categories");

            } catch (\Throwable $e) {
                $this->error("  ✗ Failed: {$e->getMessage()}");
            }

            $this->newLine();
        }

        $this->info("✓ Category scrape completed!");
        $this->info("  Total categories stored: {$totalCategories}");

        return Command::SUCCESS;
    }
}
