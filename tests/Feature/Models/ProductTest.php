<?php

namespace Tests\Feature\Models;

use App\Models\Category;
use App\Models\NormalizedCategory;
use App\Models\Price;
use App\Models\Product;
use App\Models\Supermarket;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test Product model relationships, casts, and methods.
 */
class ProductTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test product can be created with required fields.
     */
    public function test_product_can_be_created(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        $product = Product::create([
            'product_id' => 'test-123',
            'supermarket' => 'ah',
            'name' => 'Test Product',
            'quantity' => '500g',
            'image_url' => 'https://example.com/image.jpg',
            'product_url' => 'https://example.com/product',
        ]);

        $this->assertDatabaseHas('products', [
            'product_id' => 'test-123',
            'supermarket' => 'ah',
            'name' => 'Test Product',
        ]);
    }

    /**
     * Test unique constraint prevents duplicate products.
     */
    public function test_unique_constraint_prevents_duplicates(): void
    {
        $this->expectException(QueryException::class);

        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        Product::create([
            'product_id' => 'test-123',
            'supermarket' => 'ah',
            'name' => 'Test Product',
        ]);

        // This should fail due to unique constraint
        Product::create([
            'product_id' => 'test-123',
            'supermarket' => 'ah',
            'name' => 'Different Name',
        ]);
    }

    /**
     * Test same product_id can exist for different supermarkets.
     */
    public function test_same_product_id_can_exist_for_different_supermarkets(): void
    {
        Supermarket::factory()->create(['identifier' => 'ah']);
        Supermarket::factory()->create(['identifier' => 'jumbo']);

        $product1 = Product::create([
            'product_id' => 'test-123',
            'supermarket' => 'ah',
            'name' => 'AH Product',
        ]);

        $product2 = Product::create([
            'product_id' => 'test-123',
            'supermarket' => 'jumbo',
            'name' => 'Jumbo Product',
        ]);

        $this->assertNotEquals($product1->id, $product2->id);
        $this->assertEquals('test-123', $product1->product_id);
        $this->assertEquals('test-123', $product2->product_id);
    }

    /**
     * Test prices relationship returns correct prices.
     */
    public function test_prices_relationship(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $product = Product::factory()->create([
            'product_id' => 'test-123',
            'supermarket' => 'ah',
        ]);

        Price::factory()->count(3)->create([
            'product_id' => 'test-123',
            'supermarket' => 'ah',
        ]);

        $this->assertCount(3, $product->prices);
        $this->assertInstanceOf(Price::class, $product->prices->first());
    }

    /**
     * Test latestPrice relationship returns most recent price.
     */
    public function test_latest_price_relationship(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $product = Product::factory()->create([
            'product_id' => 'test-123',
            'supermarket' => 'ah',
        ]);

        Price::factory()->create([
            'product_id' => 'test-123',
            'supermarket' => 'ah',
            'scraped_at' => now()->subDays(2),
            'price_cents' => 100,
        ]);

        $latestPrice = Price::factory()->create([
            'product_id' => 'test-123',
            'supermarket' => 'ah',
            'scraped_at' => now(),
            'price_cents' => 150,
        ]);

        $this->assertEquals($latestPrice->id, $product->latestPrice->id);
        $this->assertEquals(150, $product->latestPrice->price_cents);
    }

    /**
     * Test categories relationship works correctly.
     */
    public function test_categories_relationship(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $product = Product::factory()->create(['supermarket' => 'ah']);
        $category = Category::factory()->create(['supermarket' => 'ah']);

        $product->categories()->attach($category->id);

        $this->assertCount(1, $product->categories);
        $this->assertEquals($category->id, $product->categories->first()->id);
    }

    /**
     * Test normalizedCategories method returns unique normalized categories.
     */
    public function test_normalized_categories_method(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $product = Product::factory()->create(['supermarket' => 'ah']);

        $category1 = Category::factory()->create(['supermarket' => 'ah']);
        $category2 = Category::factory()->create(['supermarket' => 'ah']);

        $normalizedCategory = NormalizedCategory::factory()->create();

        $category1->normalizedCategories()->attach($normalizedCategory->id, ['mapped_by' => 'manual']);
        $category2->normalizedCategories()->attach($normalizedCategory->id, ['mapped_by' => 'manual']);

        $product->categories()->attach([$category1->id, $category2->id]);

        $normalizedCategories = $product->normalizedCategories();

        $this->assertCount(1, $normalizedCategories);
        $this->assertEquals($normalizedCategory->id, $normalizedCategories->first()->id);
    }

    /**
     * Test supermarketModel relationship works correctly.
     */
    public function test_supermarket_model_relationship(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah', 'name' => 'Albert Heijn']);
        $product = Product::factory()->create(['supermarket' => 'ah']);

        $this->assertInstanceOf(Supermarket::class, $product->supermarketModel);
        $this->assertEquals('Albert Heijn', $product->supermarketModel->name);
    }

    /**
     * Test foreign key constraint on supermarket.
     */
    public function test_foreign_key_constraint_on_supermarket(): void
    {
        $this->expectException(QueryException::class);

        // Try to create product with non-existent supermarket
        Product::create([
            'product_id' => 'test-123',
            'supermarket' => 'nonexistent',
            'name' => 'Test Product',
        ]);
    }

    /**
     * Test cascade delete when supermarket is deleted.
     */
    public function test_cascade_delete_when_supermarket_deleted(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $product = Product::factory()->create(['supermarket' => 'ah']);

        $this->assertDatabaseHas('products', ['id' => $product->id]);

        $supermarket->delete();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }
}
