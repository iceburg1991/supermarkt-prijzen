<?php

namespace App\Services;

use App\Models\NormalizedCategory;
use Illuminate\Support\Str;

/**
 * Matches products to normalized categories based on keywords and supermarket categories.
 *
 * IMPORTANT: Category matching is optional and should NEVER block product/price storage.
 * If no category match is found, the product should still be saved with its price data.
 * Categories are a nice-to-have feature for analytics, not a requirement for data collection.
 */
class CategoryMatcher
{
    /**
     * Match a product to a normalized category.
     *
     * @param  string  $productName  Product name to match
     * @param  string|null  $supermarketCategory  Supermarket-specific category name
     * @return NormalizedCategory|null Matched normalized category or null
     */
    public function match(string $productName, ?string $supermarketCategory = null): ?NormalizedCategory
    {
        $productNameLower = Str::lower($productName);

        // Try keyword matching first (most specific)
        $category = $this->matchByKeywords($productNameLower);
        if ($category) {
            return $category;
        }

        // Fallback to supermarket category mapping
        if ($supermarketCategory) {
            $category = $this->matchBySupermarketCategory($supermarketCategory);
            if ($category) {
                return $category;
            }
        }

        // No match found
        return null;
    }

    /**
     * Match by keywords in product name.
     */
    protected function matchByKeywords(string $productNameLower): ?NormalizedCategory
    {
        $categories = NormalizedCategory::whereNotNull('keywords')->get();

        foreach ($categories as $category) {
            $keywords = explode(',', Str::lower($category->keywords ?? ''));

            foreach ($keywords as $keyword) {
                $keyword = trim($keyword);
                if ($keyword && Str::contains($productNameLower, $keyword)) {
                    return $category;
                }
            }
        }

        return null;
    }

    /**
     * Match by supermarket category name.
     */
    protected function matchBySupermarketCategory(string $supermarketCategory): ?NormalizedCategory
    {
        $categoryLower = Str::lower($supermarketCategory);

        // Direct mappings for common supermarket categories
        $mappings = [
            // Jumbo categories
            'aardappelen, groente en fruit' => ['vegetables', 'fruits'],
            'zuivel, eieren, boter' => ['dairy', 'eggs'],
            'vleeswaren, kaas en tapas' => ['cheese', 'meat'],
            'vlees, vis en vega' => ['meat', 'fish'],
            'brood en gebak' => ['bread'],
            'ontbijt, broodbeleg en bakproducten' => ['breakfast'],
            'koek, snoep, chocolade en chips' => ['snacks'],
            'koffie en thee' => ['beverages'],
            'frisdrank en sappen' => ['beverages'],
            'bier en wijn' => ['beverages'],
            'conserven, soepen, sauzen, oliën' => ['pantry-staples'],
            'wereldkeukens, kruiden, pasta en rijst' => ['pantry-staples'],
            'verse maaltijden en gemak' => ['frozen-foods'],
            'diepvries' => ['frozen-foods'],
            'drogisterij en baby' => ['personal-care', 'baby-kids'],
            'huishouden en dieren' => ['household', 'pet-supplies'],
            'vega en plantaardig' => ['meat'],

            // AH categories (add as discovered)
            'zuivel' => ['dairy'],
            'kaas' => ['cheese'],
            'vlees' => ['meat'],
            'vis' => ['fish'],
            'groente' => ['vegetables'],
            'fruit' => ['fruits'],
        ];

        // Check if category matches any mapping
        foreach ($mappings as $pattern => $slugs) {
            if (Str::contains($categoryLower, $pattern)) {
                // Return first matching normalized category
                foreach ($slugs as $slug) {
                    $category = NormalizedCategory::where('slug', $slug)->first();
                    if ($category) {
                        return $category;
                    }
                }
            }
        }

        return null;
    }
}
