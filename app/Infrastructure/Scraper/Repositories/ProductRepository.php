<?php

declare(strict_types=1);

namespace App\Infrastructure\Scraper\Repositories;

use App\Domain\Scraper\Services\CategoryMatcher;
use App\Domain\Scraper\ValueObjects\ProductData;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Repository for product data access.
 *
 * Handles product CRUD operations with category mapping support.
 */
class ProductRepository
{
    public function __construct(
        private readonly CategoryMatcher $categoryMatcher
    ) {}

    /**
     * Upsert product data (update if exists, insert if new).
     *
     * CRITICAL: Category mapping is OPTIONAL and must NEVER block product storage.
     */
    public function upsert(ProductData $productData): Product
    {
        // First, upsert the product (this must always succeed)
        $product = Product::updateOrCreate(
            [
                'product_id' => $productData->productId,
                'supermarket' => $productData->supermarket,
            ],
            [
                'name' => $productData->name,
                'quantity' => $productData->quantity,
                'image_url' => $productData->imageUrl,
                'product_url' => $productData->productUrl,
            ]
        );

        // OPTIONAL: Try to match and assign category (wrapped in try-catch)
        try {
            $normalizedCategory = $this->categoryMatcher->match(
                $productData->name,
                null // We don't have supermarket category from ProductData yet
            );

            if ($normalizedCategory !== null) {
                // Attach normalized category if not already attached
                if (! $product->categories()->where('normalized_categories.id', $normalizedCategory->id)->exists()) {
                    // Note: This assumes we have a way to link products to normalized categories
                    // For now, we'll log it for future implementation
                    Log::debug('Matched product to normalized category', [
                        'product_id' => $product->product_id,
                        'category' => $normalizedCategory->name,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            // Log but don't fail - category mapping is optional
            Log::warning('Category matching failed for product', [
                'product_id' => $productData->productId,
                'error' => $e->getMessage(),
            ]);
        }

        return $product->fresh();
    }

    /**
     * Get current prices for all products.
     *
     * Returns products with their latest price, optionally filtered by supermarket.
     */
    public function getCurrentPrices(?string $supermarket = null): Collection
    {
        $query = Product::query()
            ->with(['latestPrice', 'supermarketModel'])
            ->whereHas('prices');

        if ($supermarket !== null) {
            $query->where('supermarket', $supermarket);
        }

        return $query->get();
    }

    /**
     * Find products by name search.
     *
     * Performs case-insensitive search on product names.
     */
    public function searchByName(string $query): Collection
    {
        return Product::query()
            ->with(['latestPrice', 'supermarketModel'])
            ->where('name', 'LIKE', "%{$query}%")
            ->get();
    }

    /**
     * Get products in a normalized category.
     *
     * Returns all products across supermarkets that belong to the normalized category.
     */
    public function getByNormalizedCategory(int $normalizedCategoryId): Collection
    {
        return Product::query()
            ->with(['latestPrice', 'supermarketModel', 'categories'])
            ->whereHas('categories.normalizedCategories', function ($query) use ($normalizedCategoryId) {
                $query->where('normalized_categories.id', $normalizedCategoryId);
            })
            ->get();
    }

    /**
     * Get products with active promotions.
     *
     * Returns products that have promo_price_cents > 0 in their latest price.
     */
    public function getPromotionalProducts(?string $supermarket = null): Collection
    {
        $query = Product::query()
            ->with(['latestPrice', 'supermarketModel'])
            ->whereHas('latestPrice', function ($query) {
                $query->where('promo_price_cents', '>', 0);
            });

        if ($supermarket !== null) {
            $query->where('supermarket', $supermarket);
        }

        return $query->get();
    }
}
