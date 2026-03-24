<?php

declare(strict_types=1);

namespace App\Services\Scraper;

use App\Contracts\Scraper\TokenManagerInterface;
use App\DataTransferObjects\Scraper\TokenData;
use App\Exceptions\Scraper\TokenException;
use App\Exceptions\Scraper\TokenExpiredException;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for managing OAuth tokens.
 *
 * Handles token exchange, refresh, caching, and validation for
 * supermarket APIs requiring OAuth authentication.
 */
class TokenManager implements TokenManagerInterface
{
    /**
     * Exchange authorization code for access token.
     *
     * @param  string  $code  Authorization code from OAuth flow
     * @return TokenData Token data including access and refresh tokens
     *
     * @throws TokenException If token exchange fails
     */
    public function exchangeCode(string $code): TokenData
    {
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Appie/9.28 (iPhone17,3; iPhone; CPU OS 26_1 like Mac OS X)',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'x-client-name' => 'appie-ios',
                    'x-client-version' => '9.28',
                    'x-application' => 'AHWEBSHOP',
                ])
                ->post(config('scrapers.ah.oauth_url').'/token', [
                    'clientId' => 'appie-ios',
                    'code' => $code,
                ]);

            if (! $response->successful()) {
                throw new TokenException(
                    "Failed to exchange authorization code: {$response->status()} - {$response->body()}"
                );
            }

            $data = $response->json();

            if (! isset($data['access_token'])) {
                throw new TokenException('Access token not found in response');
            }

            Log::info('Successfully exchanged authorization code for access token');

            return TokenData::fromApiResponse($data);
        } catch (\Exception $e) {
            Log::error('Token exchange failed', [
                'error' => $e->getMessage(),
            ]);

            throw new TokenException("Token exchange failed: {$e->getMessage()}", null, 0, $e);
        }
    }

    /**
     * Refresh an expired access token using refresh token.
     *
     * @param  string  $refreshToken  Refresh token from .env
     * @return TokenData New token data with refreshed access token
     *
     * @throws TokenException If token refresh fails
     */
    public function refreshToken(string $refreshToken): TokenData
    {
        try {
            Log::info('Attempting to refresh access token');

            $response = Http::timeout(10)
                ->withHeaders([
                    'User-Agent' => 'Appie/9.28 (iPhone17,3; iPhone; CPU OS 26_1 like Mac OS X)',
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'x-client-name' => 'appie-ios',
                    'x-client-version' => '9.28',
                    'x-application' => 'AHWEBSHOP',
                ])
                ->post(config('scrapers.ah.oauth_url').'/token/refresh', [
                    'clientId' => 'appie-ios',
                    'refreshToken' => $refreshToken,
                ]);

            if (! $response->successful()) {
                $statusCode = $response->status();
                $responseBody = $response->body();

                // Check if refresh token is invalid (401/403)
                if (in_array($statusCode, [401, 403])) {
                    Log::channel('scraper-errors')->error('Invalid refresh token - manual intervention required', [
                        'status_code' => $statusCode,
                        'response' => $responseBody,
                    ]);

                    throw new TokenExpiredException(
                        "Invalid refresh token: {$statusCode}. Please run 'php artisan scraper:auth:setup' to obtain a new refresh token."
                    );
                }

                Log::channel('scraper-errors')->error('Token refresh failed', [
                    'status_code' => $statusCode,
                    'response' => $responseBody,
                ]);

                throw new TokenExpiredException(
                    "Failed to refresh token: {$statusCode} - {$responseBody}"
                );
            }

            $data = $response->json();

            if (! isset($data['access_token'])) {
                Log::channel('scraper-errors')->error('Access token not found in refresh response', [
                    'response' => $data,
                ]);

                throw new TokenException('Access token not found in refresh response');
            }

            Log::info('Successfully refreshed access token', [
                'expires_in' => $data['expires_in'] ?? 'unknown',
            ]);

            return TokenData::fromApiResponse($data);
        } catch (TokenException|TokenExpiredException $e) {
            // Re-throw our custom exceptions
            throw $e;
        } catch (\Exception $e) {
            Log::channel('scraper-errors')->error('Unexpected error during token refresh', [
                'error' => $e->getMessage(),
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            throw new TokenException("Token refresh failed: {$e->getMessage()}", null, 0, $e);
        }
    }

    /**
     * Get current valid access token from cache, refreshing if necessary.
     *
     * @param  string  $supermarket  Supermarket identifier (e.g., 'ah')
     * @return string|null Valid access token or null if unavailable
     */
    public function getValidToken(string $supermarket): ?string
    {
        // Check if we have a valid cached token
        if ($this->hasValidToken($supermarket)) {
            return Cache::get("scraper.{$supermarket}.access_token");
        }

        // Try to refresh the token
        $refreshToken = $this->getRefreshToken($supermarket);

        if ($refreshToken === null) {
            Log::warning("No refresh token configured for {$supermarket}");

            return null;
        }

        try {
            $tokenData = $this->refreshToken($refreshToken);
            $this->cacheAccessToken($supermarket, $tokenData);

            return $tokenData->accessToken;
        } catch (TokenException $e) {
            Log::error("Failed to get valid token for {$supermarket}", [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Store access token in cache with TTL.
     *
     * @param  string  $supermarket  Supermarket identifier
     * @param  TokenData  $tokenData  Token data to cache
     */
    public function cacheAccessToken(string $supermarket, TokenData $tokenData): void
    {
        $expiresAt = $tokenData->expiresAt ?? now()->addHour();

        Cache::put(
            "scraper.{$supermarket}.access_token",
            $tokenData->accessToken,
            $expiresAt
        );

        Cache::put(
            "scraper.{$supermarket}.token_expires_at",
            $expiresAt->toIso8601String(),
            $expiresAt
        );

        Log::debug("Cached access token for {$supermarket}", [
            'expires_at' => $expiresAt->toDateTimeString(),
        ]);
    }

    /**
     * Check if valid access token exists in cache.
     *
     * @param  string  $supermarket  Supermarket identifier
     * @return bool True if valid token exists
     */
    public function hasValidToken(string $supermarket): bool
    {
        $accessToken = Cache::get("scraper.{$supermarket}.access_token");
        $expiresAt = Cache::get("scraper.{$supermarket}.token_expires_at");

        if ($accessToken === null || $expiresAt === null) {
            return false;
        }

        // Check if token is expired or expiring soon (within 5 minutes)
        $expiryTime = Carbon::parse($expiresAt);

        return $expiryTime->subMinutes(5)->isFuture();
    }

    /**
     * Get refresh token from .env configuration.
     *
     * @param  string  $supermarket  Supermarket identifier
     * @return string|null Encrypted refresh token or null if not configured
     */
    public function getRefreshToken(string $supermarket): ?string
    {
        $encryptedToken = config("scrapers.{$supermarket}.refresh_token");

        if ($encryptedToken === null) {
            return null;
        }

        // If token starts with "encrypted:", decrypt it
        if (str_starts_with($encryptedToken, 'encrypted:')) {
            try {
                return Crypt::decryptString(substr($encryptedToken, 10));
            } catch (\Exception $e) {
                Log::error("Failed to decrypt refresh token for {$supermarket}", [
                    'error' => $e->getMessage(),
                ]);

                return null;
            }
        }

        // Return as-is if not encrypted (for backward compatibility)
        return $encryptedToken;
    }
}
