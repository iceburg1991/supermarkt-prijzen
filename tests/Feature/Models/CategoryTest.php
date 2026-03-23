<?php

namespace Feature\Models;

use App\Models\Category;
use App\Models\NormalizedCategory;
use App\Models\Product;
use App\Models\Supermarket;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test Category model relationships, casts, and methods.
 */
class CategoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test category can be created with required fields.
     */
    public function test_category_can_be_created(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        $category = Category::create([
            'supermarket' => 'ah',
            'category_id' => 'cat-123',
            'name' => 'Dairy Products',
        ]);

        $this->assertDatabaseHas('categories', [
            'supermarket' => 'ah',
            'category_id' => 'cat-123',
            'name' => 'Dairy Products',
        ]);
    }

    /**
     * Test last_scraped_at is cast to datetime.
     */
    public function test_last_scraped_at_cast_to_datetime(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        $category = Category::factory()->create([
            'supermarket' => 'ah',
            'last_scraped_at' => '2024-01-15 10:00:00',
        ]);

        $this->assertInstanceOf(CarbonImmutable::class, $category->last_scraped_at);
        $this->assertEquals('2024-01-15 10:00:00', $category->last_scraped_at->format('Y-m-d H:i:s'));
    }

    /**
     * Test parent relationship works correctly.
     */
    public function test_parent_relationship(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        $parentCategory = Category::factory()->create([
            'supermarket' => 'ah',
            'name' => 'Food',
        ]);

        $childCategory = Category::factory()->create([
            'supermarket' => 'ah',
            'name' => 'Dairy',
            'parent_id' => $parentCategory->id,
        ]);

        $this->assertInstanceOf(Category::class, $childCategory->parent);
        $this->assertEquals('Food', $childCategory->parent->name);
    }

    /**
     * Test children relationship works correctly.
     */
    public function test_children_relationship(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        $parentCategory = Category::factory()->create([
            'supermarket' => 'ah',
            'name' => 'Food',
        ]);

        $child1 = Category::factory()->create([
            'supermarket' => 'ah',
            'name' => 'Dairy',
            'parent_id' => $parentCategory->id,
        ]);

        $child2 = Category::factory()->create([
            'supermarket' => 'ah',
            'name' => 'Meat',
            'parent_id' => $parentCategory->id,
        ]);

        $this->assertCount(2, $parentCategory->children);
        $this->assertTrue($parentCategory->children->contains($child1));
        $this->assertTrue($parentCategory->children->contains($child2));
    }

    /**
     * Test products relationship works correctly.
     */
    public function test_products_relationship(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $category = Category::factory()->create(['supermarket' => 'ah']);
        $product = Product::factory()->create(['supermarket' => 'ah']);

        $category->products()->attach($product->id);

        $this->assertCount(1, $category->products);
        $this->assertEquals($product->id, $category->products->first()->id);
    }

    /**
     * Test normalizedCategories relationship works correctly.
     */
    public function test_normalized_categories_relationship(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $category = Category::factory()->create(['supermarket' => 'ah']);
        $normalizedCategory = NormalizedCategory::factory()->create();

        $category->normalizedCategories()->attach($normalizedCategory->id, ['mapped_by' => 'manual']);

        $this->assertCount(1, $category->normalizedCategories);
        $this->assertEquals($normalizedCategory->id, $category->normalizedCategories->first()->id);
        $this->assertEquals('manual', $category->normalizedCategories->first()->pivot->mapped_by);
    }

    /**
     * Test supermarketModel relationship works correctly.
     */
    public function test_supermarket_model_relationship(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah', 'name' => 'Albert Heijn']);
        $category = Category::factory()->create(['supermarket' => 'ah']);

        $this->assertInstanceOf(Supermarket::class, $category->supermarketModel);
        $this->assertEquals('Albert Heijn', $category->supermarketModel->name);
    }

    /**
     * Test unique constraint on supermarket and category_id.
     */
    public function test_unique_constraint_on_supermarket_and_category_id(): void
    {
        $this->expectException(QueryException::class);

        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        Category::create([
            'supermarket' => 'ah',
            'category_id' => 'cat-123',
            'name' => 'Dairy',
        ]);

        // This should fail due to unique constraint
        Category::create([
            'supermarket' => 'ah',
            'category_id' => 'cat-123',
            'name' => 'Different Name',
        ]);
    }

    /**
     * Test same category_id can exist for different supermarkets.
     */
    public function test_same_category_id_can_exist_for_different_supermarkets(): void
    {
        Supermarket::factory()->create(['identifier' => 'ah']);
        Supermarket::factory()->create(['identifier' => 'jumbo']);

        $category1 = Category::create([
            'supermarket' => 'ah',
            'category_id' => 'cat-123',
            'name' => 'AH Dairy',
        ]);

        $category2 = Category::create([
            'supermarket' => 'jumbo',
            'category_id' => 'cat-123',
            'name' => 'Jumbo Dairy',
        ]);

        $this->assertNotEquals($category1->id, $category2->id);
        $this->assertEquals('cat-123', $category1->category_id);
        $this->assertEquals('cat-123', $category2->category_id);
    }

    /**
     * Test foreign key constraint on supermarket.
     */
    public function test_foreign_key_constraint_on_supermarket(): void
    {
        $this->expectException(QueryException::class);

        // Try to create category with non-existent supermarket
        Category::create([
            'supermarket' => 'nonexistent',
            'category_id' => 'cat-123',
            'name' => 'Test Category',
        ]);
    }

    /**
     * Test cascade delete when supermarket is deleted.
     */
    public function test_cascade_delete_when_supermarket_deleted(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $category = Category::factory()->create(['supermarket' => 'ah']);

        $this->assertDatabaseHas('categories', ['id' => $category->id]);

        $supermarket->delete();

        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    }

    /**
     * Test cascade delete when parent category is deleted.
     */
    public function test_cascade_delete_when_parent_deleted(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        $parentCategory = Category::factory()->create(['supermarket' => 'ah']);
        $childCategory = Category::factory()->create([
            'supermarket' => 'ah',
            'parent_id' => $parentCategory->id,
        ]);

        $this->assertDatabaseHas('categories', ['id' => $childCategory->id]);

        $parentCategory->delete();

        $this->assertDatabaseMissing('categories', ['id' => $childCategory->id]);
    }

    /**
     * Test hierarchical category structure (3 levels).
     */
    public function test_hierarchical_category_structure(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);

        $level1 = Category::factory()->create([
            'supermarket' => 'ah',
            'name' => 'Food',
        ]);

        $level2 = Category::factory()->create([
            'supermarket' => 'ah',
            'name' => 'Dairy',
            'parent_id' => $level1->id,
        ]);

        $level3 = Category::factory()->create([
            'supermarket' => 'ah',
            'name' => 'Milk',
            'parent_id' => $level2->id,
        ]);

        $this->assertEquals($level1->id, $level2->parent->id);
        $this->assertEquals($level2->id, $level3->parent->id);
        $this->assertCount(1, $level1->children);
        $this->assertCount(1, $level2->children);
    }
}
