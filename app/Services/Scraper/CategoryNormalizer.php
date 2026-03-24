<?php

declare(strict_types=1);

namespace App\Services\Scraper;

use App\Infrastructure\Scraper\Repositories\CategoryRepository;
use App\Models\Category;
use App\Models\NormalizedCategory;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Service for normalizing and mapping supermarket categories.
 *
 * Provides category mapping suggestions and cross-supermarket product retrieval.
 */
class CategoryNormalizer
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository
    ) {}

    /**
     * Suggest normalized category for a supermarket category.
     *
     * Uses name and keyword matching to find best normalized category.
     */
    public function suggestMapping(Category $category): ?NormalizedCategory
    {
        $categoryName = Str::lower($category->name);

        // Try exact name match first
        $normalized = NormalizedCategory::whereRaw('LOWER(name) = ?', [$categoryName])->first();

        if ($normalized !== null) {
            return $normalized;
        }

        // Try partial name match
        $normalized = NormalizedCategory::whereRaw('LOWER(name) LIKE ?', ["%{$categoryName}%"])->first();

        if ($normalized !== null) {
            return $normalized;
        }

        // Try keyword matching (simple approach)
        $keywords = $this->extractKeywords($categoryName);

        foreach ($keywords as $keyword) {
            $normalized = NormalizedCategory::whereRaw('LOWER(name) LIKE ?', ["%{$keyword}%"])->first();

            if ($normalized !== null) {
                return $normalized;
            }
        }

        return null;
    }

    /**
     * Create a category mapping.
     *
     * @param  string  $mappedBy  'manual' or 'auto'
     */
    public function createMapping(
        Category $category,
        NormalizedCategory $normalizedCategory,
        string $mappedBy = 'auto'
    ): void {
        $this->categoryRepository->createMapping(
            $category->id,
            $normalizedCategory->id,
            $mappedBy
        );
    }

    /**
     * Approve an automatic mapping (change to manual).
     */
    public function approveMapping(Category $category, NormalizedCategory $normalizedCategory): void
    {
        $this->categoryRepository->createMapping(
            $category->id,
            $normalizedCategory->id,
            'manual'
        );
    }

    /**
     * Get all products across supermarkets for a normalized category.
     */
    public function getProductsByNormalizedCategory(int $normalizedCategoryId): Collection
    {
        return Product::query()
            ->with(['latestPrice', 'supermarketModel'])
            ->whereHas('categories.normalizedCategories', function ($query) use ($normalizedCategoryId) {
                $query->where('normalized_categories.id', $normalizedCategoryId);
            })
            ->get();
    }

    /**
     * Extract keywords from category name for matching.
     *
     * @return array<string>
     */
    private function extractKeywords(string $name): array
    {
        // Remove common words and split
        $stopWords = ['en', 'of', 'voor', 'met', 'de', 'het', 'een', 'and', 'or', 'for', 'with', 'the', 'a'];

        $words = explode(' ', $name);
        $keywords = [];

        foreach ($words as $word) {
            $word = trim($word);
            if (strlen($word) > 2 && ! in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }

        return $keywords;
    }
}
