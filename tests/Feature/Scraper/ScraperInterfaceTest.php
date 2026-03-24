<?php

declare(strict_types=1);

namespace Feature\Scraper;

use App\Contracts\Scraper\SupermarketScraperInterface;
use App\Contracts\Scraper\TokenManagerInterface;
use App\DataTransferObjects\Scraper\ScraperConfig;
use App\Http\Scrapers\AhScraper;
use App\Http\Scrapers\BaseScraper;
use App\Http\Scrapers\JumboScraper;
use App\Services\Scraper\TokenManager;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Interface implementation tests for scrapers.
 *
 * Validates that all scraper implementations correctly implement
 * the SupermarketScraperInterface and provide required functionality.
 */
class ScraperInterfaceTest extends TestCase
{
    /**
     * Test SupermarketScraperInterface is defined with all required methods.
     */
    public function test_supermarket_scraper_interface_is_defined_with_all_methods(): void
    {
        $this->assertTrue(
            interface_exists(SupermarketScraperInterface::class),
            'SupermarketScraperInterface should exist'
        );

        $reflection = new \ReflectionClass(SupermarketScraperInterface::class);

        // Validate required methods exist
        $requiredMethods = [
            'authenticate',
            'searchProducts',
            'getCategories',
            'getProductsByCategory',
            'getPromotionalProducts',
            'getIdentifier',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "SupermarketScraperInterface should have {$method} method"
            );
        }
    }

    /**
     * Test AhScraper implements all interface methods.
     */
    public function test_ah_scraper_implements_all_interface_methods(): void
    {
        $this->assertTrue(
            class_exists(AhScraper::class),
            'AhScraper class should exist'
        );

        $reflection = new \ReflectionClass(AhScraper::class);

        // Validate implements interface
        $this->assertTrue(
            $reflection->implementsInterface(SupermarketScraperInterface::class),
            'AhScraper should implement SupermarketScraperInterface'
        );

        // Validate all required methods are implemented
        $requiredMethods = [
            'authenticate' => ['authCode' => null],
            'searchProducts' => ['query' => 'test', 'maxResults' => 20],
            'getCategories' => [],
            'getProductsByCategory' => ['categoryId' => '1', 'maxResults' => 50],
            'getPromotionalProducts' => ['maxResults' => 30],
            'getIdentifier' => [],
        ];

        foreach ($requiredMethods as $method => $params) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "AhScraper should have {$method} method"
            );

            $methodReflection = $reflection->getMethod($method);
            $this->assertTrue(
                $methodReflection->isPublic(),
                "AhScraper::{$method} should be public"
            );
        }

        // Validate getIdentifier returns correct value
        $config = new ScraperConfig(
            identifier: 'ah',
            baseUrl: 'https://api.ah.nl',
            headers: [],
            rateLimitDelay: 0,
            maxRetries: 1,
            timeout: 10
        );

        $tokenManager = $this->createMock(TokenManagerInterface::class);
        $scraper = new AhScraper($config, $tokenManager);

        $this->assertEquals(
            'ah',
            $scraper->getIdentifier(),
            'AhScraper should return "ah" as identifier'
        );
    }

    /**
     * Test JumboScraper implements all interface methods.
     */
    public function test_jumbo_scraper_implements_all_interface_methods(): void
    {
        $this->assertTrue(
            class_exists(JumboScraper::class),
            'JumboScraper class should exist'
        );

        $reflection = new \ReflectionClass(JumboScraper::class);

        // Validate implements interface
        $this->assertTrue(
            $reflection->implementsInterface(SupermarketScraperInterface::class),
            'JumboScraper should implement SupermarketScraperInterface'
        );

        // Validate all required methods are implemented
        $requiredMethods = [
            'authenticate',
            'searchProducts',
            'getCategories',
            'getProductsByCategory',
            'getPromotionalProducts',
            'getIdentifier',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "JumboScraper should have {$method} method"
            );

            $methodReflection = $reflection->getMethod($method);
            $this->assertTrue(
                $methodReflection->isPublic(),
                "JumboScraper::{$method} should be public"
            );
        }

        // Validate getIdentifier returns correct value
        $config = new ScraperConfig(
            identifier: 'jumbo',
            baseUrl: 'https://mobileapi.jumbo.com/v17',
            headers: [],
            rateLimitDelay: 0,
            maxRetries: 1,
            timeout: 10
        );

        $scraper = new JumboScraper($config);

        $this->assertEquals(
            'jumbo',
            $scraper->getIdentifier(),
            'JumboScraper should return "jumbo" as identifier'
        );
    }

    /**
     * Test BaseScraper provides common functionality.
     */
    public function test_base_scraper_provides_common_functionality(): void
    {
        $this->assertTrue(
            class_exists(BaseScraper::class),
            'BaseScraper class should exist'
        );

        $reflection = new \ReflectionClass(BaseScraper::class);

        // Validate is abstract
        $this->assertTrue(
            $reflection->isAbstract(),
            'BaseScraper should be abstract'
        );

        // Validate implements interface
        $this->assertTrue(
            $reflection->implementsInterface(SupermarketScraperInterface::class),
            'BaseScraper should implement SupermarketScraperInterface'
        );

        // Validate common protected methods exist
        $commonMethods = [
            'get',
            'post',
            'applyRateLimit',
            'retryWithBackoff',
            'log',
        ];

        foreach ($commonMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "BaseScraper should have {$method} method"
            );
        }
    }

    /**
     * Test TokenManagerInterface is defined with all required methods.
     */
    public function test_token_manager_interface_is_defined_with_all_methods(): void
    {
        $this->assertTrue(
            interface_exists(TokenManagerInterface::class),
            'TokenManagerInterface should exist'
        );

        $reflection = new \ReflectionClass(TokenManagerInterface::class);

        // Validate required methods exist
        $requiredMethods = [
            'exchangeCode',
            'refreshToken',
            'getValidToken',
            'cacheAccessToken',
            'hasValidToken',
            'getRefreshToken',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "TokenManagerInterface should have {$method} method"
            );
        }
    }

    /**
     * Test TokenManager implements TokenManagerInterface.
     */
    public function test_token_manager_implements_interface(): void
    {
        $this->assertTrue(
            class_exists(TokenManager::class),
            'TokenManager class should exist'
        );

        $reflection = new \ReflectionClass(TokenManager::class);

        // Validate implements interface
        $this->assertTrue(
            $reflection->implementsInterface(TokenManagerInterface::class),
            'TokenManager should implement TokenManagerInterface'
        );

        // Validate all required methods are implemented
        $requiredMethods = [
            'exchangeCode',
            'refreshToken',
            'getValidToken',
            'cacheAccessToken',
            'hasValidToken',
            'getRefreshToken',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "TokenManager should have {$method} method"
            );

            $methodReflection = $reflection->getMethod($method);
            $this->assertTrue(
                $methodReflection->isPublic(),
                "TokenManager::{$method} should be public"
            );
        }
    }

    /**
     * Test scraper method return types are correct.
     */
    public function test_scraper_method_return_types(): void
    {
        $config = new ScraperConfig(
            identifier: 'jumbo',
            baseUrl: 'https://mobileapi.jumbo.com/v17',
            headers: [],
            rateLimitDelay: 0,
            maxRetries: 1,
            timeout: 10
        );

        $scraper = new JumboScraper($config);

        // Test authenticate returns bool
        $result = $scraper->authenticate();
        $this->assertIsBool(
            $result,
            'authenticate() should return bool'
        );

        // Test getIdentifier returns string
        $identifier = $scraper->getIdentifier();
        $this->assertIsString(
            $identifier,
            'getIdentifier() should return string'
        );

        // Test getCategories returns Collection
        $categories = $scraper->getCategories();
        $this->assertInstanceOf(
            Collection::class,
            $categories,
            'getCategories() should return Collection'
        );
    }

    /**
     * Test all scrapers can be instantiated.
     */
    public function test_all_scrapers_can_be_instantiated(): void
    {
        // Test JumboScraper
        $jumboConfig = new ScraperConfig(
            identifier: 'jumbo',
            baseUrl: 'https://mobileapi.jumbo.com/v17',
            headers: [],
            rateLimitDelay: 0,
            maxRetries: 1,
            timeout: 10
        );

        $jumboScraper = new JumboScraper($jumboConfig);
        $this->assertInstanceOf(
            JumboScraper::class,
            $jumboScraper,
            'JumboScraper should be instantiable'
        );

        $this->assertInstanceOf(
            SupermarketScraperInterface::class,
            $jumboScraper,
            'JumboScraper should implement SupermarketScraperInterface'
        );

        // Test AhScraper
        $ahConfig = new ScraperConfig(
            identifier: 'ah',
            baseUrl: 'https://api.ah.nl',
            headers: [],
            rateLimitDelay: 0,
            maxRetries: 1,
            timeout: 10
        );

        $tokenManager = $this->createMock(TokenManagerInterface::class);
        $ahScraper = new AhScraper($ahConfig, $tokenManager);

        $this->assertInstanceOf(
            AhScraper::class,
            $ahScraper,
            'AhScraper should be instantiable'
        );

        $this->assertInstanceOf(
            SupermarketScraperInterface::class,
            $ahScraper,
            'AhScraper should implement SupermarketScraperInterface'
        );
    }

    /**
     * Test TokenManager can be instantiated.
     */
    public function test_token_manager_can_be_instantiated(): void
    {
        $tokenManager = new TokenManager;

        $this->assertInstanceOf(
            TokenManager::class,
            $tokenManager,
            'TokenManager should be instantiable'
        );

        $this->assertInstanceOf(
            TokenManagerInterface::class,
            $tokenManager,
            'TokenManager should implement TokenManagerInterface'
        );
    }

    /**
     * Test scraper config is properly used.
     */
    public function test_scraper_config_is_properly_used(): void
    {
        $config = new ScraperConfig(
            identifier: 'test',
            baseUrl: 'https://api.test.com',
            headers: ['X-Custom' => 'value'],
            rateLimitDelay: 500,
            maxRetries: 5,
            timeout: 30
        );

        $scraper = new JumboScraper($config);

        // Config should be accessible (via reflection if needed)
        $reflection = new \ReflectionClass($scraper);
        $configProperty = $reflection->getProperty('config');
        $actualConfig = $configProperty->getValue($scraper);

        $this->assertEquals(
            $config->identifier,
            $actualConfig->identifier,
            'Config identifier should match'
        );

        $this->assertEquals(
            $config->baseUrl,
            $actualConfig->baseUrl,
            'Config baseUrl should match'
        );

        $this->assertEquals(
            $config->rateLimitDelay,
            $actualConfig->rateLimitDelay,
            'Config rateLimitDelay should match'
        );

        $this->assertEquals(
            $config->maxRetries,
            $actualConfig->maxRetries,
            'Config maxRetries should match'
        );
    }
}
