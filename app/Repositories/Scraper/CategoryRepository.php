<?php

declare(strict_types=1);

namespace App\Repositories\Scraper;

use App\Models\Category;
use App\Models\CategoryMapping;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Repository for category data access.
 *
 * Handles category CRUD operations, mappings, and hierarchical relationships.
 */
class CategoryRepository
{
    /**
     * Upsert category data (update if exists, insert if new).
     */
    public function upsert(
        string $supermarket,
        string $categoryId,
        string $name,
        ?int $parentId = null
    ): Category {
        return Category::updateOrCreate(
            [
                'supermarket' => $supermarket,
                'category_id' => $categoryId,
            ],
            [
                'name' => $name,
                'parent_id' => $parentId,
            ]
        );
    }

    /**
     * Get all categories for a supermarket.
     *
     * Returns categories with their parent-child relationships.
     */
    public function getBySupermarket(string $supermarket): Collection
    {
        return Category::query()
            ->where('supermarket', $supermarket)
            ->with(['parent', 'children'])
            ->get();
    }

    /**
     * Update last_scraped_at timestamp for a category.
     */
    public function updateLastScraped(int $categoryId, Carbon $timestamp): void
    {
        Category::where('id', $categoryId)
            ->update(['last_scraped_at' => $timestamp]);
    }

    /**
     * Get category tree with recursive children.
     *
     * Returns categories with all nested children loaded.
     */
    public function getWithChildren(string $supermarket): Collection
    {
        return Category::query()
            ->where('supermarket', $supermarket)
            ->whereNull('parent_id') // Only root categories
            ->with('children') // Load all children recursively
            ->get();
    }

    /**
     * Create a category mapping to normalized category.
     *
     * @param  int  $categoryId  Supermarket category ID
     * @param  int  $normalizedCategoryId  Normalized category ID
     * @param  string  $mappedBy  'manual' or 'auto'
     */
    public function createMapping(
        int $categoryId,
        int $normalizedCategoryId,
        string $mappedBy = 'auto'
    ): CategoryMapping {
        return CategoryMapping::updateOrCreate(
            [
                'category_id' => $categoryId,
                'normalized_category_id' => $normalizedCategoryId,
            ],
            [
                'mapped_by' => $mappedBy,
            ]
        );
    }
}
