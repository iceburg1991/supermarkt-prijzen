<?php

declare(strict_types=1);

namespace App\Repositories\Scraper;

use App\Models\Price;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Repository for price data access.
 *
 * Handles price history storage and retrieval without deleting historical data.
 */
class PriceRepository
{
    /**
     * Create a new price record.
     *
     * Inserts new price without deleting historical data.
     */
    public function create(
        string $productId,
        string $supermarket,
        int $priceCents,
        int $promoPriceCents,
        bool $available,
        string $badge,
        string $unitPrice,
        Carbon $scrapedAt
    ): Price {
        return Price::create([
            'product_id' => $productId,
            'supermarket' => $supermarket,
            'price_cents' => $priceCents,
            'promo_price_cents' => $promoPriceCents,
            'available' => $available,
            'badge' => $badge,
            'unit_price' => $unitPrice,
            'scraped_at' => $scrapedAt,
        ]);
    }

    /**
     * Get price history for a product over a time period.
     *
     * Returns all price records within the specified time range.
     */
    public function getPriceHistory(
        string $productId,
        string $supermarket,
        int $days = 90
    ): Collection {
        $startDate = now()->subDays($days);

        return Price::query()
            ->where('product_id', $productId)
            ->where('supermarket', $supermarket)
            ->where('scraped_at', '>=', $startDate)
            ->orderBy('scraped_at', 'asc')
            ->get();
    }

    /**
     * Get the latest price for a product.
     *
     * Returns the most recent price record based on scraped_at.
     */
    public function getLatestPrice(string $productId, string $supermarket): ?Price
    {
        return Price::query()
            ->where('product_id', $productId)
            ->where('supermarket', $supermarket)
            ->orderBy('scraped_at', 'desc')
            ->first();
    }
}
