<?php

declare(strict_types=1);

namespace App\Services\Scraper;

use App\Contracts\Scraper\SupermarketScraperInterface;
use App\DataTransferObjects\Scraper\ScraperConfig;
use App\Models\Supermarket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

/**
 * Registry for managing scraper implementations.
 *
 * Provides auto-discovery and resolution of scraper instances.
 */
class ScraperRegistry
{
    /**
     * Registered scrapers.
     *
     * @var array<string, string>
     */
    private array $scrapers = [];

    /**
     * Register a scraper implementation.
     *
     * @param  string  $identifier  Supermarket identifier (e.g., 'ah', 'jumbo')
     * @param  string  $scraperClass  Fully qualified class name
     */
    public function register(string $identifier, string $scraperClass): void
    {
        $this->scrapers[$identifier] = $scraperClass;
    }

    /**
     * Get scraper instance by identifier.
     *
     * @throws \InvalidArgumentException If scraper not found
     */
    public function get(string $identifier): SupermarketScraperInterface
    {
        if (! $this->has($identifier)) {
            throw new \InvalidArgumentException("Scraper not found for identifier: {$identifier}");
        }

        $scraperClass = $this->scrapers[$identifier];

        // Create appropriate config based on identifier
        $config = match ($identifier) {
            'ah' => ScraperConfig::forAh(),
            'jumbo' => ScraperConfig::forJumbo(),
            default => throw new \InvalidArgumentException("No configuration found for identifier: {$identifier}"),
        };

        return App::make($scraperClass, ['config' => $config]);
    }

    /**
     * Get all registered scrapers.
     *
     * @return Collection<SupermarketScraperInterface>
     */
    public function all(): Collection
    {
        return collect($this->scrapers)
            ->map(function (string $class, string $identifier) {
                // Create appropriate config based on identifier
                $config = match ($identifier) {
                    'ah' => ScraperConfig::forAh(),
                    'jumbo' => ScraperConfig::forJumbo(),
                    default => null,
                };

                if ($config === null) {
                    return null;
                }

                return App::make($class, ['config' => $config]);
            })
            ->filter();
    }

    /**
     * Check if scraper is registered.
     */
    public function has(string $identifier): bool
    {
        return isset($this->scrapers[$identifier]);
    }

    /**
     * Auto-discover scrapers implementing SupermarketScraperInterface.
     *
     * Scans the Http/Scrapers directory for scraper classes.
     */
    public function discover(): void
    {
        $scraperPath = app_path('Http/Scrapers');

        if (! is_dir($scraperPath)) {
            return;
        }

        $files = glob($scraperPath.'/*Scraper.php');

        foreach ($files as $file) {
            $className = basename($file, '.php');

            // Skip BaseScraper
            if ($className === 'BaseScraper') {
                continue;
            }

            $fullClassName = "App\\Http\\Scrapers\\{$className}";

            if (! class_exists($fullClassName)) {
                continue;
            }

            $reflection = new \ReflectionClass($fullClassName);

            if ($reflection->isAbstract() || ! $reflection->implementsInterface(SupermarketScraperInterface::class)) {
                continue;
            }

            // Map class name to identifier without instantiation
            // AhScraper -> ah, JumboScraper -> jumbo
            $identifier = strtolower(str_replace('Scraper', '', $className));

            $this->register($identifier, $fullClassName);
        }
    }

    /**
     * Get only enabled scrapers from supermarkets table.
     *
     * @return Collection<SupermarketScraperInterface>
     */
    public function enabled(): Collection
    {
        $enabledIdentifiers = Supermarket::where('enabled', true)
            ->pluck('identifier')
            ->toArray();

        return collect($this->scrapers)
            ->filter(fn (string $class, string $identifier) => in_array($identifier, $enabledIdentifiers))
            ->map(function (string $class, string $identifier) {
                // Create appropriate config based on identifier
                $config = match ($identifier) {
                    'ah' => ScraperConfig::forAh(),
                    'jumbo' => ScraperConfig::forJumbo(),
                    default => null,
                };

                if ($config === null) {
                    return null;
                }

                return App::make($class, ['config' => $config]);
            })
            ->filter();
    }
}
