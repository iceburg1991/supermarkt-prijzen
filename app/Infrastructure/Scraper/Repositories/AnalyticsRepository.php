<?php

declare(strict_types=1);

namespace App\Infrastructure\Scraper\Repositories;

use App\Models\Price;
use App\Models\Product;
use App\Models\ScrapeRun;
use App\Models\Supermarket;
use Illuminate\Support\Collection;

/**
 * Repository for analytics queries on price data.
 *
 * Provides optimized queries for price analysis, trends, and comparisons.
 * Uses database aggregations and eager loading to prevent N+1 queries.
 */
class AnalyticsRepository
{
    /**
     * Get price history for a product over time period with chart-ready format.
     *
     * Returns price records formatted for charting with dates and prices.
     */
    public function getPriceHistory(
        string $productId,
        string $supermarket,
        int $days = 90
    ): Collection {
        $startDate = now()->subDays($days);

        return Price::query()
            ->where('product_id', $productId)
            ->where('supermarket', $supermarket)
            ->where('scraped_at', '>=', $startDate)
            ->orderBy('scraped_at', 'asc')
            ->get()
            ->map(function (Price $price) {
                return [
                    'scraped_at' => $price->scraped_at->toIso8601String(),
                    'price_cents' => $price->price_cents,
                    'promo_price_cents' => $price->promo_price_cents,
                ];
            });
    }

    /**
     * Calculate average price for a product over time period.
     *
     * Uses database aggregation for performance.
     */
    public function getAveragePrice(
        string $productId,
        string $supermarket,
        int $days = 30
    ): float {
        $startDate = now()->subDays($days);

        $result = Price::query()
            ->where('product_id', $productId)
            ->where('supermarket', $supermarket)
            ->where('scraped_at', '>=', $startDate)
            ->selectRaw('AVG(price_cents) as average_price')
            ->first();

        return (float) ($result?->average_price ?? 0.0);
    }

    /**
     * Compare prices across supermarkets for similar products.
     *
     * Searches by product name and returns latest prices from all supermarkets.
     */
    public function comparePrices(string $productName): Collection
    {
        return Product::query()
            ->with(['latestPrice', 'supermarketModel'])
            ->where('name', 'LIKE', "%{$productName}%")
            ->whereHas('latestPrice')
            ->get()
            ->map(function (Product $product) {
                $latestPrice = $product->latestPrice;

                return [
                    'product_id' => $product->product_id,
                    'supermarket' => $product->supermarket,
                    'supermarket_name' => $product->supermarketModel?->name ?? $product->supermarket,
                    'name' => $product->name,
                    'quantity' => $product->quantity,
                    'price_cents' => $latestPrice->price_cents,
                    'promo_price_cents' => $latestPrice->promo_price_cents,
                    'effective_price' => $latestPrice->getEffectivePrice(),
                    'has_promotion' => $latestPrice->hasPromotion(),
                    'scraped_at' => $latestPrice->scraped_at->format('Y-m-d H:i:s'),
                ];
            })
            ->sortBy('effective_price')
            ->values();
    }

    /**
     * Get products with highest price volatility.
     *
     * Identifies products with highest standard deviation in prices.
     * Uses database aggregation for performance on MySQL/PostgreSQL,
     * falls back to collection-based calculation for SQLite.
     */
    public function getMostVolatileProducts(int $limit = 10, int $days = 90): Collection
    {
        $startDate = now()->subDays($days);
        $driver = config('database.default');
        $connection = config("database.connections.{$driver}.driver");

        // For SQLite, we need to calculate volatility in PHP since STDDEV is not available
        if ($connection === 'sqlite') {
            return $this->getMostVolatileProductsSqlite($startDate, $limit);
        }

        // For MySQL/PostgreSQL, use database aggregation
        return Price::query()
            ->select('product_id', 'supermarket')
            ->selectRaw('STDDEV(price_cents) as price_stddev')
            ->selectRaw('AVG(price_cents) as avg_price')
            ->selectRaw('COUNT(*) as price_count')
            ->where('scraped_at', '>=', $startDate)
            ->groupBy('product_id', 'supermarket')
            ->having('price_count', '>=', 5) // Need at least 5 data points
            ->orderByDesc('price_stddev')
            ->limit($limit)
            ->get()
            ->map(function ($result) {
                $product = Product::query()
                    ->where('product_id', $result->product_id)
                    ->where('supermarket', $result->supermarket)
                    ->with('supermarketModel')
                    ->first();

                return [
                    'product_id' => $result->product_id,
                    'supermarket' => $result->supermarket,
                    'supermarket_name' => $product?->supermarketModel?->name ?? $result->supermarket,
                    'name' => $product?->name ?? 'Unknown',
                    'price_stddev' => round($result->price_stddev, 2),
                    'avg_price' => round($result->avg_price, 2),
                    'price_count' => $result->price_count,
                ];
            });
    }

    /**
     * Calculate volatility for SQLite (no STDDEV function).
     *
     * Uses collection-based standard deviation calculation.
     */
    protected function getMostVolatileProductsSqlite($startDate, int $limit): Collection
    {
        // Get all prices grouped by product
        $pricesByProduct = Price::query()
            ->select('product_id', 'supermarket', 'price_cents')
            ->where('scraped_at', '>=', $startDate)
            ->get()
            ->groupBy(fn ($price) => $price->product_id.'|'.$price->supermarket);

        // Calculate standard deviation for each product
        $volatility = $pricesByProduct
            ->map(function (Collection $prices, string $key) {
                if ($prices->count() < 5) {
                    return null; // Need at least 5 data points
                }

                [$productId, $supermarket] = explode('|', $key);

                $priceCents = $prices->pluck('price_cents');
                $mean = $priceCents->avg();
                $variance = $priceCents->map(fn ($price) => pow($price - $mean, 2))->avg();
                $stddev = sqrt($variance);

                return [
                    'product_id' => $productId,
                    'supermarket' => $supermarket,
                    'price_stddev' => $stddev,
                    'avg_price' => $mean,
                    'price_count' => $prices->count(),
                ];
            })
            ->filter() // Remove nulls
            ->sortByDesc('price_stddev')
            ->take($limit)
            ->values();

        // Enrich with product details
        return $volatility->map(function ($result) {
            $product = Product::query()
                ->where('product_id', $result['product_id'])
                ->where('supermarket', $result['supermarket'])
                ->with('supermarketModel')
                ->first();

            return [
                'product_id' => $result['product_id'],
                'supermarket' => $result['supermarket'],
                'supermarket_name' => $product?->supermarketModel?->name ?? $result['supermarket'],
                'name' => $product?->name ?? 'Unknown',
                'price_stddev' => round($result['price_stddev'], 2),
                'avg_price' => round($result['avg_price'], 2),
                'price_count' => $result['price_count'],
            ];
        });
    }

    /**
     * Compare average prices by normalized category per supermarket.
     *
     * Returns mean of effective prices (considering promotions) for each supermarket.
     */
    public function compareAveragePricesByCategory(int $normalizedCategoryId): Collection
    {
        // Get all products in the normalized category with their latest prices
        $products = Product::query()
            ->with(['latestPrice', 'supermarketModel'])
            ->whereHas('categories.normalizedCategories', function ($query) use ($normalizedCategoryId) {
                $query->where('normalized_categories.id', $normalizedCategoryId);
            })
            ->whereHas('latestPrice')
            ->get();

        // Group by supermarket and calculate average effective price
        return $products
            ->groupBy('supermarket')
            ->map(function (Collection $supermarketProducts, string $supermarket) {
                $effectivePrices = $supermarketProducts->map(function (Product $product) {
                    return $product->latestPrice->getEffectivePrice();
                });

                $supermarketModel = $supermarketProducts->first()?->supermarketModel;

                return [
                    'supermarket' => $supermarket,
                    'supermarket_name' => $supermarketModel?->name ?? $supermarket,
                    'average_price' => round($effectivePrices->avg(), 2),
                    'product_count' => $supermarketProducts->count(),
                    'min_price' => $effectivePrices->min(),
                    'max_price' => $effectivePrices->max(),
                ];
            })
            ->sortBy('average_price')
            ->values();
    }

    /**
     * Calculate price change percentage between two periods.
     *
     * Compares average price in recent period vs older period.
     */
    public function getPriceChangePercentage(
        string $productId,
        string $supermarket,
        int $days = 7
    ): float {
        $recentDate = now()->subDays($days);
        $olderDate = now()->subDays($days * 2);

        // Get average price for recent period
        $recentAvg = Price::query()
            ->where('product_id', $productId)
            ->where('supermarket', $supermarket)
            ->where('scraped_at', '>=', $recentDate)
            ->avg('price_cents');

        // Get average price for older period
        $olderAvg = Price::query()
            ->where('product_id', $productId)
            ->where('supermarket', $supermarket)
            ->where('scraped_at', '>=', $olderDate)
            ->where('scraped_at', '<', $recentDate)
            ->avg('price_cents');

        if ($olderAvg === null || $olderAvg == 0) {
            return 0.0;
        }

        if ($recentAvg === null) {
            return 0.0;
        }

        return round((($recentAvg - $olderAvg) / $olderAvg) * 100, 2);
    }

    /**
     * Get all products with active promotions across supermarkets.
     *
     * Returns products with promo_price_cents > 0 in their latest price.
     */
    public function getCurrentPromotions(): Collection
    {
        return Product::query()
            ->with(['latestPrice', 'supermarketModel'])
            ->whereHas('latestPrice', function ($query) {
                $query->where('promo_price_cents', '>', 0);
            })
            ->get()
            ->map(function (Product $product) {
                $latestPrice = $product->latestPrice;
                $savings = $latestPrice->price_cents - $latestPrice->promo_price_cents;
                $savingsPercentage = round(($savings / $latestPrice->price_cents) * 100, 2);

                return [
                    'product_id' => $product->product_id,
                    'supermarket' => $product->supermarket,
                    'supermarket_name' => $product->supermarketModel?->name ?? $product->supermarket,
                    'name' => $product->name,
                    'quantity' => $product->quantity,
                    'regular_price' => $latestPrice->price_cents,
                    'promo_price' => $latestPrice->promo_price_cents,
                    'savings' => $savings,
                    'savings_percentage' => $savingsPercentage,
                    'badge' => $latestPrice->badge,
                    'scraped_at' => $latestPrice->scraped_at->format('Y-m-d H:i:s'),
                ];
            })
            ->sortByDesc('savings_percentage')
            ->values();
    }

    /**
     * Get scrape run success/failure rate metrics.
     *
     * @param  int  $days  Number of days to analyze
     * @return array<string, mixed> Metrics including success rate, failure rate, total runs
     */
    public function getScrapeRunMetrics(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $metrics = ScrapeRun::query()
            ->where('started_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total_runs,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as successful_runs,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_runs,
                AVG(CASE WHEN status = "completed" THEN product_count ELSE 0 END) as avg_products_per_run,
                MAX(product_count) as max_products_per_run,
                MIN(CASE WHEN product_count > 0 THEN product_count ELSE NULL END) as min_products_per_run
            ')
            ->first();

        $successRate = $metrics->total_runs > 0
            ? round(($metrics->successful_runs / $metrics->total_runs) * 100, 2)
            : 0;

        $failureRate = $metrics->total_runs > 0
            ? round(($metrics->failed_runs / $metrics->total_runs) * 100, 2)
            : 0;

        return [
            'period_days' => $days,
            'total_runs' => $metrics->total_runs,
            'successful_runs' => $metrics->successful_runs,
            'failed_runs' => $metrics->failed_runs,
            'success_rate' => $successRate,
            'failure_rate' => $failureRate,
            'avg_products_per_run' => round($metrics->avg_products_per_run, 2),
            'max_products_per_run' => $metrics->max_products_per_run,
            'min_products_per_run' => $metrics->min_products_per_run,
        ];
    }

    /**
     * Get scrape run metrics by supermarket.
     *
     * @param  int  $days  Number of days to analyze
     * @return Collection<array<string, mixed>> Metrics per supermarket
     */
    public function getScrapeRunMetricsBySupermarket(int $days = 30): Collection
    {
        $startDate = now()->subDays($days);

        return ScrapeRun::query()
            ->where('started_at', '>=', $startDate)
            ->selectRaw('
                supermarket,
                COUNT(*) as total_runs,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as successful_runs,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_runs,
                AVG(CASE WHEN status = "completed" THEN product_count ELSE 0 END) as avg_products_per_run,
                MAX(started_at) as last_run_at
            ')
            ->groupBy('supermarket')
            ->get()
            ->map(function ($metrics) {
                $successRate = $metrics->total_runs > 0
                    ? round(($metrics->successful_runs / $metrics->total_runs) * 100, 2)
                    : 0;

                return [
                    'supermarket' => $metrics->supermarket,
                    'total_runs' => $metrics->total_runs,
                    'successful_runs' => $metrics->successful_runs,
                    'failed_runs' => $metrics->failed_runs,
                    'success_rate' => $successRate,
                    'avg_products_per_run' => round($metrics->avg_products_per_run, 2),
                    'last_run_at' => $metrics->last_run_at,
                ];
            });
    }

    /**
     * Get error rate metrics.
     *
     * @param  int  $days  Number of days to analyze
     * @return array<string, mixed> Error rate and count
     */
    public function getErrorRate(int $days = 7): array
    {
        $startDate = now()->subDays($days);

        $totalRuns = ScrapeRun::query()
            ->where('started_at', '>=', $startDate)
            ->count();

        $failedRuns = ScrapeRun::query()
            ->where('started_at', '>=', $startDate)
            ->where('status', 'failed')
            ->count();

        $errorRate = $totalRuns > 0
            ? round(($failedRuns / $totalRuns) * 100, 2)
            : 0;

        return [
            'period_days' => $days,
            'total_runs' => $totalRuns,
            'failed_runs' => $failedRuns,
            'error_rate' => $errorRate,
            'threshold_exceeded' => $errorRate > 5.0,
        ];
    }

    /**
     * Check if any supermarket has not had a successful scrape in the last N hours.
     *
     * @param  int  $hours  Number of hours to check
     * @return Collection<array<string, mixed>> Supermarkets without recent successful scrapes
     */
    public function getSupermarketsWithoutRecentScrape(int $hours = 24): Collection
    {
        $threshold = now()->subHours($hours);

        $recentScrapes = ScrapeRun::query()
            ->where('status', 'completed')
            ->where('completed_at', '>=', $threshold)
            ->pluck('supermarket')
            ->unique();

        return Supermarket::query()
            ->where('enabled', true)
            ->whereNotIn('identifier', $recentScrapes)
            ->get()
            ->map(function (Supermarket $supermarket) {
                $lastSuccessfulRun = ScrapeRun::query()
                    ->where('supermarket', $supermarket->identifier)
                    ->where('status', 'completed')
                    ->orderByDesc('completed_at')
                    ->first();

                return [
                    'supermarket' => $supermarket->identifier,
                    'name' => $supermarket->name,
                    'last_successful_scrape' => $lastSuccessfulRun?->completed_at?->format('Y-m-d H:i:s'),
                    'hours_since_last_scrape' => $lastSuccessfulRun?->completed_at
                        ? now()->diffInHours($lastSuccessfulRun->completed_at)
                        : null,
                ];
            });
    }

    /**
     * Get average API response times (estimated from scrape run duration).
     *
     * @param  int  $days  Number of days to analyze
     * @return Collection<array<string, mixed>> Response time metrics per supermarket
     */
    public function getApiResponseTimes(int $days = 7): Collection
    {
        $startDate = now()->subDays($days);

        return ScrapeRun::query()
            ->where('started_at', '>=', $startDate)
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->selectRaw('
                supermarket,
                AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_duration_seconds,
                MIN(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as min_duration_seconds,
                MAX(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as max_duration_seconds,
                COUNT(*) as sample_size
            ')
            ->groupBy('supermarket')
            ->get()
            ->map(function ($metrics) {
                return [
                    'supermarket' => $metrics->supermarket,
                    'avg_duration_seconds' => round($metrics->avg_duration_seconds, 2),
                    'min_duration_seconds' => $metrics->min_duration_seconds,
                    'max_duration_seconds' => $metrics->max_duration_seconds,
                    'sample_size' => $metrics->sample_size,
                ];
            });
    }
}
