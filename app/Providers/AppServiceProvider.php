<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->registerScrapers();
    }

    /**
     * Register scraper services and implementations.
     */
    protected function registerScrapers(): void
    {
        // Bind TokenManager interface
        $this->app->bind(
            \App\Contracts\Scraper\TokenManagerInterface::class,
            \App\Services\Scraper\TokenManager::class
        );

        // Bind ScraperRegistry as singleton
        $this->app->singleton(\App\Services\Scraper\ScraperRegistry::class);

        // Bind AhScraper with its dependencies
        $this->app->bind(\App\Infrastructure\Scraper\Http\AhScraper::class, function ($app) {
            return new \App\Infrastructure\Scraper\Http\AhScraper(
                \App\DataTransferObjects\Scraper\ScraperConfig::forAh(),
                $app->make(\App\Contracts\Scraper\TokenManagerInterface::class)
            );
        });

        // Bind JumboScraper with its dependencies
        $this->app->bind(\App\Infrastructure\Scraper\Http\JumboScraper::class, function ($app) {
            return new \App\Infrastructure\Scraper\Http\JumboScraper(
                \App\DataTransferObjects\Scraper\ScraperConfig::forJumbo()
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->discoverScrapers();
    }

    /**
     * Auto-discover and register scrapers.
     */
    protected function discoverScrapers(): void
    {
        $registry = $this->app->make(\App\Services\Scraper\ScraperRegistry::class);
        $registry->discover();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
