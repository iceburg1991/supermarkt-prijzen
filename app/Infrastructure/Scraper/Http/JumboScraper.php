<?php

declare(strict_types=1);

namespace App\Infrastructure\Scraper\Http;

use App\Domain\Scraper\ValueObjects\ProductData;
use Illuminate\Support\Collection;

/**
 * Jumbo supermarket scraper implementation.
 *
 * Handles product scraping from Jumbo API without authentication,
 * category discovery, and promotional product retrieval.
 */
class JumboScraper extends BaseScraper
{
    /**
     * Authenticate with the Jumbo API (no-op, no authentication required).
     *
     * @param string|null $authCode Not used for Jumbo
     * @return bool Always returns true
     */
    public function authenticate(?string $authCode = null): bool
    {
        $this->log('info', 'Jumbo API does not require authentication');

        return true;
    }

    /**
     * Search for products by query term.
     *
     * @param string $query Search term
     * @param int $maxResults Maximum number of results to return
     * @return Collection<int, ProductData>
     */
    public function searchProducts(string $query, int $maxResults = 20): Collection
    {
        $products = collect();
        $offset = 0;
        $limit = 20;

        $this->log('info', "Searching products with query: {$query}", [
            'max_results' => $maxResults,
        ]);

        while ($products->count() < $maxResults) {
            $response = $this->get('/search', [
                'q' => $query,
                'offset' => $offset,
                'limit' => $limit,
            ]);

            if ($response === null || ! isset($response['products']['data'])) {
                break;
            }

            $pageProducts = $this->mapProducts($response['products']['data']);
            $products = $products->concat($pageProducts);

            // Check if we've reached the end
            if (count($response['products']['data']) < $limit) {
                break;
            }

            $offset += $limit;

            // Prevent infinite loops
            if ($offset > 2000) {
                $this->log('warning', 'Reached maximum offset limit (2000)');
                break;
            }
        }

        $this->log('info', "Found {$products->count()} products for query: {$query}");

        return $products->take($maxResults);
    }

    /**
     * Get all available categories from Jumbo.
     *
     * @return Collection<int, array{id: string, name: string, parent_id: string|null}>
     */
    public function getCategories(): Collection
    {
        $this->log('info', 'Fetching categories');

        $response = $this->get('/categories');

        if ($response === null || ! isset($response['categories']['data'])) {
            $this->log('warning', 'No categories found in response');

            return collect();
        }

        $categories = $this->flattenCategories($response['categories']['data']);

        $this->log('info', "Found {$categories->count()} categories");

        return $categories;
    }

    /**
     * Get products within a specific category.
     *
     * @param string $categoryId Category identifier
     * @param int $maxResults Maximum number of results to return
     * @return Collection<int, ProductData>
     */
    public function getProductsByCategory(string $categoryId, int $maxResults = 50): Collection
    {
        $products = collect();
        $offset = 0;
        $limit = 20;

        $this->log('info', "Fetching products for category: {$categoryId}", [
            'max_results' => $maxResults,
        ]);

        while ($products->count() < $maxResults) {
            $response = $this->get("/categories/{$categoryId}/products", [
                'offset' => $offset,
                'limit' => $limit,
            ]);

            if ($response === null || ! isset($response['products']['data'])) {
                break;
            }

            $pageProducts = $this->mapProducts($response['products']['data']);
            $products = $products->concat($pageProducts);

            // Check if we've reached the end
            if (count($response['products']['data']) < $limit) {
                break;
            }

            $offset += $limit;

            // Prevent infinite loops
            if ($offset > 2000) {
                $this->log('warning', 'Reached maximum offset limit (2000)');
                break;
            }
        }

        $this->log('info', "Found {$products->count()} products in category: {$categoryId}");

        return $products->take($maxResults);
    }

    /**
     * Get products currently on promotion.
     *
     * @param int $maxResults Maximum number of results to return
     * @return Collection<int, ProductData>
     */
    public function getPromotionalProducts(int $maxResults = 30): Collection
    {
        $products = collect();
        $offset = 0;
        $limit = 20;

        $this->log('info', 'Fetching promotional products', [
            'max_results' => $maxResults,
        ]);

        while ($products->count() < $maxResults) {
            $response = $this->get('/promotion-overview', [
                'offset' => $offset,
                'limit' => $limit,
            ]);

            if ($response === null || ! isset($response['products']['data'])) {
                break;
            }

            $pageProducts = $this->mapProducts($response['products']['data'])
                ->filter(fn (ProductData $product) => $product->hasPromotion());

            $products = $products->concat($pageProducts);

            // Check if we've reached the end
            if (count($response['products']['data']) < $limit) {
                break;
            }

            $offset += $limit;

            // Prevent infinite loops
            if ($offset > 2000) {
                $this->log('warning', 'Reached maximum offset limit (2000)');
                break;
            }
        }

        $this->log('info', "Found {$products->count()} promotional products");

        return $products->take($maxResults);
    }

    /**
     * Get the supermarket identifier.
     *
     * @return string
     */
    public function getIdentifier(): string
    {
        return 'jumbo';
    }

    /**
     * Map API products to ProductData value objects.
     *
     * @param array<int, array<string, mixed>> $apiProducts Products from API
     * @return Collection<int, ProductData>
     */
    protected function mapProducts(array $apiProducts): Collection
    {
        return collect($apiProducts)->map(function ($product) {
            try {
                return $this->mapProduct($product);
            } catch (\Exception $e) {
                $this->log('warning', 'Failed to map product', [
                    'product_id' => $product['id'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        })->filter();
    }

    /**
     * Map single API product to ProductData.
     *
     * @param array<string, mixed> $product Product from API
     * @return ProductData
     */
    protected function mapProduct(array $product): ProductData
    {
        // Extract price information
        $regularPrice = $product['prices']['price']['amount'] ?? 0;
        $priceCents = (int) ($regularPrice * 100);

        // Check for promotional price
        $promoPriceCents = 0;
        if (isset($product['prices']['promotionalPrice']['amount'])) {
            $promoPriceCents = (int) ($product['prices']['promotionalPrice']['amount'] * 100);
        }

        // Extract image URL
        $imageUrl = '';
        if (isset($product['imageInfo']['primaryView'][0]['url'])) {
            $imageUrl = $product['imageInfo']['primaryView'][0]['url'];
        }

        // Build product URL
        $productUrl = '';
        if (isset($product['id'])) {
            $slug = $product['title'] ?? '';
            $slug = strtolower(str_replace(' ', '-', $slug));
            $productUrl = "https://www.jumbo.com/producten/{$slug}-{$product['id']}";
        }

        // Extract unit price
        $unitPrice = '';
        if (isset($product['prices']['unitPrice']['unit'])) {
            $unitPrice = $product['prices']['unitPrice']['unit'];
        }

        return ProductData::fromArray([
            'product_id' => (string) $product['id'],
            'supermarket' => $this->getIdentifier(),
            'name' => $product['title'] ?? '',
            'quantity' => $product['quantity'] ?? '',
            'price_cents' => $priceCents,
            'promo_price_cents' => $promoPriceCents,
            'available' => $product['available'] ?? true,
            'badge' => $product['badge'] ?? '',
            'unit_price' => $unitPrice,
            'image_url' => $imageUrl,
            'product_url' => $productUrl,
            'scraped_at' => now(),
        ]);
    }

    /**
     * Flatten nested category structure.
     *
     * @param array<int, array<string, mixed>> $categories Categories from API
     * @param string|null $parentId Parent category ID
     * @return Collection<int, array{id: string, name: string, parent_id: string|null}>
     */
    protected function flattenCategories(array $categories, ?string $parentId = null): Collection
    {
        $flattened = collect();

        foreach ($categories as $category) {
            $flattened->push([
                'id' => (string) $category['id'],
                'name' => $category['title'] ?? '',
                'parent_id' => $parentId,
            ]);

            // Recursively flatten subcategories
            if (isset($category['children']) && is_array($category['children'])) {
                $children = $this->flattenCategories(
                    $category['children'],
                    (string) $category['id']
                );
                $flattened = $flattened->concat($children);
            }
        }

        return $flattened;
    }
}
