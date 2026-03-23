<?php

namespace Tests\Feature\Models;

use App\Models\Category;
use App\Models\NormalizedCategory;
use App\Models\Product;
use App\Models\Supermarket;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test NormalizedCategory model relationships and methods.
 */
class NormalizedCategoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test normalized category can be created with required fields.
     */
    public function test_normalized_category_can_be_created(): void
    {
        $category = NormalizedCategory::create([
            'name' => 'Dairy Products',
            'slug' => 'dairy-products',
            'description' => 'All dairy products',
        ]);

        $this->assertDatabaseHas('normalized_categories', [
            'name' => 'Dairy Products',
            'slug' => 'dairy-products',
        ]);
    }

    /**
     * Test parent relationship works correctly.
     */
    public function test_parent_relationship(): void
    {
        $parentCategory = NormalizedCategory::factory()->create(['name' => 'Food']);
        $childCategory = NormalizedCategory::factory()->create([
            'name' => 'Dairy',
            'parent_id' => $parentCategory->id,
        ]);

        $this->assertInstanceOf(NormalizedCategory::class, $childCategory->parent);
        $this->assertEquals('Food', $childCategory->parent->name);
    }

    /**
     * Test children relationship works correctly.
     */
    public function test_children_relationship(): void
    {
        $parentCategory = NormalizedCategory::factory()->create(['name' => 'Food']);

        $child1 = NormalizedCategory::factory()->create([
            'name' => 'Dairy',
            'parent_id' => $parentCategory->id,
        ]);

        $child2 = NormalizedCategory::factory()->create([
            'name' => 'Meat',
            'parent_id' => $parentCategory->id,
        ]);

        $this->assertCount(2, $parentCategory->children);
        $this->assertTrue($parentCategory->children->contains($child1));
        $this->assertTrue($parentCategory->children->contains($child2));
    }

    /**
     * Test categories relationship works correctly.
     */
    public function test_categories_relationship(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $normalizedCategory = NormalizedCategory::factory()->create();
        $category = Category::factory()->create(['supermarket' => 'ah']);

        $normalizedCategory->categories()->attach($category->id, ['mapped_by' => 'manual']);

        $this->assertCount(1, $normalizedCategory->categories);
        $this->assertEquals($category->id, $normalizedCategory->categories->first()->id);
        $this->assertEquals('manual', $normalizedCategory->categories->first()->pivot->mapped_by);
    }

    /**
     * Test products method returns products from all mapped categories.
     */
    public function test_products_method_returns_products_from_mapped_categories(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $normalizedCategory = NormalizedCategory::factory()->create();

        $category1 = Category::factory()->create(['supermarket' => 'ah']);
        $category2 = Category::factory()->create(['supermarket' => 'ah']);

        $product1 = Product::factory()->create(['supermarket' => 'ah']);
        $product2 = Product::factory()->create(['supermarket' => 'ah']);

        $category1->products()->attach($product1->id);
        $category2->products()->attach($product2->id);

        $normalizedCategory->categories()->attach($category1->id, ['mapped_by' => 'manual']);
        $normalizedCategory->categories()->attach($category2->id, ['mapped_by' => 'manual']);

        $products = $normalizedCategory->products();

        $this->assertCount(2, $products);
        $this->assertTrue($products->contains('id', $product1->id));
        $this->assertTrue($products->contains('id', $product2->id));
    }

    /**
     * Test products method returns unique products.
     */
    public function test_products_method_returns_unique_products(): void
    {
        $supermarket = Supermarket::factory()->create(['identifier' => 'ah']);
        $normalizedCategory = NormalizedCategory::factory()->create();

        $category1 = Category::factory()->create(['supermarket' => 'ah']);
        $category2 = Category::factory()->create(['supermarket' => 'ah']);

        $product = Product::factory()->create(['supermarket' => 'ah']);

        // Same product in both categories
        $category1->products()->attach($product->id);
        $category2->products()->attach($product->id);

        $normalizedCategory->categories()->attach($category1->id, ['mapped_by' => 'manual']);
        $normalizedCategory->categories()->attach($category2->id, ['mapped_by' => 'manual']);

        $products = $normalizedCategory->products();

        $this->assertCount(1, $products);
        $this->assertEquals($product->id, $products->first()->id);
    }

    /**
     * Test slug is unique.
     */
    public function test_slug_is_unique(): void
    {
        $this->expectException(QueryException::class);

        NormalizedCategory::create([
            'name' => 'Dairy',
            'slug' => 'dairy',
        ]);

        // This should fail due to unique constraint
        NormalizedCategory::create([
            'name' => 'Dairy Products',
            'slug' => 'dairy',
        ]);
    }

    /**
     * Test cascade delete when parent is deleted.
     */
    public function test_cascade_delete_when_parent_deleted(): void
    {
        $parentCategory = NormalizedCategory::factory()->create();
        $childCategory = NormalizedCategory::factory()->create(['parent_id' => $parentCategory->id]);

        $this->assertDatabaseHas('normalized_categories', ['id' => $childCategory->id]);

        $parentCategory->delete();

        $this->assertDatabaseMissing('normalized_categories', ['id' => $childCategory->id]);
    }

    /**
     * Test hierarchical normalized category structure (3 levels).
     */
    public function test_hierarchical_normalized_category_structure(): void
    {
        $level1 = NormalizedCategory::factory()->create(['name' => 'Food']);
        $level2 = NormalizedCategory::factory()->create([
            'name' => 'Dairy',
            'parent_id' => $level1->id,
        ]);
        $level3 = NormalizedCategory::factory()->create([
            'name' => 'Milk',
            'parent_id' => $level2->id,
        ]);

        $this->assertEquals($level1->id, $level2->parent->id);
        $this->assertEquals($level2->id, $level3->parent->id);
        $this->assertCount(1, $level1->children);
        $this->assertCount(1, $level2->children);
    }

    /**
     * Test products from multiple supermarkets can be retrieved.
     */
    public function test_products_from_multiple_supermarkets(): void
    {
        $supermarket1 = Supermarket::factory()->create(['identifier' => 'ah']);
        $supermarket2 = Supermarket::factory()->create(['identifier' => 'jumbo']);

        $normalizedCategory = NormalizedCategory::factory()->create();

        $category1 = Category::factory()->create(['supermarket' => 'ah']);
        $category2 = Category::factory()->create(['supermarket' => 'jumbo']);

        $product1 = Product::factory()->create(['supermarket' => 'ah']);
        $product2 = Product::factory()->create(['supermarket' => 'jumbo']);

        $category1->products()->attach($product1->id);
        $category2->products()->attach($product2->id);

        $normalizedCategory->categories()->attach($category1->id, ['mapped_by' => 'manual']);
        $normalizedCategory->categories()->attach($category2->id, ['mapped_by' => 'manual']);

        $products = $normalizedCategory->products();

        $this->assertCount(2, $products);
        $this->assertTrue($products->contains('id', $product1->id));
        $this->assertTrue($products->contains('id', $product2->id));
    }
}
