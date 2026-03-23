<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * CategoryMapping model representing a mapping between supermarket and normalized categories.
 *
 * @property int $id
 * @property int $category_id
 * @property int $normalized_category_id
 * @property string $mapped_by
 */
class CategoryMapping extends Model
{
    protected $fillable = [
        'category_id',
        'normalized_category_id',
        'mapped_by',
    ];

    /**
     * Get the supermarket-specific category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the normalized category.
     */
    public function normalizedCategory(): BelongsTo
    {
        return $this->belongsTo(NormalizedCategory::class);
    }
}
