<?php

declare(strict_types=1);

namespace App\Domain\Scraper\Services;

use App\Domain\Scraper\Contracts\SupermarketScraperInterface;
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

        return App::make($scraperClass);
    }

    /**
     * Get all registered scrapers.
     *
     * @return Collection<SupermarketScraperInterface>
     */
    public function all(): Collection
    {
        return collect($this->scrapers)
            ->map(fn (string $class) => App::make($class));
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
     * Scans the Infrastructure/Scraper/Http directory for scraper classes.
     */
    public function discover(): void
    {
        $scraperPath = app_path('Infrastructure/Scraper/Http');

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

            $fullClassName = "App\\Infrastructure\\Scraper\\Http\\{$className}";

            if (! class_exists($fullClassName)) {
                continue;
            }

            $reflection = new \ReflectionClass($fullClassName);

            if ($reflection->isAbstract() || ! $reflection->implementsInterface(SupermarketScraperInterface::class)) {
                continue;
            }

            // Create instance to get identifier
            try {
                $instance = App::make($fullClassName);
                $identifier = $instance->getIdentifier();

                $this->register($identifier, $fullClassName);
            } catch (\Throwable $e) {
                // Skip if instantiation fails
                continue;
            }
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
            ->map(fn (string $class) => App::make($class));
    }
}
