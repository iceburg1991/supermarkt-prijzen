<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Scraper;

use Spatie\LaravelData\Data;

/**
 * Data transfer object representing scraper configuration.
 *
 * Contains all configuration needed for a specific supermarket scraper
 * including API endpoints, headers, and rate limiting settings.
 */
class ScraperConfig extends Data
{
    /**
     * Create a new ScraperConfig instance.
     *
     * @param  string  $identifier  Supermarket identifier (e.g., 'ah', 'jumbo')
     * @param  string  $baseUrl  API base URL
     * @param  array<string, string>  $headers  HTTP headers to include in requests
     * @param  int  $rateLimitDelay  Delay between requests in milliseconds
     * @param  int  $maxRetries  Maximum number of retry attempts
     * @param  int  $timeout  Request timeout in seconds
     */
    public function __construct(
        public string $identifier,
        public string $baseUrl,
        public array $headers,
        public int $rateLimitDelay,
        public int $maxRetries,
        public int $timeout,
    ) {}

    /**
     * Create configuration for Albert Heijn scraper.
     */
    public static function forAh(): self
    {
        return new self(
            identifier: 'ah',
            baseUrl: config('scrapers.ah.base_url'),
            headers: config('scrapers.ah.headers'),
            rateLimitDelay: config('scrapers.ah.rate_limit_delay', 600),
            maxRetries: config('scrapers.max_retries', 3),
            timeout: config('scrapers.timeout', 10),
        );
    }

    /**
     * Create configuration for Jumbo scraper.
     */
    public static function forJumbo(): self
    {
        return new self(
            identifier: 'jumbo',
            baseUrl: config('scrapers.jumbo.base_url'),
            headers: config('scrapers.jumbo.headers'),
            rateLimitDelay: config('scrapers.jumbo.rate_limit_delay', 600),
            maxRetries: config('scrapers.max_retries', 3),
            timeout: config('scrapers.timeout', 10),
        );
    }
}
