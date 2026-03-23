<?php

declare(strict_types=1);

namespace App\Application\Scraper\Actions;

use App\Domain\Scraper\Contracts\SupermarketScraperInterface;
use App\Infrastructure\Scraper\Repositories\PriceRepository;
use App\Infrastructure\Scraper\Repositories\ProductRepository;
use App\Models\ScrapeRun;
use Illuminate\Support\Facades\Log;

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
        }

        return $scrapeRun->fresh();
    }
}
