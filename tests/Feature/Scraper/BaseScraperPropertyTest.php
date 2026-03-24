<?php

declare(strict_types=1);

namespace Feature\Scraper;

use App\DataTransferObjects\Scraper\ScraperConfig;
use App\Http\Scrapers\BaseScraper;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Property-based tests for BaseScraper.
 *
 * Validates rate limiting, retry logic, and graceful degradation.
 */
class BaseScraperPropertyTest extends TestCase
{
    private const int ITERATIONS = 10; // Reduced for speed

    /**
     * Property 5: Rate Limiting Consistency
     *
     * For any sequence of API requests, time between consecutive requests
     * should be at least the configured delay.
     *
     * Validates: Requirements 2.8, 3.7
     */
    public function test_rate_limiting_consistency(): void
    {
        // Use small delay for fast tests (50ms)
        $rateLimitDelayMs = 50;

        $config = new ScraperConfig(
            identifier: 'test',
            baseUrl: 'https://api.test.com',
            headers: ['User-Agent' => 'Test'],
            rateLimitDelay: $rateLimitDelayMs,
            maxRetries: 1,
            timeout: 10
        );

        Http::fake(['*' => Http::response(['success' => true], 200)]);

        $scraper = new TestScraper($config);

        // First request (no delay expected)
        $scraper->testGet('/test1');

        // Second request (should have delay)
        $start2 = microtime(true);
        $scraper->testGet('/test2');
        $end2 = microtime(true);

        // Third request (should have delay)
        $start3 = microtime(true);
        $scraper->testGet('/test3');
        $end3 = microtime(true);

        // Validate delays (allow 20ms tolerance)
        $delay2 = ($end2 - $start2) * 1000;
        $delay3 = ($end3 - $start3) * 1000;

        $this->assertGreaterThanOrEqual(
            $rateLimitDelayMs - 20,
            $delay2,
            "Second request delay should be at least {$rateLimitDelayMs}ms, got {$delay2}ms"
        );

        $this->assertGreaterThanOrEqual(
            $rateLimitDelayMs - 20,
            $delay3,
            "Third request delay should be at least {$rateLimitDelayMs}ms, got {$delay3}ms"
        );
    }

    /**
     * Property 33: Retry with Exponential Backoff
     *
     * For any failed API request, scraper should retry with exponential backoff.
     *
     * Validates: Requirements 2.8, 3.7, 11.2, 11.3
     */
    public function test_retry_with_exponential_backoff(): void
    {
        $config = new ScraperConfig(
            identifier: 'test',
            baseUrl: 'https://api.test.com',
            headers: [],
            rateLimitDelay: 0,
            maxRetries: 3,
            timeout: 10
        );

        // Mock: fail twice, then succeed
        Http::fake([
            '*' => Http::sequence([
                Http::response(['error' => 'Rate limited'], 429),
                Http::response(['error' => 'Rate limited'], 429),
                Http::response(['success' => true], 200),
            ]),
        ]);

        $scraper = new TestScraper($config);

        $startTime = microtime(true);
        $result = $scraper->testGet('/test');
        $endTime = microtime(true);

        // Should eventually succeed
        $this->assertNotNull($result);
        $this->assertEquals('true', $result['success']);

        // Should wait at least 2^1 + 2^2 = 6 seconds (allow 1s tolerance)
        $actualDelay = $endTime - $startTime;
        $this->assertGreaterThanOrEqual(5, $actualDelay);
    }

    /**
     * Test retry exhaustion returns null.
     */
    public function test_retry_exhaustion_returns_null(): void
    {
        $config = new ScraperConfig(
            identifier: 'test',
            baseUrl: 'https://api.test.com',
            headers: [],
            rateLimitDelay: 0,
            maxRetries: 2,
            timeout: 10
        );

        // All requests fail
        Http::fake(['*' => Http::response(['error' => 'Rate limited'], 429)]);

        $scraper = new TestScraper($config);
        $result = $scraper->testGet('/test');

        $this->assertNull($result);
    }

    /**
     * Property 34: Graceful Degradation
     *
     * For any request that fails, scraper should continue with next request.
     *
     * Validates: Requirements 2.8, 3.7, 11.2, 11.3
     */
    public function test_graceful_degradation(): void
    {
        $config = new ScraperConfig(
            identifier: 'test',
            baseUrl: 'https://api.test.com',
            headers: [],
            rateLimitDelay: 0,
            maxRetries: 1,
            timeout: 10
        );

        Http::fake([
            '*/fail' => Http::response(['error' => 'Server error'], 500),
            '*/success' => Http::response(['data' => 'ok'], 200),
        ]);

        $scraper = new TestScraper($config);

        // First request fails
        $result1 = $scraper->testGet('/fail');
        $this->assertNull($result1);

        // Second request succeeds (scraper continues)
        $result2 = $scraper->testGet('/success');
        $this->assertNotNull($result2);
        $this->assertEquals('ok', $result2['data']);
    }

    /**
     * Test various HTTP error codes are handled correctly.
     */
    public function test_http_error_code_handling(): void
    {
        $config = new ScraperConfig(
            identifier: 'test',
            baseUrl: 'https://api.test.com',
            headers: [],
            rateLimitDelay: 0,
            maxRetries: 1,
            timeout: 10
        );

        $errorCodes = [400, 401, 403, 404, 500, 502, 503];

        foreach ($errorCodes as $errorCode) {
            Http::clearResolvedInstances();
            Http::fake(['*' => Http::response(['error' => 'Error'], $errorCode)]);

            $scraper = new TestScraper($config);
            $result = $scraper->testGet('/test');

            // All errors should return null gracefully
            $this->assertNull($result, "Error {$errorCode} should return null");
        }
    }

    /**
     * Test JSON parsing error handling.
     */
    public function test_json_parsing_error_handling(): void
    {
        $config = new ScraperConfig(
            identifier: 'test',
            baseUrl: 'https://api.test.com',
            headers: [],
            rateLimitDelay: 0,
            maxRetries: 1,
            timeout: 10
        );

        Http::fake(['*' => Http::response('Invalid JSON {{{', 200)]);

        $scraper = new TestScraper($config);
        $result = $scraper->testGet('/test');

        $this->assertNull($result);
    }
}

/**
 * Concrete test implementation of BaseScraper.
 */
class TestScraper extends BaseScraper
{
    public function authenticate(?string $authCode = null): bool
    {
        return true;
    }

    public function searchProducts(string $query, int $maxResults = 20): Collection
    {
        return collect();
    }

    public function getCategories(): Collection
    {
        return collect();
    }

    public function getProductsByCategory(string $categoryId, int $maxResults = 50): Collection
    {
        return collect();
    }

    public function getPromotionalProducts(int $maxResults = 30): Collection
    {
        return collect();
    }

    public function getIdentifier(): string
    {
        return 'test';
    }

    public function testGet(string $path, array $params = []): ?array
    {
        return $this->get($path, $params);
    }

    public function testPost(string $path, array $data = []): ?array
    {
        return $this->post($path, $data);
    }
}
