<?php

declare(strict_types=1);

namespace App\Repositories\Scraper;

use App\DataTransferObjects\Scraper\ProductData;
use App\Models\Category;
use App\Models\Product;
use App\Services\Scraper\CategoryMatcher;
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

        // OPTIONAL: Sync product categories if provided (wrapped in try-catch)
        if (! empty($productData->categoryIds)) {
            try {
                // Find category database IDs from category_id strings
                $categoryDbIds = Category::query()
                    ->where('supermarket', $productData->supermarket)
                    ->whereIn('category_id', $productData->categoryIds)
                    ->pluck('id')
                    ->toArray();

                if (! empty($categoryDbIds)) {
                    // Sync categories (replaces existing relationships)
                    // This ensures each product has only the category from its most recent scrape
                    $product->categories()->sync($categoryDbIds);

                    Log::debug('Synced product categories', [
                        'product_id' => $product->product_id,
                        'categories' => count($categoryDbIds),
                    ]);
                }
            } catch (\Throwable $e) {
                // Log but don't fail - category mapping is optional
                Log::warning('Category sync failed for product', [
                    'product_id' => $productData->productId,
                    'error' => $e->getMessage(),
                ]);
            }
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
