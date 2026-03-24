<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Price model representing a price observation for a product.
 *
 * @property int $id
 * @property string $product_id
 * @property string $supermarket
 * @property int $price_cents
 * @property int $promo_price_cents
 * @property bool $available
 * @property string|null $badge
 * @property string|null $unit_price
 * @property Carbon $scraped_at
 */
class Price extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'supermarket',
        'price_cents',
        'promo_price_cents',
        'available',
        'badge',
        'unit_price',
        'scraped_at',
    ];

    protected $casts = [
        'scraped_at' => 'datetime',
        'available' => 'boolean',
        'price_cents' => 'integer',
        'promo_price_cents' => 'integer',
    ];

    /**
     * Get the product this price belongs to.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id', 'product_id')
            ->where('supermarket', $this->supermarket);
    }

    /**
     * Check if this price has a promotion.
     */
    public function hasPromotion(): bool
    {
        return $this->promo_price_cents > 0;
    }

    /**
     * Get the effective price (promo price if available, otherwise regular price).
     */
    public function getEffectivePrice(): int
    {
        return $this->hasPromotion() ? $this->promo_price_cents : $this->price_cents;
    }
}
