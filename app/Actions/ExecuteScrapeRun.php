<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Scraper\SupermarketScraperInterface;
use App\Events\ScrapeRunCompleted;
use App\Models\ScrapeRun;
use App\Notifications\ScrapeRunFailed;
use App\Repositories\Scraper\PriceRepository;
use App\Repositories\Scraper\ProductRepository;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

/**
 * Action to execute a complete scrape run for a supermarket.
 *
 * Orchestrates product fetching, storage, and price recording.
 */
class ExecuteScrapeRun
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly PriceRepository $priceRepository
    ) {}

    /**
     * Execute a scrape run for a supermarket.
     *
     * @param  string|null  $category  Optional category to scrape
     * @param  int  $maxResults  Maximum results to fetch
     */
    public function execute(
        SupermarketScraperInterface $scraper,
        ?string $category = null,
        int $maxResults = 100
    ): ScrapeRun {
        $identifier = $scraper->getIdentifier();

        Log::channel('scraper')->info('Starting scrape run', [
            'supermarket' => $identifier,
            'category' => $category,
            'max_results' => $maxResults,
        ]);

        // Create scrape run record
        $scrapeRun = ScrapeRun::create([
            'supermarket' => $identifier,
            'started_at' => now(),
            'status' => 'running',
        ]);

        // Set scrape run ID on scraper for logging context
        if (method_exists($scraper, 'setScrapeRunId')) {
            $scraper->setScrapeRunId($scrapeRun->id);
        }

        try {
            // Authenticate if needed
            Log::channel('scraper')->info('Authenticating with API', [
                'supermarket' => $identifier,
                'scrape_run_id' => $scrapeRun->id,
            ]);

            if (! $scraper->authenticate()) {
                throw new \Exception('Authentication failed');
            }

            Log::channel('scraper')->info('Authentication successful', [
                'supermarket' => $identifier,
                'scrape_run_id' => $scrapeRun->id,
            ]);

            // Fetch products
            Log::channel('scraper')->info('Fetching products', [
                'supermarket' => $identifier,
                'scrape_run_id' => $scrapeRun->id,
                'category' => $category,
                'max_results' => $maxResults,
            ]);

            $products = $category !== null
                ? $scraper->getProductsByCategory($category, $maxResults)
                : $scraper->searchProducts('', $maxResults);

            Log::channel('scraper')->info('Products fetched', [
                'supermarket' => $identifier,
                'scrape_run_id' => $scrapeRun->id,
                'product_count' => $products->count(),
            ]);

            $productCount = 0;
            $errorCount = 0;

            foreach ($products as $productData) {
                try {
                    // CRITICAL: Always save product and price first
                    $product = $this->productRepository->upsert($productData);

                    // Create price record
                    $this->priceRepository->create(
                        $productData->productId,
                        $productData->supermarket,
                        $productData->priceCents,
                        $productData->promoPriceCents,
                        $productData->available,
                        $productData->badge,
                        $productData->unitPrice,
                        $productData->scrapedAt
                    );

                    $productCount++;

                    // OPTIONAL: Category mapping happens in ProductRepository->upsert()
                    // It's wrapped in try-catch there, so failures won't block storage

                } catch (\Throwable $e) {
                    $errorCount++;

                    // Log but continue with next product
                    Log::channel('scraper-errors')->error('Failed to store product', [
                        'product_id' => $productData->productId,
                        'product_name' => $productData->name,
                        'supermarket' => $identifier,
                        'scrape_run_id' => $scrapeRun->id,
                        'error' => $e->getMessage(),
                        'exception_class' => get_class($e),
                    ]);
                }
            }

            // Mark as completed
            $scrapeRun->markCompleted($productCount);

            $duration = now()->diffInSeconds($scrapeRun->started_at);

            Log::channel('scraper')->info('Scrape run completed successfully', [
                'supermarket' => $identifier,
                'scrape_run_id' => $scrapeRun->id,
                'product_count' => $productCount,
                'error_count' => $errorCount,
                'duration_seconds' => $duration,
                'products_per_second' => $duration > 0 ? round($productCount / $duration, 2) : 0,
            ]);

        } catch (\Throwable $e) {
            // Mark as failed
            $scrapeRun->markFailed($e->getMessage());

            Log::channel('scraper-errors')->error('Scrape run failed', [
                'supermarket' => $identifier,
                'scrape_run_id' => $scrapeRun->id,
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            // Send notification if enabled
            if (config('scrapers.notifications.enabled', true)) {
                $this->sendFailureNotification($scrapeRun);
            }
        }

        // Broadcast completion event OUTSIDE the main try-catch
        // so broadcasting failures don't mark the scrape as failed
        $scrapeRun = $scrapeRun->fresh();

        if ($scrapeRun->status->value === 'completed') {
            try {
                event(new ScrapeRunCompleted($scrapeRun));
            } catch (\Throwable $e) {
                Log::channel('scraper-errors')->warning('Failed to broadcast ScrapeRunCompleted event', [
                    'scrape_run_id' => $scrapeRun->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $scrapeRun;
    }

    /**
     * Send failure notification to configured channels.
     *
     * @param  ScrapeRun  $scrapeRun  Failed scrape run
     */
    private function sendFailureNotification(ScrapeRun $scrapeRun): void
    {
        try {
            // Send to mail if configured
            if (config('scrapers.notifications.channels.mail', false)) {
                $mailTo = config('scrapers.notifications.mail_to');
                if ($mailTo) {
                    Notification::route('mail', $mailTo)
                        ->notify(new ScrapeRunFailed($scrapeRun));
                }
            }

            // Send to Slack if configured
            if (config('scrapers.notifications.channels.slack', false)) {
                $slackWebhook = config('scrapers.notifications.slack_webhook');
                if ($slackWebhook) {
                    Notification::route('slack', $slackWebhook)
                        ->notify(new ScrapeRunFailed($scrapeRun));
                }
            }
        } catch (\Throwable $e) {
            Log::channel('scraper-errors')->error('Failed to send scrape failure notification', [
                'scrape_run_id' => $scrapeRun->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
