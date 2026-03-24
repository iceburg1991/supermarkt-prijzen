<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Scraper;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

/**
 * Data transfer object representing product data from scraper.
 *
 * Contains all product information including pricing, availability,
 * and metadata scraped from supermarket APIs.
 */
class ProductData extends Data
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
     * @param  array<string>  $categoryIds  Category IDs this product belongs to
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
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public Carbon $scrapedAt,
        public array $categoryIds = [],
    ) {}

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

    /**
     * Create ProductData from array (backward compatibility wrapper).
     *
     * This method provides backward compatibility with the old ValueObject
     * implementation that used fromArray(). It wraps Spatie's from() method.
     *
     * @param array<string, mixed> $data Product data array
     * @return self
     */
    public static function fromArray(array $data): self
    {
        return self::from($data);
    }
}
