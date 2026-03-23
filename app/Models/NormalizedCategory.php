<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * NormalizedCategory model representing a standardized product category.
 *
 * Supports hierarchical categories with parent-child relationships.
 * Recommended to keep hierarchy shallow (max 2-3 levels) for optimal performance.
 * Example: Dairy > Milk > Whole Milk
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property int|null $parent_id
 * @property string|null $description
 */
class NormalizedCategory extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'description',
        'keywords',
    ];

    /**
     * Get the parent normalized category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(__CLASS__, 'parent_id');
    }

    /**
     * Get all child normalized categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(__CLASS__, 'parent_id');
    }

    /**
     * Get all supermarket-specific categories mapped to this normalized category.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(
            Category::class,
            'category_mappings',
            'normalized_category_id',
            'category_id'
        )->withPivot('mapped_by')->withTimestamps();
    }

    /**
     * Get all products in this normalized category across all supermarkets.
     */
    public function products(): Collection
    {
        return $this->categories()
            ->with('products')
            ->get()
            ->pluck('products')
            ->flatten()
            ->unique('id');
    }
}
