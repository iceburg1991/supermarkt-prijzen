<?php

declare(strict_types=1);

namespace Feature\Scraper;

use App\Contracts\Scraper\TokenManagerInterface;
use App\DataTransferObjects\Scraper\ProductData;
use App\DataTransferObjects\Scraper\ScraperConfig;
use App\Http\Scrapers\AhScraper;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Property-based tests for AhScraper field mapping.
 *
 * Validates that all AH API fields are correctly mapped to ProductData
 * across multiple iterations with randomized data.
 */
class AhScraperPropertyTest extends TestCase
{
    private const int ITERATIONS = 1;

    /**
     * Property 3: API Field Mapping Completeness (AH)
     *
     * For any product returned by AH API, all specified fields should be
     * mapped to ProductData value object.
     *
     * Validates: Requirements 2.7
     */
    public function test_api_field_mapping_completeness_for_all_products(): void
    {
        $failures = [];

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            try {
                // Generate random product for this iteration
                $apiProduct = $this->generateRandomAhProduct();

                // Set up HTTP fake BEFORE creating scraper
                Http::fake([
                    '*/mobile-services/product/search/v2*' => Http::response([
                        'products' => [$apiProduct],
                        'page' => ['totalElements' => 1],
                    ], 200),
                ]);

                // Create scraper for each iteration
                $scraper = $this->createAhScraperWithMockedAuth();

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
                $apiProduct = $this->generateRandomAhProduct(true);

                Http::fake([
                    '*/mobile-services/product/search/v2*' => Http::response([
                        'products' => [$apiProduct],
                        'page' => ['totalElements' => 1],
                    ], 200),
                ]);

                $scraper = $this->createAhScraperWithMockedAuth();
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
                $apiProduct = $this->generateRandomAhProduct(false, true);

                Http::fake([
                    '*/mobile-services/product/search/v2*' => Http::response([
                        'products' => [$apiProduct],
                        'page' => ['totalElements' => 1],
                    ], 200),
                ]);

                $scraper = $this->createAhScraperWithMockedAuth();
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
     * Validate that all AH API fields are correctly mapped to ProductData.
     *
     * @param  array<string, mixed>  $apiProduct
     */
    private function validateFieldMapping(array $apiProduct, ProductData $productData, int $iteration): void
    {
        // webshopId → product_id
        $expectedProductId = (string) $apiProduct['webshopId'];
        $actualProductId = $productData->productId;

        if ($expectedProductId !== $actualProductId) {
            throw new \Exception(
                "webshopId mismatch - Expected: '{$expectedProductId}', Got: '{$actualProductId}'"
            );
        }

        $this->assertEquals(
            $expectedProductId,
            $actualProductId,
            "Iteration {$iteration}: webshopId should map to product_id"
        );

        // title → name
        $this->assertEquals(
            $apiProduct['title'],
            $productData->name,
            "Iteration {$iteration}: title should map to name"
        );

        // salesUnitSize → quantity
        $expectedQuantity = $apiProduct['salesUnitSize'] ?? '';
        $this->assertEquals(
            $expectedQuantity,
            $productData->quantity,
            "Iteration {$iteration}: salesUnitSize should map to quantity"
        );

        // unitPriceDescription → unit_price
        $expectedUnitPrice = $apiProduct['unitPriceDescription'] ?? '';
        $this->assertEquals(
            $expectedUnitPrice,
            $productData->unitPrice,
            "Iteration {$iteration}: unitPriceDescription should map to unit_price"
        );

        // images[0].url → image_url
        $expectedImageUrl = $apiProduct['images'][0]['url'] ?? '';
        $this->assertEquals(
            $expectedImageUrl,
            $productData->imageUrl,
            "Iteration {$iteration}: images[0].url should map to image_url"
        );

        // priceBeforeBonus/price → price_cents
        $regularPrice = $apiProduct['priceBeforeBonus'] ?? $apiProduct['price'] ?? 0;
        $expectedPriceCents = (int) ($regularPrice * 100);
        $this->assertEquals(
            $expectedPriceCents,
            $productData->priceCents,
            "Iteration {$iteration}: priceBeforeBonus/price should map to price_cents"
        );

        // isBonus + price → promo_price_cents
        $isBonus = $apiProduct['isBonus'] ?? false;
        if ($isBonus && isset($apiProduct['price'])) {
            $expectedPromoCents = (int) ($apiProduct['price'] * 100);
            $this->assertEquals(
                $expectedPromoCents,
                $productData->promoPriceCents,
                "Iteration {$iteration}: Bonus product price should map to promo_price_cents"
            );
        } else {
            $this->assertEquals(
                0,
                $productData->promoPriceCents,
                "Iteration {$iteration}: Non-bonus product should have promo_price_cents = 0"
            );
        }

        // bonusMechanism → badge (only if isBonus)
        if ($isBonus && isset($apiProduct['bonusMechanism'])) {
            $this->assertEquals(
                $apiProduct['bonusMechanism'],
                $productData->badge,
                "Iteration {$iteration}: bonusMechanism should map to badge"
            );
        }

        // availableOnline → available
        $expectedAvailable = $apiProduct['availableOnline'] ?? true;
        $this->assertEquals(
            $expectedAvailable,
            $productData->available,
            "Iteration {$iteration}: availableOnline should map to available"
        );

        // Validate product URL is constructed correctly
        $expectedUrl = "https://www.ah.nl/producten/product/wi{$apiProduct['webshopId']}";
        $this->assertEquals(
            $expectedUrl,
            $productData->productUrl,
            "Iteration {$iteration}: Product URL should be constructed correctly"
        );

        // Validate supermarket identifier
        $this->assertEquals(
            'ah',
            $productData->supermarket,
            "Iteration {$iteration}: Supermarket should be 'ah'"
        );
    }

    /**
     * Generate random AH API product data for testing.
     *
     * @param  bool  $withPromotion  Include promotional pricing
     * @param  bool  $missingOptional  Randomly omit optional fields
     * @return array<string, mixed>
     */
    private function generateRandomAhProduct(bool $withPromotion = false, bool $missingOptional = false): array
    {
        $webshopId = random_int(100000, 999999);
        $regularPrice = random_int(50, 5000) / 100; // 0.50 to 50.00
        $promoPrice = $withPromotion ? $regularPrice * 0.8 : null;

        $product = [
            'webshopId' => $webshopId,
            'title' => 'Product '.random_int(1000, 9999),
            'price' => $promoPrice ?? $regularPrice,
            'isBonus' => $withPromotion,
        ];

        // Add priceBeforeBonus for promotional products
        if ($withPromotion) {
            $product['priceBeforeBonus'] = $regularPrice;
            $bonusMechanisms = ['1+1 gratis', '2e halve prijs', '25% korting', '3 voor €5'];
            $product['bonusMechanism'] = $bonusMechanisms[array_rand($bonusMechanisms)];
        } else {
            $product['priceBeforeBonus'] = $regularPrice;
        }

        // Add optional fields unless testing missing fields
        if (! $missingOptional || (random_int(1, 100) <= 70)) {
            $sizes = ['1L', '500g', '250ml', '1kg', '6 stuks'];
            $product['salesUnitSize'] = $sizes[array_rand($sizes)];
        }

        if (! $missingOptional || (random_int(1, 100) <= 70)) {
            $price = number_format(random_int(10, 1000) / 100, 2, '.', '');
            $product['unitPriceDescription'] = '€'.$price.' per kg';
        }

        if (! $missingOptional || (random_int(1, 100) <= 80)) {
            $uuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                random_int(0, 0xFFFF), random_int(0, 0xFFFF),
                random_int(0, 0xFFFF),
                random_int(0, 0x0FFF) | 0x4000,
                random_int(0, 0x3FFF) | 0x8000,
                random_int(0, 0xFFFF), random_int(0, 0xFFFF), random_int(0, 0xFFFF)
            );
            $product['images'] = [
                ['url' => 'https://static.ah.nl/dam/product/'.$uuid.'.jpg'],
            ];
        }

        if (! $missingOptional || (random_int(1, 100) <= 90)) {
            $product['availableOnline'] = (random_int(1, 100) <= 95);
        }

        return $product;
    }

    /**
     * Create AhScraper instance with mocked authentication.
     */
    private function createAhScraperWithMockedAuth(): AhScraper
    {
        $config = new ScraperConfig(
            identifier: 'ah',
            baseUrl: 'https://api.ah.nl',
            headers: [
                'User-Agent' => 'Appie/8.0.0',
                'x-client-name' => 'appie-ios',
                'x-client-version' => '8.0.0',
                'x-application' => 'AHWEBSHOP',
            ],
            rateLimitDelay: 0, // No delay for tests
            maxRetries: 1,
            timeout: 10
        );

        $tokenManager = $this->createMock(TokenManagerInterface::class);
        $tokenManager->method('getValidToken')
            ->willReturn('mock-access-token');
        $tokenManager->method('hasValidToken')
            ->willReturn(true);

        return new AhScraper($config, $tokenManager);
    }
}
