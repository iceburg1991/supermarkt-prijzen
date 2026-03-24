<?php

declare(strict_types=1);

namespace App\Console\Commands\Scraper;

use App\Models\Supermarket;
use App\Services\Scraper\ScraperRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('scrape:list')]
#[Description('List all registered scrapers with their capabilities')]
class ListScrapersCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ScraperRegistry $registry): int
    {
        $registry->discover();

        $scrapers = $registry->all();

        if ($scrapers->isEmpty()) {
            $this->warn('No scrapers found');

            return Command::SUCCESS;
        }

        $rows = [];

        foreach ($scrapers as $scraper) {
            $identifier = $scraper->getIdentifier();

            $supermarket = Supermarket::where('identifier', $identifier)->first();

            $rows[] = [
                'identifier' => $identifier,
                'name' => $supermarket?->name ?? 'Unknown',
                'enabled' => $supermarket?->enabled ? '✓' : '✗',
                'requires_auth' => $supermarket?->requires_auth ? 'Yes' : 'No',
                'last_scrape' => $supermarket?->scrapeRuns()->latest('started_at')->first()?->started_at?->diffForHumans() ?? 'Never',
            ];
        }

        $this->table(
            ['Identifier', 'Name', 'Enabled', 'Requires Auth', 'Last Scrape'],
            $rows
        );

        return Command::SUCCESS;
    }
}
