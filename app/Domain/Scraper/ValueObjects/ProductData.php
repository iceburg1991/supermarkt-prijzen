<?php

declare(strict_types=1);

namespace App\Domain\Scraper\ValueObjects;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

/**
 * Immutable value object representing product data from scraper.
 *
 * Contains all product information including pricing, availability,
 * and metadata scraped from supermarket APIs.
 */
readonly class ProductData
{
    /**
     * Create a new ProductData instance.
     *
     * @param  string  $productId  Unique product identifier from supermarket
     * @param  string  $supermarket  Supermarket identifier (e.g., 'ah', 'jumbo')
     * @param  string  $name  Product name
     * @param  string  $quantity  Product quantity/size (e.g., '1L', '500g')
     * @param  int  $priceCents  Regular price in cents
     * @param  int  $promoPriceCents  Promotional price in cents (0 if no promo)
     * @param  bool  $available  Product availability status
     * @param  string  $badge  Product badge/label (e.g., 'Bonus', 'New')
     * @param  string  $unitPrice  Unit price description (e.g., '€1.50 per kg')
     * @param  string  $imageUrl  Product image URL
     * @param  string  $productUrl  Product page URL
     * @param  Carbon  $scrapedAt  Timestamp when data was scraped
     */
    public function __construct(
        public string $productId,
        public string $supermarket,
        public string $name,
        public string $quantity,
        public int $priceCents,
        public int $promoPriceCents,
        public bool $available,
        public string $badge,
        public string $unitPrice,
        public string $imageUrl,
        public string $productUrl,
        public Carbon $scrapedAt,
    ) {}

    /**
     * Create ProductData from array.
     *
     * @param  array<string, mixed>  $data  Product data array
     */
    public static function fromArray(array $data): self
    {
        $scrapedAt = $data['scraped_at'] ?? now();

        // Convert CarbonImmutable to Carbon if needed
        if ($scrapedAt instanceof CarbonImmutable) {
            $scrapedAt = Carbon::instance($scrapedAt);
        }

        return new self(
            productId: $data['product_id'],
            supermarket: $data['supermarket'],
            name: $data['name'],
            quantity: $data['quantity'] ?? '',
            priceCents: $data['price_cents'],
            promoPriceCents: $data['promo_price_cents'] ?? 0,
            available: $data['available'] ?? true,
            badge: $data['badge'] ?? '',
            unitPrice: $data['unit_price'] ?? '',
            imageUrl: $data['image_url'] ?? '',
            productUrl: $data['product_url'] ?? '',
            scrapedAt: $scrapedAt,
        );
    }

    /**
     * Check if product has an active promotion.
     *
     * @return bool True if promotional price is set
     */
    public function hasPromotion(): bool
    {
        return $this->promoPriceCents > 0;
    }

    /**
     * Get effective price (promotional if available, otherwise regular).
     *
     * @return int Price in cents
     */
    public function getEffectivePrice(): int
    {
        return $this->hasPromotion() ? $this->promoPriceCents : $this->priceCents;
    }
}
