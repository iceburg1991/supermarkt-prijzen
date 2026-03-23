<?php

declare(strict_types=1);

namespace App\Infrastructure\Scraper\Http;

use App\Domain\Scraper\Contracts\SupermarketScraperInterface;
use App\Domain\Scraper\Exceptions\ApiException;
use App\Domain\Scraper\Exceptions\ApiRateLimitException;
use App\Domain\Scraper\ValueObjects\ScraperConfig;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * Abstract base class for supermarket scrapers.
 *
 * Provides common functionality including HTTP client setup,
 * rate limiting, retry logic with exponential backoff, and logging.
 */
abstract class BaseScraper implements SupermarketScraperInterface
{
    /**
     * Scraper configuration.
     */
    protected ScraperConfig $config;

    /**
     * Logger instance.
     */
    protected LoggerInterface $logger;

    /**
     * Timestamp of last API request for rate limiting.
     */
    protected ?int $lastRequestTime = null;

    /**
     * Create a new BaseScraper instance.
     *
     * @param ScraperConfig $config Scraper configuration
     */
    public function __construct(ScraperConfig $config)
    {
        $this->config = $config;
        $this->logger = Log::channel('scraper');
    }

    /**
     * Make HTTP GET request with rate limiting and retry logic.
     *
     * @param string $path API endpoint path
     * @param array<string, mixed> $params Query parameters
     * @return array<string, mixed>|null Response data or null on failure
     */
    protected function get(string $path, array $params = []): ?array
    {
        return $this->retryWithBackoff(function () use ($path, $params) {
            $this->applyRateLimit();

            $client = $this->createHttpClient();
            $url = $this->buildUrl($path);

            $this->log('debug', "GET request to {$url}", ['params' => $params]);

            $response = $client->get($url, $params);

            $this->lastRequestTime = time();

            return $this->handleResponse($response, 'GET', $url);
        });
    }

    /**
     * Make HTTP POST request with rate limiting and retry logic.
     *
     * @param string $path API endpoint path
     * @param array<string, mixed> $data Request body data
     * @return array<string, mixed>|null Response data or null on failure
     */
    protected function post(string $path, array $data = []): ?array
    {
        return $this->retryWithBackoff(function () use ($path, $data) {
            $this->applyRateLimit();

            $client = $this->createHttpClient();
            $url = $this->buildUrl($path);

            $this->log('debug', "POST request to {$url}", ['data' => $data]);

            $response = $client->post($url, $data);

            $this->lastRequestTime = time();

            return $this->handleResponse($response, 'POST', $url);
        });
    }

    /**
     * Apply rate limiting delay between requests.
     *
     * @return void
     */
    protected function applyRateLimit(): void
    {
        if ($this->lastRequestTime === null) {
            return;
        }

        $elapsedMs = (time() - $this->lastRequestTime) * 1000;
        $delayMs = $this->config->rateLimitDelay;

        if ($elapsedMs < $delayMs) {
            $sleepMs = $delayMs - $elapsedMs;
            $this->log('debug', "Rate limiting: sleeping for {$sleepMs}ms");
            usleep((int) ($sleepMs * 1000));
        }
    }

    /**
     * Retry failed requests with exponential backoff.
     *
     * @param callable $callback Request callback to retry
     * @param int $maxAttempts Maximum number of retry attempts
     * @return mixed Result from callback or null on failure
     */
    protected function retryWithBackoff(callable $callback, ?int $maxAttempts = null): mixed
    {
        $maxAttempts = $maxAttempts ?? $this->config->maxRetries;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                return $callback();
            } catch (ApiRateLimitException $e) {
                $attempt++;

                if ($attempt >= $maxAttempts) {
                    $this->log('error', 'Max retry attempts reached for rate limit', [
                        'attempts' => $attempt,
                        'error' => $e->getMessage(),
                    ]);

                    return null;
                }

                // Exponential backoff: 2^attempt seconds
                $backoffSeconds = 2 ** $attempt;
                $this->log('warning', "Rate limited, retrying in {$backoffSeconds}s", [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                ]);

                sleep($backoffSeconds);
            } catch (ApiException $e) {
                $attempt++;

                if ($attempt >= $maxAttempts) {
                    $this->log('error', 'Max retry attempts reached', [
                        'attempts' => $attempt,
                        'error' => $e->getMessage(),
                    ]);

                    return null;
                }

                // Exponential backoff for API errors
                $backoffSeconds = 2 ** $attempt;
                $this->log('warning', "API error, retrying in {$backoffSeconds}s", [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'error' => $e->getMessage(),
                ]);

                sleep($backoffSeconds);
            } catch (\Exception $e) {
                $this->log('error', 'Unexpected error during request', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                return null;
            }
        }

        return null;
    }

    /**
     * Log scraper activity.
     *
     * @param string $level Log level (debug, info, warning, error)
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        $context = array_merge([
            'supermarket' => $this->getIdentifier(),
            'timestamp' => now()->toIso8601String(),
        ], $context);

        // Only log debug messages if debug mode is enabled
        if ($level === 'debug' && ! config('scrapers.debug', false)) {
            return;
        }

        $this->logger->log($level, $message, $context);
    }

    /**
     * Create HTTP client with configured headers and timeout.
     *
     * @return PendingRequest
     */
    protected function createHttpClient(): PendingRequest
    {
        return Http::timeout($this->config->timeout)
            ->withHeaders($this->config->headers)
            ->acceptJson();
    }

    /**
     * Build full URL from path.
     *
     * @param string $path API endpoint path
     * @return string Full URL
     */
    protected function buildUrl(string $path): string
    {
        $baseUrl = rtrim($this->config->baseUrl, '/');
        $path = ltrim($path, '/');

        return "{$baseUrl}/{$path}";
    }

    /**
     * Handle HTTP response and extract data.
     *
     * @param Response $response HTTP response
     * @param string $method HTTP method
     * @param string $url Request URL
     * @return array<string, mixed>|null Response data or null on failure
     * @throws ApiRateLimitException If rate limited
     * @throws ApiException If API error occurs
     */
    protected function handleResponse(Response $response, string $method, string $url): ?array
    {
        // Handle rate limiting
        if ($response->status() === 429) {
            $this->log('warning', 'Rate limit exceeded', [
                'method' => $method,
                'url' => $url,
                'status' => $response->status(),
            ]);

            throw new ApiRateLimitException('Rate limit exceeded');
        }

        // Handle authentication errors
        if (in_array($response->status(), [401, 403])) {
            $this->log('error', 'Authentication failed', [
                'method' => $method,
                'url' => $url,
                'status' => $response->status(),
            ]);

            throw new ApiException("Authentication failed: {$response->status()}");
        }

        // Handle server errors
        if ($response->status() >= 500) {
            $this->log('error', 'Server error', [
                'method' => $method,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new ApiException("Server error: {$response->status()}");
        }

        // Handle not found
        if ($response->status() === 404) {
            $this->log('warning', 'Resource not found', [
                'method' => $method,
                'url' => $url,
            ]);

            return null;
        }

        // Handle other client errors
        if ($response->status() >= 400) {
            $this->log('error', 'Client error', [
                'method' => $method,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            throw new ApiException("Client error: {$response->status()}");
        }

        // Parse JSON response
        try {
            $data = $response->json();

            if ($data === null) {
                $this->log('error', 'Failed to parse JSON response', [
                    'method' => $method,
                    'url' => $url,
                    'body' => $response->body(),
                ]);

                return null;
            }

            return $data;
        } catch (\Exception $e) {
            $this->log('error', 'JSON parsing error', [
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage(),
                'body' => $response->body(),
            ]);

            return null;
        }
    }
}
