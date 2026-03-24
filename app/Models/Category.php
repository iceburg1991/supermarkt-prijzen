<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Category model representing a supermarket-specific product category.
 *
 * Supports hierarchical categories with parent-child relationships.
 * In practice, supermarkets typically use 2-3 levels (e.g., Dairy > Milk > Whole Milk).
 * The database structure supports unlimited depth, but deep hierarchies may impact performance.
 *
 * @property int $id
 * @property string $supermarket
 * @property string $category_id
 * @property string $name
 * @property int|null $parent_id
 * @property Carbon|null $last_scraped_at
 */
class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'supermarket',
        'category_id',
        'name',
        'parent_id',
        'last_scraped_at',
    ];

    protected $casts = [
        'last_scraped_at' => 'datetime',
    ];

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get all child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Get all products in this category.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_categories');
    }

    /**
     * Get all normalized categories mapped to this category.
     */
    public function normalizedCategories(): BelongsToMany
    {
        return $this->belongsToMany(
            NormalizedCategory::class,
            'category_mappings',
            'category_id',
            'normalized_category_id'
        )->withPivot('mapped_by')->withTimestamps();
    }

    /**
     * Get the supermarket this category belongs to.
     */
    public function supermarketModel(): BelongsTo
    {
        return $this->belongsTo(Supermarket::class, 'supermarket', 'identifier');
    }
}
