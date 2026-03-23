<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;

/**
 * Product model representing a supermarket product.
 *
 * @property int $id
 * @property string $product_id
 * @property string $supermarket
 * @property string $name
 * @property string|null $quantity
 * @property string|null $image_url
 * @property string|null $product_url
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'supermarket',
        'name',
        'quantity',
        'image_url',
        'product_url',
    ];

    /**
     * Get all price records for this product.
     */
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class, 'product_id', 'product_id')
            ->where('supermarket', '=', $this->attributes['supermarket'] ?? $this->supermarket);
    }

    /**
     * Get the latest price for this product.
     */
    public function latestPrice(): HasOne
    {
        return $this->hasOne(Price::class, 'product_id', 'product_id')
            ->where('supermarket', '=', $this->attributes['supermarket'] ?? $this->supermarket)
            ->latest('scraped_at');
    }

    /**
     * Get the latest price as an attribute (for when relationship doesn't work).
     */
    public function getLatestPriceAttribute(): ?Price
    {
        if (! isset($this->relations['latestPrice'])) {
            return Price::where('product_id', $this->product_id)
                ->where('supermarket', $this->supermarket)
                ->latest('scraped_at')
                ->first();
        }

        return $this->relations['latestPrice'];
    }

    /**
     * Get all categories this product belongs to.
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'product_categories');
    }

    /**
     * Get all normalized categories for this product.
     */
    public function normalizedCategories(): Collection
    {
        return $this->categories()
            ->with('normalizedCategories')
            ->get()
            ->pluck('normalizedCategories')
            ->flatten()
            ->unique('id');
    }

    /**
     * Get the supermarket this product belongs to.
     */
    public function supermarketModel(): BelongsTo
    {
        return $this->belongsTo(Supermarket::class, 'supermarket', 'identifier');
    }
}
