<?php

declare(strict_types=1);

namespace App\DataTransferObjects\Scraper;

use Carbon\Carbon;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;

/**
 * Data transfer object representing OAuth token data.
 *
 * Contains access token, refresh token, and expiry information
 * for authenticated API access.
 */
class TokenData extends Data
{
    /**
     * Create a new TokenData instance.
     *
     * @param  string  $accessToken  OAuth access token
     * @param  string|null  $refreshToken  OAuth refresh token (null if not provided)
     * @param  Carbon|null  $expiresAt  Token expiration timestamp
     */
    public function __construct(
        public string $accessToken,
        public ?string $refreshToken,
        #[WithCast(DateTimeInterfaceCast::class, format: 'Y-m-d H:i:s')]
        public ?Carbon $expiresAt,
    ) {}

    /**
     * Create TokenData from API response.
     *
     * @param  array<string, mixed>  $response  API response containing token data
     */
    public static function fromApiResponse(array $response): self
    {
        return new self(
            accessToken: $response['access_token'],
            refreshToken: $response['refresh_token'] ?? null,
            expiresAt: isset($response['expires_in'])
                ? Carbon::now()->addSeconds($response['expires_in'])
                : null,
        );
    }

    /**
     * Check if token is expired.
     *
     * @return bool True if token has expired
     */
    public function isExpired(): bool
    {
        return $this->expiresAt !== null && $this->expiresAt->isPast();
    }

    /**
     * Check if token is expiring soon.
     *
     * @param  int  $bufferMinutes  Minutes before expiry to consider "expiring soon"
     * @return bool True if token expires within buffer period
     */
    public function isExpiringSoon(int $bufferMinutes = 5): bool
    {
        return $this->expiresAt !== null
            && $this->expiresAt->subMinutes($bufferMinutes)->isPast();
    }
}
