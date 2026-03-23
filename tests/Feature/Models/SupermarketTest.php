<?php

namespace Tests\Feature\Models;

use App\Models\Category;
use App\Models\Product;
use App\Models\ScrapeRun;
use App\Models\Supermarket;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test Supermarket model relationships, casts, and methods.
 */
class SupermarketTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test supermarket can be created with required fields.
     */
    public function test_supermarket_can_be_created(): void
    {
        $supermarket = Supermarket::create([
            'identifier' => 'ah',
            'name' => 'Albert Heijn',
            'base_url' => 'https://api.ah.nl',
            'requires_auth' => true,
            'enabled' => true,
        ]);

        $this->assertDatabaseHas('supermarkets', [
            'identifier' => 'ah',
            'name' => 'Albert Heijn',
        ]);
    }

    /**
     * Test requires_auth is cast to boolean.
     */
    public function test_requires_auth_cast_to_boolean(): void
    {
        $supermarket = Supermarket::factory()->create(['requires_auth' => 1]);

        $this->assertIsBool($supermarket->requires_auth);
        $this->assertTrue($supermarket->requires_auth);
    }

    /**
     * Test enabled is cast to boolean.
     */
    public function test_enabled_cast_to_boolean(): void
    {
        $supermarket = Supermarket::factory()->create(['enabled' => 0]);

        $this->assertIsBool($supermarket->enabled);
        $this->assertFalse($supermarket->enabled);
    }

    /**
     * Test identifier is unique.
     */
    public function test_identifier_is_unique(): void
    {
        $this->expectException(QueryException::class);

        Supermarket::create([
            'identifier' => 'ah',
            'name' => 'Albert Heijn',
        ]);

        // This should fail due to unique constraint
        Supermarket::create([
            'identifier' => 'ah',
            'name' => 'Different Name',
        ]);
    }

    /**
     * Test products relationship works correctly.
     */
    public function test_products_relationship(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        Product::factory()->count(3)->create(['supermarket' => 'ah']);

        $this->assertCount(3, $supermarket->products);
        $this->assertInstanceOf(Product::class, $supermarket->products->first());
    }

    /**
     * Test scrapeRuns relationship works correctly.
     */
    public function test_scrape_runs_relationship(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        ScrapeRun::factory()->count(2)->create(['supermarket' => 'ah']);

        $this->assertCount(2, $supermarket->scrapeRuns);
        $this->assertInstanceOf(ScrapeRun::class, $supermarket->scrapeRuns->first());
    }

    /**
     * Test categories relationship works correctly.
     */
    public function test_categories_relationship(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        Category::factory()->count(5)->create(['supermarket' => 'ah']);

        $this->assertCount(5, $supermarket->categories);
        $this->assertInstanceOf(Category::class, $supermarket->categories->first());
    }

    /**
     * Test default requires_auth is false.
     */
    public function test_default_requires_auth_is_false(): void
    {
        $supermarket = Supermarket::create([
            'identifier' => 'jumbo',
            'name' => 'Jumbo',
        ]);

        $this->assertFalse($supermarket->fresh()->requires_auth);
    }

    /**
     * Test default enabled is true.
     */
    public function test_default_enabled_is_true(): void
    {
        $supermarket = Supermarket::create([
            'identifier' => 'jumbo',
            'name' => 'Jumbo',
        ]);

        $this->assertTrue($supermarket->fresh()->enabled);
    }

    /**
     * Test cascade delete removes related products.
     */
    public function test_cascade_delete_removes_related_products(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $product = Product::factory()->create(['supermarket' => 'ah']);

        $this->assertDatabaseHas('products', ['id' => $product->id]);

        $supermarket->delete();

        $this->assertDatabaseMissing('products', ['id' => $product->id]);
    }

    /**
     * Test cascade delete removes related scrape runs.
     */
    public function test_cascade_delete_removes_related_scrape_runs(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $scrapeRun = ScrapeRun::factory()->create(['supermarket' => 'ah']);

        $this->assertDatabaseHas('scrape_runs', ['id' => $scrapeRun->id]);

        $supermarket->delete();

        $this->assertDatabaseMissing('scrape_runs', ['id' => $scrapeRun->id]);
    }

    /**
     * Test cascade delete removes related categories.
     */
    public function test_cascade_delete_removes_related_categories(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $category = Category::factory()->create(['supermarket' => 'ah']);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);

        $supermarket->delete();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    /**
     * Test multiple supermarkets can exist.
     */
    public function test_multiple_supermarkets_can_exist(): void
    {
        $ah = Supermarket::factory()->create(['identifier' => 'ah', 'name' => 'Albert Heijn']);
        $jumbo = Supermarket::factory()->create(['identifier' => 'jumbo', 'name' => 'Jumbo']);

        $this->assertCount(2, Supermarket::all());
        $this->assertNotEquals($ah->id, $jumbo->id);
    }

    /**
     * Test supermarket can be disabled.
     */
    public function test_supermarket_can_be_disabled(): void
    {
        $supermarket = Supermarket::factory()->create(['enabled' => true]);

        $this->assertTrue($supermarket->enabled);

        $supermarket->update(['enabled' => false]);

        $this->assertFalse($supermarket->fresh()->enabled);
    }
}
