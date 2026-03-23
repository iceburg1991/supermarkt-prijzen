<?php

namespace Tests\Feature\Models;

use App\Models\Price;
use App\Models\Product;
use App\Models\Supermarket;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test Price model relationships, casts, and methods.
 */
class PriceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test price can be created with required fields.
     */
    public function test_price_can_be_created(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $product = Product::factory()->create([
            'product_id' => 'test-123',
            'supermarket' => 'ah',
        ]);

        $price = Price::create([
            'product_id' => 'test-123',
            'supermarket' => 'ah',
            'price_cents' => 299,
            'promo_price_cents' => 0,
            'available' => true,
            'scraped_at' => now(),
        ]);

        $this->assertDatabaseHas('prices', [
            'product_id' => 'test-123',
            'supermarket' => 'ah',
            'price_cents' => 299,
        ]);
    }

    /**
     * Test scraped_at is cast to datetime.
     */
    public function test_scraped_at_cast_to_datetime(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $product = Product::factory()->create(['supermarket' => 'ah']);

        $price = Price::factory()->create([
            'product_id' => $product->product_id,
            'supermarket' => 'ah',
            'scraped_at' => '2024-01-15 10:30:00',
        ]);

        $this->assertInstanceOf(CarbonImmutable::class, $price->scraped_at);
        $this->assertEquals('2024-01-15 10:30:00', $price->scraped_at->format('Y-m-d H:i:s'));
    }

    /**
     * Test available is cast to boolean.
     */
    public function test_available_cast_to_boolean(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $product = Product::factory()->create(['supermarket' => 'ah']);

        $price = Price::factory()->create([
            'product_id' => $product->product_id,
            'supermarket' => 'ah',
            'available' => 1,
        ]);

        $this->assertIsBool($price->available);
        $this->assertTrue($price->available);
    }

    /**
     * Test price_cents is cast to integer.
     */
    public function test_price_cents_cast_to_integer(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $product = Product::factory()->create(['supermarket' => 'ah']);

        $price = Price::factory()->create([
            'product_id' => $product->product_id,
            'supermarket' => 'ah',
            'price_cents' => '299',
        ]);

        $this->assertIsInt($price->price_cents);
        $this->assertEquals(299, $price->price_cents);
    }

    /**
     * Test promo_price_cents is cast to integer.
     */
    public function test_promo_price_cents_cast_to_integer(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $product = Product::factory()->create(['supermarket' => 'ah']);

        $price = Price::factory()->create([
            'product_id' => $product->product_id,
            'supermarket' => 'ah',
            'promo_price_cents' => '199',
        ]);

        $this->assertIsInt($price->promo_price_cents);
        $this->assertEquals(199, $price->promo_price_cents);
    }

    /**
     * Test product relationship works correctly.
     */
    public function test_product_relationship(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $product = Product::factory()->create([
            'product_id' => 'test-123',
            'supermarket' => 'ah',
            'name' => 'Test Product',
        ]);

        $price = Price::factory()->create([
            'product_id' => 'test-123',
            'supermarket' => 'ah',
        ]);

        $this->assertInstanceOf(Product::class, $price->product);
        $this->assertEquals('Test Product', $price->product->name);
    }

    /**
     * Test hasPromotion returns true when promo_price_cents > 0.
     */
    public function test_has_promotion_returns_true_when_promo_exists(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $product = Product::factory()->create(['supermarket' => 'ah']);

        $price = Price::factory()->create([
            'product_id' => $product->product_id,
            'supermarket' => 'ah',
            'price_cents' => 299,
            'promo_price_cents' => 199,
        ]);

        $this->assertTrue($price->hasPromotion());
    }

    /**
     * Test hasPromotion returns false when promo_price_cents is 0.
     */
    public function test_has_promotion_returns_false_when_no_promo(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $product = Product::factory()->create(['supermarket' => 'ah']);

        $price = Price::factory()->create([
            'product_id' => $product->product_id,
            'supermarket' => 'ah',
            'price_cents' => 299,
            'promo_price_cents' => 0,
        ]);

        $this->assertFalse($price->hasPromotion());
    }

    /**
     * Test getEffectivePrice returns promo price when available.
     */
    public function test_get_effective_price_returns_promo_when_available(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $product = Product::factory()->create(['supermarket' => 'ah']);

        $price = Price::factory()->create([
            'product_id' => $product->product_id,
            'supermarket' => 'ah',
            'price_cents' => 299,
            'promo_price_cents' => 199,
        ]);

        $this->assertEquals(199, $price->getEffectivePrice());
    }

    /**
     * Test getEffectivePrice returns regular price when no promo.
     */
    public function test_get_effective_price_returns_regular_when_no_promo(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $product = Product::factory()->create(['supermarket' => 'ah']);

        $price = Price::factory()->create([
            'product_id' => $product->product_id,
            'supermarket' => 'ah',
            'price_cents' => 299,
            'promo_price_cents' => 0,
        ]);

        $this->assertEquals(299, $price->getEffectivePrice());
    }

    /**
     * Test foreign key constraint on product.
     */
    public function test_foreign_key_constraint_on_product(): void
    {
        $this->expectException(QueryException::class);

        Supermarket::factory()->create(['identifier' => 'ah']);

        // Try to create price with non-existent product
        Price::create([
            'product_id' => 'nonexistent',
            'supermarket' => 'ah',
            'price_cents' => 299,
            'scraped_at' => now(),
        ]);
    }

    /**
     * Test cascade delete when product is deleted.
     */
    public function test_cascade_delete_when_product_deleted(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $product = Product::factory()->create(['supermarket' => 'ah']);
        $price = Price::factory()->create([
            'product_id' => $product->product_id,
            'supermarket' => 'ah',
        ]);

        $this->assertDatabaseHas('prices', ['id' => $price->id]);

        $product->delete();

        $this->assertDatabaseMissing('prices', ['id' => $price->id]);
    }

    /**
     * Test multiple price records can exist for same product (price history).
     */
    public function test_multiple_price_records_for_same_product(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $product = Product::factory()->create(['supermarket' => 'ah']);

        Price::factory()->create([
            'product_id' => $product->product_id,
            'supermarket' => 'ah',
            'price_cents' => 299,
            'scraped_at' => now()->subDays(2),
        ]);

        Price::factory()->create([
            'product_id' => $product->product_id,
            'supermarket' => 'ah',
            'price_cents' => 279,
            'scraped_at' => now()->subDay(),
        ]);

        Price::factory()->create([
            'product_id' => $product->product_id,
            'supermarket' => 'ah',
            'price_cents' => 289,
            'scraped_at' => now(),
        ]);

        $this->assertCount(3, Price::where('product_id', $product->product_id)->get());
    }
}
