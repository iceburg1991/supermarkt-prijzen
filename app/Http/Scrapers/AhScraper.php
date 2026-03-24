<?php

declare(strict_types=1);

namespace App\Http\Scrapers;

use App\Contracts\Scraper\TokenManagerInterface;
use App\DataTransferObjects\Scraper\ProductData;
use App\DataTransferObjects\Scraper\ScraperConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;

/**
 * Albert Heijn supermarket scraper implementation.
 *
 * Handles product scraping from AH API with OAuth authentication,
 * category discovery, and promotional product retrieval.
 */
class AhScraper extends BaseScraper
{
    /**
     * Token manager for OAuth authentication.
     */
    protected TokenManagerInterface $tokenManager;

    /**
     * Create a new AhScraper instance.
     *
     * @param  ScraperConfig  $config  Scraper configuration
     * @param  TokenManagerInterface  $tokenManager  Token manager
     */
    public function __construct(ScraperConfig $config, TokenManagerInterface $tokenManager)
    {
        parent::__construct($config);
        $this->tokenManager = $tokenManager;
    }

    /**
     * Authenticate with the AH API using OAuth token flow.
     *
     * @param  string|null  $authCode  Optional authorization code for initial setup
     * @return bool True if authentication successful
     */
    public function authenticate(?string $authCode = null): bool
    {
        try {
            if ($authCode !== null) {
                // Exchange authorization code for tokens
                $tokenData = $this->tokenManager->exchangeCode($authCode);
                $this->tokenManager->cacheAccessToken($this->getIdentifier(), $tokenData);
                $this->log('info', 'Successfully authenticated with authorization code');

                return true;
            }

            // Check if we have a valid token or can refresh
            $token = $this->tokenManager->getValidToken($this->getIdentifier());

            if ($token !== null) {
                $this->log('info', 'Authentication successful using cached/refreshed token');

                return true;
            }

            $this->log('error', 'Authentication failed: no valid token available');

            return false;
        } catch (\Exception $e) {
            $this->log('error', 'Authentication failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Search for products by query term.
     *
     * @param  string  $query  Search term
     * @param  int  $maxResults  Maximum number of results to return
     * @return Collection<int, ProductData>
     */
    public function searchProducts(string $query, int $maxResults = 20): Collection
    {
        $products = collect();
        $page = 0;
        $pageSize = 20;

        $this->log('info', "Searching products with query: {$query}", [
            'max_results' => $maxResults,
        ]);

        while ($products->count() < $maxResults) {
            $response = $this->get('/mobile-services/product/search/v2', [
                'query' => $query,
                'page' => $page,
                'size' => $pageSize,
            ]);

            if ($response === null || ! isset($response['products'])) {
                break;
            }

            $pageProducts = $this->mapProducts($response['products']);
            $products = $products->concat($pageProducts);

            // Check if we've reached the end
            if (count($response['products']) < $pageSize) {
                break;
            }

            $page++;

            // Prevent infinite loops
            if ($page > 100) {
                $this->log('warning', 'Reached maximum page limit (100)');
                break;
            }
        }

        $this->log('info', "Found {$products->count()} products for query: {$query}");

        return $products->take($maxResults);
    }

    /**
     * Get all available categories from AH.
     *
     * @return Collection<int, array{id: string, name: string, parent_id: string|null}>
     */
    public function getCategories(): Collection
    {
        $this->log('info', 'Fetching categories');

        $response = $this->get('/mobile-services/v1/product-shelves/categories');

        if ($response === null || ! isset($response['categories'])) {
            $this->log('warning', 'No categories found in response');

            return collect();
        }

        $categories = collect($response['categories'])->map(function ($category) {
            return [
                'id' => (string) $category['id'],
                'name' => $category['name'],
                'parent_id' => $category['parent_id'] ?? null,
            ];
        });

        $this->log('info', "Found {$categories->count()} categories");

        return $categories;
    }

    /**
     * Get products within a specific category.
     *
     * @param  string  $categoryId  Category identifier
     * @param  int  $maxResults  Maximum number of results to return
     * @return Collection<int, ProductData>
     */
    public function getProductsByCategory(string $categoryId, int $maxResults = 50): Collection
    {
        $products = collect();
        $page = 0;
        $pageSize = 20;

        $this->log('info', "Fetching products for category: {$categoryId}", [
            'max_results' => $maxResults,
        ]);

        while ($products->count() < $maxResults) {
            $response = $this->get("/mobile-services/product/search/v2/category/{$categoryId}", [
                'page' => $page,
                'size' => $pageSize,
            ]);

            if ($response === null || ! isset($response['products'])) {
                break;
            }

            $pageProducts = $this->mapProducts($response['products']);
            $products = $products->concat($pageProducts);

            // Check if we've reached the end
            if (count($response['products']) < $pageSize) {
                break;
            }

            $page++;

            // Prevent infinite loops
            if ($page > 100) {
                $this->log('warning', 'Reached maximum page limit (100)');
                break;
            }
        }

        $this->log('info', "Found {$products->count()} products in category: {$categoryId}");

        return $products->take($maxResults);
    }

    /**
     * Get products currently on promotion.
     *
     * @param  int  $maxResults  Maximum number of results to return
     * @return Collection<int, ProductData>
     */
    public function getPromotionalProducts(int $maxResults = 30): Collection
    {
        $products = collect();
        $page = 0;
        $pageSize = 20;

        $this->log('info', 'Fetching promotional products', [
            'max_results' => $maxResults,
        ]);

        while ($products->count() < $maxResults) {
            $response = $this->get('/mobile-services/product/search/v2', [
                'query' => '',
                'filters' => 'bonus',
                'page' => $page,
                'size' => $pageSize,
            ]);

            if ($response === null || ! isset($response['products'])) {
                break;
            }

            $pageProducts = $this->mapProducts($response['products'])
                ->filter(fn (ProductData $product) => $product->hasPromotion());

            $products = $products->concat($pageProducts);

            // Check if we've reached the end
            if (count($response['products']) < $pageSize) {
                break;
            }

            $page++;

            // Prevent infinite loops
            if ($page > 100) {
                $this->log('warning', 'Reached maximum page limit (100)');
                break;
            }
        }

        $this->log('info', "Found {$products->count()} promotional products");

        return $products->take($maxResults);
    }

    /**
     * Get the supermarket identifier.
     */
    public function getIdentifier(): string
    {
        return 'ah';
    }

    /**
     * Map API products to ProductData value objects.
     *
     * @param  array<int, array<string, mixed>>  $apiProducts  Products from API
     * @return Collection<int, ProductData>
     */
    protected function mapProducts(array $apiProducts): Collection
    {
        return collect($apiProducts)->map(function ($product) {
            try {
                return $this->mapProduct($product);
            } catch (\Exception $e) {
                $this->log('warning', 'Failed to map product', [
                    'product_id' => $product['webshopId'] ?? 'unknown',
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        })->filter();
    }

    /**
     * Map single API product to ProductData.
     *
     * @param  array<string, mixed>  $product  Product from API
     */
    protected function mapProduct(array $product): ProductData
    {
        // Extract price information
        $regularPrice = $product['priceBeforeBonus'] ?? $product['price'] ?? 0;
        $priceCents = (int) ($regularPrice * 100);

        // Check if product has bonus/promotion
        $isBonus = $product['isBonus'] ?? false;
        $promoPriceCents = 0;

        if ($isBonus && isset($product['price'])) {
            $promoPriceCents = (int) ($product['price'] * 100);
        }

        // Extract image URL
        $imageUrl = '';
        if (isset($product['images'][0]['url'])) {
            $imageUrl = $product['images'][0]['url'];
        }

        // Build product URL
        $productUrl = '';
        if (isset($product['webshopId'])) {
            $productUrl = "https://www.ah.nl/producten/product/wi{$product['webshopId']}";
        }

        // Extract badge/bonus mechanism
        $badge = '';
        if ($isBonus && isset($product['bonusMechanism'])) {
            $badge = $product['bonusMechanism'];
        }

        return ProductData::fromArray([
            'product_id' => (string) $product['webshopId'],
            'supermarket' => $this->getIdentifier(),
            'name' => $product['title'] ?? '',
            'quantity' => $product['salesUnitSize'] ?? '',
            'price_cents' => $priceCents,
            'promo_price_cents' => $promoPriceCents,
            'available' => $product['availableOnline'] ?? true,
            'badge' => $badge,
            'unit_price' => $product['unitPriceDescription'] ?? '',
            'image_url' => $imageUrl,
            'product_url' => $productUrl,
            'scraped_at' => now(),
        ]);
    }

    /**
     * Create HTTP client with authentication header.
     */
    protected function createHttpClient(): PendingRequest
    {
        $client = parent::createHttpClient();

        // Add authentication token if available
        $token = $this->tokenManager->getValidToken($this->getIdentifier());

        if ($token !== null) {
            $client->withToken($token);
        }

        return $client;
    }
}
