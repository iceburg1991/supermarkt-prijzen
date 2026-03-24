<?php

namespace App\Providers;

use App\Contracts\Scraper\TokenManagerInterface;
use App\DataTransferObjects\Scraper\ScraperConfig;
use App\Infrastructure\Scraper\Http\AhScraper;
use App\Infrastructure\Scraper\Http\JumboScraper;
use App\Services\Scraper\ScraperRegistry;
use App\Services\Scraper\TokenManager;
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
            TokenManagerInterface::class,
            TokenManager::class
        );

        // Bind ScraperRegistry as singleton
        $this->app->singleton(ScraperRegistry::class);

        // Bind AhScraper with its dependencies
        $this->app->bind(AhScraper::class, function ($app) {
            return new AhScraper(
                ScraperConfig::forAh(),
                $app->make(TokenManagerInterface::class)
            );
        });

        // Bind JumboScraper with its dependencies
        $this->app->bind(JumboScraper::class, function ($app) {
            return new JumboScraper(
                ScraperConfig::forJumbo()
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
        $registry = $this->app->make(ScraperRegistry::class);
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
