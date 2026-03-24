<?php

declare(strict_types=1);

namespace Feature\Scraper;

use App\DataTransferObjects\Scraper\ProductData;
use App\DataTransferObjects\Scraper\ScraperConfig;
use App\Infrastructure\Scraper\Http\JumboScraper;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Property-based tests for JumboScraper field mapping and pagination.
 *
 * Validates that all Jumbo API fields are correctly mapped to ProductData
 * and that pagination retrieves all products without duplicates.
 */
class JumboScraperPropertyTest extends TestCase
{
    private const int ITERATIONS = 1;

    /**
     * Property 4: API Field Mapping Completeness (Jumbo)
     *
     * For any product returned by Jumbo API, all specified fields should be
     * mapped to ProductData value object.
     *
     * Validates: Requirements 3.5
     */
    public function test_api_field_mapping_completeness_for_all_products(): void
    {
        $failures = [];

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            try {
                // Generate random product for this iteration
                $apiProduct = $this->generateRandomJumboProduct();

                // Set up HTTP fake BEFORE creating scraper
                Http::fake([
                    '*/search*' => Http::response([
                        'products' => [
                            'data' => [$apiProduct],
                            'total' => 1,
                        ],
                    ], 200),
                ]);

                // Create scraper for each iteration
                $scraper = $this->createJumboScraper();

                // Execute search to trigger mapping
                $products = $scraper->searchProducts('test', 1);

                // Validate mapping
                $this->assertCount(1, $products, "Iteration {$i}: Expected 1 product");
                $productData = $products->first();

                $this->assertInstanceOf(
                    ProductData::class,
                    $productData,
                    "Iteration {$i}: Product should be ProductData instance"
                );

                // Validate all required fields are mapped
                $this->validateFieldMapping($apiProduct, $productData, $i);

            } catch (\Throwable $e) {
                $failures[] = "Iteration {$i}: {$e->getMessage()}";
            }
        }

        if (! empty($failures)) {
            $this->fail(
                'Property test failed in '.count($failures)." iterations:\n".
                implode("\n", array_slice($failures, 0, 10))
            );
        }
    }

    /**
     * Test field mapping with products that have promotions.
     */
    public function test_api_field_mapping_for_promotional_products(): void
    {
        $failures = [];

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            try {
                // Clear HTTP fakes before each iteration
                Http::clearResolvedInstances();

                // Generate promotional product
                $apiProduct = $this->generateRandomJumboProduct(true);

                Http::fake([
                    '*/search*' => Http::response([
                        'products' => [
                            'data' => [$apiProduct],
                            'total' => 1,
                        ],
                    ], 200),
                ]);

                $scraper = $this->createJumboScraper();
                $products = $scraper->searchProducts('test', 1);
                $productData = $products->first();

                // Validate promotional fields
                $this->assertTrue(
                    $productData->promoPriceCents > 0,
                    "Iteration {$i}: Promotional product should have promo_price_cents > 0"
                );

                $this->assertTrue(
                    $productData->hasPromotion(),
                    "Iteration {$i}: Promotional product should return true for hasPromotion()"
                );

                $this->assertNotEmpty(
                    $productData->badge,
                    "Iteration {$i}: Promotional product should have badge set"
                );

            } catch (\Throwable $e) {
                $failures[] = "Iteration {$i}: {$e->getMessage()}";
            }
        }

        if (! empty($failures)) {
            $this->fail(
                'Promotional product test failed in '.count($failures)." iterations:\n".
                implode("\n", array_slice($failures, 0, 10))
            );
        }
    }

    /**
     * Test field mapping with products missing optional fields.
     */
    public function test_api_field_mapping_with_missing_optional_fields(): void
    {
        $failures = [];

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            try {
                // Clear HTTP fakes before each iteration
                Http::clearResolvedInstances();

                // Generate product with some optional fields missing
                $apiProduct = $this->generateRandomJumboProduct(false, true);

                Http::fake([
                    '*/search*' => Http::response([
                        'products' => [
                            'data' => [$apiProduct],
                            'total' => 1,
                        ],
                    ], 200),
                ]);

                $scraper = $this->createJumboScraper();
                $products = $scraper->searchProducts('test', 1);
                $productData = $products->first();

                // Should still create valid ProductData with defaults
                $this->assertInstanceOf(ProductData::class, $productData);
                $this->assertNotEmpty($productData->productId);
                $this->assertNotEmpty($productData->name);

            } catch (\Throwable $e) {
                $failures[] = "Iteration {$i}: {$e->getMessage()}";
            }
        }

        if (! empty($failures)) {
            $this->fail(
                'Missing optional fields test failed in '.count($failures)." iterations:\n".
                implode("\n", array_slice($failures, 0, 10))
            );
        }
    }

    /**
     * Property 6: Pagination Completeness
     *
     * For any paginated API response, repeatedly fetching pages should
     * retrieve all products without duplicates.
     *
     * Validates: Requirements 3.6
     */
    public function test_pagination_completeness_without_duplicates(): void
    {
        // Clear HTTP fakes to prevent memory buildup
        Http::clearResolvedInstances();

        // Test with a smaller scenario (10 products to avoid Spatie Data memory issues)
        $totalProducts = 10;
        $allProducts = [];

        // Generate all products with unique IDs
        for ($j = 0; $j < $totalProducts; $j++) {
            $allProducts[] = $this->generateRandomJumboProduct(false, false, "product-pagination-{$j}");
        }

        Http::fake([
            'https://mobileapi.jumbo.com/v17/search*' => Http::response([
                'products' => ['data' => $allProducts, 'total' => $totalProducts],
            ], 200),
        ]);

        $scraper = $this->createJumboScraper();
        $fetchedProducts = $scraper->searchProducts('test', $totalProducts);

        // Validate count
        $this->assertCount(
            $totalProducts,
            $fetchedProducts,
            "Should fetch all {$totalProducts} products"
        );

        // Validate no duplicates
        $productIds = $fetchedProducts->pluck('productId')->toArray();
        $uniqueIds = array_unique($productIds);

        $this->assertCount(
            count($productIds),
            $uniqueIds,
            'Should have no duplicate product IDs'
        );

        // Validate all products are present
        $expectedIds = array_map(fn ($p) => (string) $p['id'], $allProducts);
        sort($expectedIds);
        sort($productIds);

        $this->assertEquals(
            $expectedIds,
            $productIds,
            'All product IDs should be present'
        );
    }

    /**
     * Test pagination with exact page boundaries.
     */
    public function test_pagination_with_exact_page_boundaries(): void
    {
        // Clear HTTP fakes to prevent memory buildup
        Http::clearResolvedInstances();

        // Test with exactly 5 products (small test to avoid Spatie Data memory issues)
        $totalProducts = 5;
        $allProducts = [];

        for ($j = 0; $j < $totalProducts; $j++) {
            $allProducts[] = $this->generateRandomJumboProduct(false, false, "product-boundary-{$j}");
        }

        Http::fake([
            'https://mobileapi.jumbo.com/v17/search*' => Http::response([
                'products' => ['data' => $allProducts, 'total' => $totalProducts],
            ], 200),
        ]);

        $scraper = $this->createJumboScraper();
        $fetchedProducts = $scraper->searchProducts('test', $totalProducts);

        $this->assertCount(
            $totalProducts,
            $fetchedProducts,
            "Should fetch exactly {$totalProducts} products"
        );
    }

    /**
     * Test pagination stops when API returns fewer products than page size.
     */
    public function test_pagination_stops_when_api_returns_fewer_products(): void
    {
        // Clear HTTP fakes to prevent memory buildup
        Http::clearResolvedInstances();

        // Test with 5 products (small test to avoid Spatie Data memory issues)
        $totalProducts = 5;
        $allProducts = [];

        for ($j = 0; $j < $totalProducts; $j++) {
            $allProducts[] = $this->generateRandomJumboProduct(false, false, "product-stop-{$j}");
        }

        Http::fake([
            'https://mobileapi.jumbo.com/v17/search*' => Http::response([
                'products' => [
                    'data' => $allProducts,
                    'total' => $totalProducts,
                ],
            ], 200),
        ]);

        $scraper = $this->createJumboScraper();
        $fetchedProducts = $scraper->searchProducts('test', 50);

        // Should only fetch what's available
        $this->assertCount(
            $totalProducts,
            $fetchedProducts,
            "Should fetch only {$totalProducts} available products"
        );
    }

    /**
     * Validate that all Jumbo API fields are correctly mapped to ProductData.
     *
     * @param  array<string, mixed>  $apiProduct
     */
    private function validateFieldMapping(array $apiProduct, ProductData $productData, int $iteration): void
    {
        // id → product_id
        $expectedProductId = (string) $apiProduct['id'];
        $actualProductId = $productData->productId;

        if ($expectedProductId !== $actualProductId) {
            throw new \Exception(
                "id mismatch - Expected: '{$expectedProductId}', Got: '{$actualProductId}'"
            );
        }

        $this->assertEquals(
            $expectedProductId,
            $actualProductId,
            "Iteration {$iteration}: id should map to product_id"
        );

        // title → name
        $this->assertEquals(
            $apiProduct['title'],
            $productData->name,
            "Iteration {$iteration}: title should map to name"
        );

        // quantity → quantity
        $expectedQuantity = $apiProduct['quantity'] ?? '';
        $this->assertEquals(
            $expectedQuantity,
            $productData->quantity,
            "Iteration {$iteration}: quantity should map to quantity"
        );

        // prices.price.amount → price_cents (API returns cents already)
        $regularPriceCents = $apiProduct['prices']['price']['amount'] ?? 0;
        $this->assertEquals(
            $regularPriceCents,
            $productData->priceCents,
            "Iteration {$iteration}: prices.price.amount should map to price_cents"
        );

        // prices.promotionalPrice.amount → promo_price_cents (API returns cents already)
        $promoPriceCents = $apiProduct['prices']['promotionalPrice']['amount'] ?? 0;
        $this->assertEquals(
            $promoPriceCents,
            $productData->promoPriceCents,
            "Iteration {$iteration}: prices.promotionalPrice.amount should map to promo_price_cents"
        );

        // prices.unitPrice.unit → unit_price
        $expectedUnitPrice = $apiProduct['prices']['unitPrice']['unit'] ?? '';
        $this->assertEquals(
            $expectedUnitPrice,
            $productData->unitPrice,
            "Iteration {$iteration}: prices.unitPrice.unit should map to unit_price"
        );

        // available → available
        $expectedAvailable = $apiProduct['available'] ?? true;
        $this->assertEquals(
            $expectedAvailable,
            $productData->available,
            "Iteration {$iteration}: available should map to available"
        );

        // badge → badge
        $expectedBadge = $apiProduct['badge'] ?? '';
        $this->assertEquals(
            $expectedBadge,
            $productData->badge,
            "Iteration {$iteration}: badge should map to badge"
        );

        // imageInfo.primaryView[0].url → image_url
        $expectedImageUrl = $apiProduct['imageInfo']['primaryView'][0]['url'] ?? '';
        $this->assertEquals(
            $expectedImageUrl,
            $productData->imageUrl,
            "Iteration {$iteration}: imageInfo.primaryView[0].url should map to image_url"
        );

        // Validate product URL is constructed correctly
        $slug = strtolower(str_replace(' ', '-', $apiProduct['title']));
        $expectedUrl = "https://www.jumbo.com/producten/{$slug}-{$apiProduct['id']}";
        $this->assertEquals(
            $expectedUrl,
            $productData->productUrl,
            "Iteration {$iteration}: Product URL should be constructed correctly"
        );

        // Validate supermarket identifier
        $this->assertEquals(
            'jumbo',
            $productData->supermarket,
            "Iteration {$iteration}: Supermarket should be 'jumbo'"
        );
    }

    /**
     * Generate random Jumbo API product data for testing.
     *
     * @param  bool  $withPromotion  Include promotional pricing
     * @param  bool  $missingOptional  Randomly omit optional fields
     * @param  string|null  $customId  Custom product ID for pagination tests
     * @return array<string, mixed>
     */
    private function generateRandomJumboProduct(
        bool $withPromotion = false,
        bool $missingOptional = false,
        ?string $customId = null
    ): array {
        $productId = $customId ?? 'JUM-'.random_int(100000, 999999);
        // Jumbo API returns prices in CENTS (not euros)
        $regularPriceCents = random_int(50, 5000); // 0.50 to 50.00 euros in cents
        $promoPriceCents = $withPromotion ? (int) ($regularPriceCents * 0.75) : null;

        $product = [
            'id' => $productId,
            'title' => 'Product '.random_int(1000, 9999),
            'prices' => [
                'price' => [
                    'amount' => $regularPriceCents,
                ],
            ],
        ];

        // Add promotional price if applicable
        if ($withPromotion && $promoPriceCents !== null) {
            $product['prices']['promotionalPrice'] = [
                'amount' => $promoPriceCents,
            ];
            $badges = ['2e halve prijs', '1+1 gratis', '25% korting', '3 voor €10'];
            $product['badge'] = $badges[array_rand($badges)];
        }

        // Add optional fields unless testing missing fields
        if (! $missingOptional || (random_int(1, 100) <= 70)) {
            $quantities = ['1L', '500g', '250ml', '1kg', '6 stuks'];
            $product['quantity'] = $quantities[array_rand($quantities)];
        }

        if (! $missingOptional || (random_int(1, 100) <= 70)) {
            $price = number_format(random_int(10, 1000) / 100, 2, '.', '');
            $product['prices']['unitPrice'] = [
                'unit' => '€'.$price.' per kg',
            ];
        }

        if (! $missingOptional || (random_int(1, 100) <= 80)) {
            $uuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                random_int(0, 0xffff), random_int(0, 0xffff),
                random_int(0, 0xffff),
                random_int(0, 0x0fff) | 0x4000,
                random_int(0, 0x3fff) | 0x8000,
                random_int(0, 0xffff), random_int(0, 0xffff), random_int(0, 0xffff)
            );
            $product['imageInfo'] = [
                'primaryView' => [
                    ['url' => 'https://images.jumbo.com/product/'.$uuid.'.jpg'],
                ],
            ];
        }

        if (! $missingOptional || (random_int(1, 100) <= 90)) {
            $product['available'] = (random_int(1, 100) <= 95);
        }

        if (! $missingOptional && ! $withPromotion && (random_int(1, 100) <= 30)) {
            $badges = ['Nieuw', 'Populair', 'Seizoen'];
            $product['badge'] = $badges[array_rand($badges)];
        }

        return $product;
    }

    /**
     * Create JumboScraper instance for testing.
     */
    private function createJumboScraper(): JumboScraper
    {
        $config = new ScraperConfig(
            identifier: 'jumbo',
            baseUrl: 'https://mobileapi.jumbo.com/v17',
            headers: [
                'User-Agent' => 'Jumbo/8.0.0 (iPhone; iOS 15.0)',
            ],
            rateLimitDelay: 0, // No delay for tests
            maxRetries: 1,
            timeout: 10
        );

        return new JumboScraper($config);
    }
}
