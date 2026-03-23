<?php

declare(strict_types=1);

namespace App\Infrastructure\Scraper\Repositories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Cached wrapper for AnalyticsRepository.
 *
 * Implements caching layer with configurable TTL and performance threshold.
 * Measures query execution time and caches only if exceeds threshold.
 * Supports cache tags for granular invalidation per supermarket and category.
 */
class CachedAnalyticsRepository extends AnalyticsRepository
{
    private int $cacheTtl;

    private int $performanceThreshold;

    private bool $cacheEnabled;

    public function __construct()
    {
        $this->cacheTtl = config('scrapers.analytics.cache_ttl', 3600);
        $this->performanceThreshold = config('scrapers.analytics.performance_threshold', 1000);
        $this->cacheEnabled = config('scrapers.analytics.cache_enabled', true);
    }

    /**
     * Get price history with caching.
     */
    public function getPriceHistory(
        string $productId,
        string $supermarket,
        int $days = 90
    ): Collection {
        if (! $this->cacheEnabled) {
            return parent::getPriceHistory($productId, $supermarket, $days);
        }

        $cacheKey = $this->getCacheKey('price_history', $productId, $supermarket, $days);
        $tags = $this->getCacheTags($supermarket);

        return $this->cacheQuery(
            $cacheKey,
            $tags,
            fn () => parent::getPriceHistory($productId, $supermarket, $days)
        );
    }

    /**
     * Get average price with caching.
     */
    public function getAveragePrice(
        string $productId,
        string $supermarket,
        int $days = 30
    ): float {
        if (! $this->cacheEnabled) {
            return parent::getAveragePrice($productId, $supermarket, $days);
        }

        $cacheKey = $this->getCacheKey('average_price', $productId, $supermarket, $days);
        $tags = $this->getCacheTags($supermarket);

        return $this->cacheQuery(
            $cacheKey,
            $tags,
            fn () => parent::getAveragePrice($productId, $supermarket, $days)
        );
    }

    /**
     * Compare prices with caching.
     */
    public function comparePrices(string $productName): Collection
    {
        if (! $this->cacheEnabled) {
            return parent::comparePrices($productName);
        }

        $cacheKey = $this->getCacheKey('compare_prices', $productName);
        $tags = $this->getCacheTags(); // All supermarkets

        return $this->cacheQuery(
            $cacheKey,
            $tags,
            fn () => parent::comparePrices($productName)
        );
    }

    /**
     * Get most volatile products with caching.
     */
    public function getMostVolatileProducts(int $limit = 10, int $days = 90): Collection
    {
        if (! $this->cacheEnabled) {
            return parent::getMostVolatileProducts($limit, $days);
        }

        $cacheKey = $this->getCacheKey('volatile_products', $limit, $days);
        $tags = $this->getCacheTags(); // All supermarkets

        return $this->cacheQuery(
            $cacheKey,
            $tags,
            fn () => parent::getMostVolatileProducts($limit, $days)
        );
    }

    /**
     * Compare average prices by category with caching.
     */
    public function compareAveragePricesByCategory(int $normalizedCategoryId): Collection
    {
        if (! $this->cacheEnabled) {
            return parent::compareAveragePricesByCategory($normalizedCategoryId);
        }

        $cacheKey = $this->getCacheKey('category_prices', $normalizedCategoryId);
        $tags = $this->getCacheTags(null, $normalizedCategoryId);

        return $this->cacheQuery(
            $cacheKey,
            $tags,
            fn () => parent::compareAveragePricesByCategory($normalizedCategoryId)
        );
    }

    /**
     * Get price change percentage with caching.
     */
    public function getPriceChangePercentage(
        string $productId,
        string $supermarket,
        int $days = 7
    ): float {
        if (! $this->cacheEnabled) {
            return parent::getPriceChangePercentage($productId, $supermarket, $days);
        }

        $cacheKey = $this->getCacheKey('price_change', $productId, $supermarket, $days);
        $tags = $this->getCacheTags($supermarket);

        return $this->cacheQuery(
            $cacheKey,
            $tags,
            fn () => parent::getPriceChangePercentage($productId, $supermarket, $days)
        );
    }

    /**
     * Get current promotions with caching.
     */
    public function getCurrentPromotions(): Collection
    {
        if (! $this->cacheEnabled) {
            return parent::getCurrentPromotions();
        }

        $cacheKey = $this->getCacheKey('current_promotions');
        $tags = $this->getCacheTags(); // All supermarkets

        return $this->cacheQuery(
            $cacheKey,
            $tags,
            fn () => parent::getCurrentPromotions()
        );
    }

    /**
     * Invalidate cache for a specific supermarket.
     *
     * Called when a new scrape run completes.
     */
    public function invalidateCacheForSupermarket(string $supermarket): void
    {
        if (! $this->cacheEnabled) {
            return;
        }

        $tags = $this->getCacheTags($supermarket);

        try {
            Cache::tags($tags)->flush();

            Log::info('Analytics cache invalidated', [
                'supermarket' => $supermarket,
                'tags' => $tags,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to invalidate analytics cache', [
                'supermarket' => $supermarket,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Invalidate cache for a specific category.
     *
     * Called when category mappings change.
     */
    public function invalidateCacheForCategory(int $normalizedCategoryId): void
    {
        if (! $this->cacheEnabled) {
            return;
        }

        $tags = $this->getCacheTags(null, $normalizedCategoryId);

        try {
            Cache::tags($tags)->flush();

            Log::info('Analytics cache invalidated for category', [
                'category_id' => $normalizedCategoryId,
                'tags' => $tags,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to invalidate analytics cache for category', [
                'category_id' => $normalizedCategoryId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Invalidate all analytics cache.
     */
    public function invalidateAllCache(): void
    {
        if (! $this->cacheEnabled) {
            return;
        }

        try {
            Cache::tags(['analytics'])->flush();

            Log::info('All analytics cache invalidated');
        } catch (\Exception $e) {
            Log::warning('Failed to invalidate all analytics cache', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Execute query with caching and performance measurement.
     *
     * Measures execution time and caches only if exceeds threshold.
     */
    private function cacheQuery(string $cacheKey, array $tags, callable $callback): mixed
    {
        // Check if result is already cached
        try {
            $cached = Cache::tags($tags)->get($cacheKey);

            if ($cached !== null) {
                Log::debug('Analytics cache hit', ['key' => $cacheKey]);

                return $cached;
            }
        } catch (\Exception $e) {
            Log::warning('Cache retrieval failed, executing query', [
                'key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);
        }

        // Execute query and measure time
        $startTime = microtime(true);
        $result = $callback();
        $executionTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

        // Cache only if execution time exceeds threshold
        if ($executionTime >= $this->performanceThreshold) {
            try {
                Cache::tags($tags)->put($cacheKey, $result, $this->cacheTtl);

                Log::debug('Analytics query cached', [
                    'key' => $cacheKey,
                    'execution_time_ms' => round($executionTime, 2),
                    'ttl' => $this->cacheTtl,
                ]);
            } catch (\Exception $e) {
                Log::warning('Failed to cache analytics query', [
                    'key' => $cacheKey,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::debug('Analytics query not cached (below threshold)', [
                'key' => $cacheKey,
                'execution_time_ms' => round($executionTime, 2),
                'threshold_ms' => $this->performanceThreshold,
            ]);
        }

        return $result;
    }

    /**
     * Generate cache key from parameters.
     */
    private function getCacheKey(string $method, mixed ...$params): string
    {
        $paramString = implode('_', array_map(fn ($p) => (string) $p, $params));

        return "analytics:{$method}:{$paramString}";
    }

    /**
     * Get cache tags for granular invalidation.
     */
    private function getCacheTags(?string $supermarket = null, ?int $categoryId = null): array
    {
        $tags = ['analytics'];

        if ($supermarket !== null) {
            $tags[] = "supermarket:{$supermarket}";
        }

        if ($categoryId !== null) {
            $tags[] = "category:{$categoryId}";
        }

        return $tags;
    }
}
