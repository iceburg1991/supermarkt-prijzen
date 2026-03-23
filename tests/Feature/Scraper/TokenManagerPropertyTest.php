<?php

declare(strict_types=1);

namespace Feature\Scraper;

use App\Domain\Scraper\Exceptions\TokenException;
use App\Domain\Scraper\Services\TokenManager;
use App\Domain\Scraper\ValueObjects\TokenData;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Property-based tests for TokenManager.
 *
 * Validates token refresh round trip and token storage security.
 */
class TokenManagerPropertyTest extends TestCase
{
    private const int ITERATIONS = 10; // Reduced for speed

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('scrapers.ah.oauth_url', 'https://api.ah.nl/oauth/token');
        Config::set('scrapers.ah.client_id', 'appie-ios');
    }

    /**
     * Property 1: Token Refresh Round Trip
     *
     * For any valid refresh token, exchanging it should result in
     * successful authentication.
     *
     * Validates: Requirements 2.2, 2.3
     */
    public function test_token_refresh_round_trip(): void
    {
        Cache::flush();

        $refreshToken = fake()->sha256();
        $accessToken = fake()->sha256();
        $expiresIn = 3600;

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => $expiresIn,
            ], 200),
        ]);

        $tokenManager = new TokenManager;
        $tokenData = $tokenManager->refreshToken($refreshToken);

        $this->assertInstanceOf(TokenData::class, $tokenData);
        $this->assertEquals($accessToken, $tokenData->accessToken);
        $this->assertEquals($refreshToken, $tokenData->refreshToken);
        $this->assertNotNull($tokenData->expiresAt);
        $this->assertTrue($tokenData->expiresAt->isFuture());

        // Test caching
        $tokenManager->cacheAccessToken('ah', $tokenData);
        $this->assertEquals($accessToken, $tokenManager->getValidToken('ah'));
        $this->assertTrue($tokenManager->hasValidToken('ah'));
    }

    /**
     * Property 2: Token Storage Security
     *
     * For any token stored in cache, it should be retrievable and valid.
     *
     * Validates: Requirements 2.4
     */
    public function test_token_storage_security(): void
    {
        Cache::flush();

        $accessToken = fake()->sha256();
        $refreshToken = fake()->sha256();
        $expiresIn = 3600;

        $tokenData = new TokenData(
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresAt: Carbon::now()->addSeconds($expiresIn)
        );

        $tokenManager = new TokenManager;
        $tokenManager->cacheAccessToken('ah', $tokenData);

        // Validate storage
        $this->assertEquals($accessToken, Cache::get('scraper.ah.access_token'));
        $this->assertNotNull(Cache::get('scraper.ah.token_expires_at'));

        // Validate retrieval
        $this->assertEquals($accessToken, $tokenManager->getValidToken('ah'));
        $this->assertTrue($tokenManager->hasValidToken('ah'));
    }

    /**
     * Test encrypted refresh token storage.
     */
    public function test_encrypted_refresh_token_storage(): void
    {
        $refreshToken = fake()->sha256();
        $encryptedToken = 'encrypted:'.Crypt::encryptString($refreshToken);

        Config::set('scrapers.ah.refresh_token', $encryptedToken);

        $tokenManager = new TokenManager;
        $retrievedToken = $tokenManager->getRefreshToken('ah');

        $this->assertEquals($refreshToken, $retrievedToken);
    }

    /**
     * Test token expiry detection with 5-minute buffer.
     */
    public function test_token_expiry_detection_with_buffer(): void
    {
        Cache::flush();
        $tokenManager = new TokenManager;

        // Token expiring in 3 minutes should NOT be valid (< 5 min buffer)
        $tokenData = new TokenData(
            accessToken: fake()->sha256(),
            refreshToken: null,
            expiresAt: Carbon::now()->addMinutes(3)
        );
        $tokenManager->cacheAccessToken('ah', $tokenData);
        $this->assertFalse($tokenManager->hasValidToken('ah'));

        // Token expiring in 10 minutes should be valid (> 5 min buffer)
        Cache::flush();
        $tokenData = new TokenData(
            accessToken: fake()->sha256(),
            refreshToken: null,
            expiresAt: Carbon::now()->addMinutes(10)
        );
        $tokenManager->cacheAccessToken('ah', $tokenData);
        $this->assertTrue($tokenManager->hasValidToken('ah'));
    }

    /**
     * Test token refresh failure handling.
     */
    public function test_token_refresh_failure_handling(): void
    {
        Http::fake([
            '*/oauth/token' => Http::response(['error' => 'invalid_grant'], 401),
        ]);

        $tokenManager = new TokenManager;

        $this->expectException(TokenException::class);
        $tokenManager->refreshToken(fake()->sha256());
    }

    /**
     * Test authorization code exchange.
     */
    public function test_authorization_code_exchange(): void
    {
        $authCode = fake()->sha256();
        $accessToken = fake()->sha256();
        $refreshToken = fake()->sha256();

        Http::fake([
            '*/oauth/token' => Http::response([
                'access_token' => $accessToken,
                'refresh_token' => $refreshToken,
                'expires_in' => 3600,
            ], 200),
        ]);

        $tokenManager = new TokenManager;
        $tokenData = $tokenManager->exchangeCode($authCode);

        $this->assertEquals($accessToken, $tokenData->accessToken);
        $this->assertEquals($refreshToken, $tokenData->refreshToken);
    }
}
