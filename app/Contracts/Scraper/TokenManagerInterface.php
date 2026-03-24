<?php

declare(strict_types=1);

namespace App\Contracts\Scraper;

use App\DataTransferObjects\Scraper\TokenData;

/**
 * Interface for OAuth token management.
 *
 * Handles token exchange, refresh, and caching for supermarket APIs
 * that require OAuth authentication.
 */
interface TokenManagerInterface
{
    /**
     * Exchange authorization code for access token.
     *
     * @param string $code Authorization code from OAuth flow
     * @return TokenData Token data including access and refresh tokens
     */
    public function exchangeCode(string $code): TokenData;

    /**
     * Refresh an expired access token using refresh token.
     *
     * @param string $refreshToken Refresh token from .env
     * @return TokenData New token data with refreshed access token
     */
    public function refreshToken(string $refreshToken): TokenData;

    /**
     * Get current valid access token from cache, refreshing if necessary.
     *
     * @param string $supermarket Supermarket identifier (e.g., 'ah')
     * @return string|null Valid access token or null if unavailable
     */
    public function getValidToken(string $supermarket): ?string;

    /**
     * Store access token in cache with TTL.
     *
     * @param string $supermarket Supermarket identifier
     * @param TokenData $tokenData Token data to cache
     * @return void
     */
    public function cacheAccessToken(string $supermarket, TokenData $tokenData): void;

    /**
     * Check if valid access token exists in cache.
     *
     * @param string $supermarket Supermarket identifier
     * @return bool True if valid token exists
     */
    public function hasValidToken(string $supermarket): bool;

    /**
     * Get refresh token from .env configuration.
     *
     * @param string $supermarket Supermarket identifier
     * @return string|null Encrypted refresh token or null if not configured
     */
    public function getRefreshToken(string $supermarket): ?string;
}
