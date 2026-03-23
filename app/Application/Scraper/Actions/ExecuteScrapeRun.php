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

        // Create scrape run record
        $scrapeRun = ScrapeRun::create([
            'supermarket' => $identifier,
            'started_at' => now(),
            'status' => 'running',
        ]);

        try {
            // Authenticate if needed
            if (! $scraper->authenticate()) {
                throw new \Exception('Authentication failed');
            }

            // Fetch products
            $products = $category !== null
                ? $scraper->getProductsByCategory($category, $maxResults)
                : $scraper->searchProducts('', $maxResults);

            $productCount = 0;

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
                    // Log but continue with next product
                    Log::error('Failed to store product', [
                        'product_id' => $productData->productId,
                        'supermarket' => $identifier,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Mark as completed
            $scrapeRun->markCompleted($productCount);

            Log::info('Scrape run completed', [
                'supermarket' => $identifier,
                'product_count' => $productCount,
                'duration' => now()->diffInSeconds($scrapeRun->started_at),
            ]);

        } catch (\Throwable $e) {
            // Mark as failed
            $scrapeRun->markFailed($e->getMessage());

            Log::error('Scrape run failed', [
                'supermarket' => $identifier,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return $scrapeRun->fresh();
    }
}
