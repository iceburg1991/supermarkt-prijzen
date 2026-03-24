<?php

declare(strict_types=1);

namespace App\Infrastructure\Scraper\Http;

use App\Contracts\Scraper\SupermarketScraperInterface;
use App\DataTransferObjects\Scraper\ScraperConfig;
use App\Exceptions\Scraper\ApiException;
use App\Exceptions\Scraper\ApiRateLimitException;
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
     * Error logger instance.
     */
    protected LoggerInterface $errorLogger;

    /**
     * Debug logger instance.
     */
    protected LoggerInterface $debugLogger;

    /**
     * Timestamp of last API request for rate limiting.
     */
    protected ?int $lastRequestTime = null;

    /**
     * Current scrape run ID for context.
     */
    protected ?int $scrapeRunId = null;

    /**
     * Create a new BaseScraper instance.
     *
     * @param ScraperConfig $config Scraper configuration
     */
    public function __construct(ScraperConfig $config)
    {
        $this->config = $config;
        $this->logger = Log::channel('scraper');
        $this->errorLogger = Log::channel('scraper-errors');
        $this->debugLogger = Log::channel('scraper-debug');
    }

    /**
     * Set the current scrape run ID for logging context.
     *
     * @param int|null $scrapeRunId Scrape run ID
     * @return void
     */
    public function setScrapeRunId(?int $scrapeRunId): void
    {
        $this->scrapeRunId = $scrapeRunId;
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
        $url = $this->buildUrl($path);

        $this->log('info', "Starting GET request", [
            'endpoint' => $path,
            'url' => $url,
            'params' => $params,
        ]);

        $result = $this->retryWithBackoff(function () use ($path, $params, $url) {
            $this->applyRateLimit();

            $client = $this->createHttpClient();

            $this->logDebug("Executing GET request to {$url}", ['params' => $params]);

            $response = $client->get($url, $params);

            $this->lastRequestTime = time();

            return $this->handleResponse($response, 'GET', $url, $path);
        });

        if ($result !== null) {
            $this->log('info', "GET request completed successfully", [
                'endpoint' => $path,
                'url' => $url,
            ]);
        }

        return $result;
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
        $url = $this->buildUrl($path);

        $this->log('info', "Starting POST request", [
            'endpoint' => $path,
            'url' => $url,
        ]);

        $result = $this->retryWithBackoff(function () use ($path, $data, $url) {
            $this->applyRateLimit();

            $client = $this->createHttpClient();

            $this->logDebug("Executing POST request to {$url}", ['data' => $data]);

            $response = $client->post($url, $data);

            $this->lastRequestTime = time();

            return $this->handleResponse($response, 'POST', $url, $path);
        });

        if ($result !== null) {
            $this->log('info', "POST request completed successfully", [
                'endpoint' => $path,
                'url' => $url,
            ]);
        }

        return $result;
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
            $this->logDebug("Rate limiting: sleeping for {$sleepMs}ms");
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
                    $this->logError('Max retry attempts reached for rate limit', [
                        'attempts' => $attempt,
                        'error' => $e->getMessage(),
                    ]);

                    return null;
                }

                // Exponential backoff: 2^attempt seconds
                $backoffSeconds = 2 ** $attempt;
                $this->log('warning', "Rate limited, retrying in {$backoffSeconds}s (attempt {$attempt}/{$maxAttempts})", [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'backoff_seconds' => $backoffSeconds,
                ]);

                sleep($backoffSeconds);
            } catch (ApiException $e) {
                $attempt++;

                if ($attempt >= $maxAttempts) {
                    $this->logError('Max retry attempts reached', [
                        'attempts' => $attempt,
                        'error' => $e->getMessage(),
                        'exception_class' => get_class($e),
                    ]);

                    return null;
                }

                // Exponential backoff for API errors
                $backoffSeconds = 2 ** $attempt;
                $this->log('warning', "API error, retrying in {$backoffSeconds}s (attempt {$attempt}/{$maxAttempts})", [
                    'attempt' => $attempt,
                    'max_attempts' => $maxAttempts,
                    'backoff_seconds' => $backoffSeconds,
                    'error' => $e->getMessage(),
                ]);

                sleep($backoffSeconds);
            } catch (\Exception $e) {
                $this->logError('Unexpected error during request', [
                    'error' => $e->getMessage(),
                    'exception_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
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

        // Add scrape_run_id if available
        if ($this->scrapeRunId !== null) {
            $context['scrape_run_id'] = $this->scrapeRunId;
        }

        $this->logger->log($level, $message, $context);

        // Also log errors to the error channel
        if ($level === 'error') {
            $this->errorLogger->error($message, $context);
        }
    }

    /**
     * Log debug message (only when SCRAPER_DEBUG=true).
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    protected function logDebug(string $message, array $context = []): void
    {
        if (! config('scrapers.debug', false)) {
            return;
        }

        $context = array_merge([
            'supermarket' => $this->getIdentifier(),
            'timestamp' => now()->toIso8601String(),
        ], $context);

        if ($this->scrapeRunId !== null) {
            $context['scrape_run_id'] = $this->scrapeRunId;
        }

        $this->debugLogger->debug($message, $context);
    }

    /**
     * Log error message to both scraper and scraper-errors channels.
     *
     * @param string $message Log message
     * @param array<string, mixed> $context Additional context
     * @return void
     */
    protected function logError(string $message, array $context = []): void
    {
        $context = array_merge([
            'supermarket' => $this->getIdentifier(),
            'timestamp' => now()->toIso8601String(),
        ], $context);

        if ($this->scrapeRunId !== null) {
            $context['scrape_run_id'] = $this->scrapeRunId;
        }

        $this->logger->error($message, $context);
        $this->errorLogger->error($message, $context);
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
     * @param string $endpoint API endpoint path
     * @return array<string, mixed>|null Response data or null on failure
     * @throws ApiRateLimitException If rate limited
     * @throws ApiException If API error occurs
     */
    protected function handleResponse(Response $response, string $method, string $url, string $endpoint): ?array
    {
        $statusCode = $response->status();

        // Handle rate limiting
        if ($statusCode === 429) {
            $this->log('warning', 'Rate limit exceeded', [
                'method' => $method,
                'url' => $url,
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
            ]);

            throw new ApiRateLimitException('Rate limit exceeded');
        }

        // Handle authentication errors
        if (in_array($statusCode, [401, 403])) {
            $this->logError('Authentication failed', [
                'method' => $method,
                'url' => $url,
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
            ]);

            throw new ApiException("Authentication failed: {$statusCode}");
        }

        // Handle server errors
        if ($statusCode >= 500) {
            $this->logError('Server error', [
                'method' => $method,
                'url' => $url,
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'response_body' => $response->body(),
            ]);

            throw new ApiException("Server error: {$statusCode}");
        }

        // Handle not found
        if ($statusCode === 404) {
            $this->log('warning', 'Resource not found', [
                'method' => $method,
                'url' => $url,
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
            ]);

            return null;
        }

        // Handle other client errors
        if ($statusCode >= 400) {
            $this->logError('Client error', [
                'method' => $method,
                'url' => $url,
                'endpoint' => $endpoint,
                'status_code' => $statusCode,
                'response_body' => $response->body(),
            ]);

            throw new ApiException("Client error: {$statusCode}");
        }

        // Parse JSON response
        try {
            $data = $response->json();

            if ($data === null) {
                $this->logError('Failed to parse JSON response', [
                    'method' => $method,
                    'url' => $url,
                    'endpoint' => $endpoint,
                    'response_body' => $response->body(),
                ]);

                return null;
            }

            $this->logDebug('Response parsed successfully', [
                'method' => $method,
                'endpoint' => $endpoint,
                'data_keys' => array_keys($data),
            ]);

            return $data;
        } catch (\Exception $e) {
            $this->logError('JSON parsing error', [
                'method' => $method,
                'url' => $url,
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
                'response_body' => $response->body(),
            ]);

            return null;
        }
    }
}
