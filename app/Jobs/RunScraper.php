<?php

namespace App\Jobs;

use App\Actions\ExecuteScrapeRun;
use App\Services\Scraper\ScraperRegistry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunScraper implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     * 5 minutes to allow for scrape runs with rate limiting.
     */
    public int $timeout = 300;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $supermarket
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ScraperRegistry $registry, ExecuteScrapeRun $action): void
    {
        Log::channel('scraper')->info('Starting scraper job', [
            'supermarket' => $this->supermarket,
        ]);

        try {
            // Get scraper instance
            $scraper = $registry->get($this->supermarket);

            // Execute scrape run directly (not via Artisan command)
            $maxResults = (int) config('scrapers.max_results', 1000);
            $scrapeRun = $action->execute($scraper, null, $maxResults);

            Log::channel('scraper')->info('Scraper job completed', [
                'supermarket' => $this->supermarket,
                'scrape_run_id' => $scrapeRun->id,
                'status' => $scrapeRun->status->value,
                'products_scraped' => $scrapeRun->products_scraped,
            ]);
        } catch (\Throwable $e) {
            Log::channel('scraper-errors')->error('Scraper job failed', [
                'supermarket' => $this->supermarket,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
            ]);

            throw $e;
        }
    }
}
