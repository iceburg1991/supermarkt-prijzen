<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ScrapeRun;
use App\Models\Supermarket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for supermarket dashboard and sync operations.
 */
class SupermarketController extends Controller
{
    /**
     * Display the supermarket dashboard with statistics.
     */
    public function dashboard(): Response
    {
        $supermarkets = Supermarket::where('enabled', true)->get();

        $statistics = [];

        foreach ($supermarkets as $supermarket) {
            // Get product count
            $productCount = Product::where('supermarket', $supermarket->identifier)->count();

            // Get last scrape run
            $lastScrapeRun = ScrapeRun::where('supermarket', $supermarket->identifier)
                ->where('status', 'completed')
                ->whereNotNull('completed_at')
                ->orderBy('completed_at', 'desc')
                ->first();

            // Get total scrape runs
            $totalScrapeRuns = ScrapeRun::where('supermarket', $supermarket->identifier)
                ->where('status', 'completed')
                ->count();

            // Get products with promotions
            $promotionCount = Product::where('supermarket', $supermarket->identifier)
                ->whereHas('latestPrice', function ($q) {
                    $q->where('promo_price_cents', '>', 0);
                })
                ->count();

            $statistics[] = [
                'supermarket' => $supermarket,
                'product_count' => $productCount,
                'promotion_count' => $promotionCount,
                'total_scrape_runs' => $totalScrapeRuns,
                'last_scrape_run' => $lastScrapeRun ? [
                    'completed_at' => $lastScrapeRun->completed_at,
                    'products_scraped' => $lastScrapeRun->products_scraped,
                    'duration_seconds' => $lastScrapeRun->duration_seconds,
                ] : null,
            ];
        }

        return Inertia::render('Supermarkets/Dashboard', [
            'statistics' => $statistics,
        ]);
    }

    /**
     * Trigger a sync for a specific supermarket.
     */
    public function sync(string $identifier): RedirectResponse
    {
        $supermarket = Supermarket::where('identifier', $identifier)
            ->where('enabled', true)
            ->firstOrFail();

        try {
            // Dispatch job to database queue
            \App\Jobs\RunScraper::dispatch($identifier);
            
            return redirect()
                ->route('supermarkets.dashboard')
                ->with('success', "Sync gestart voor {$supermarket->name}. Start de queue worker met: php artisan queue:work");
        } catch (\Throwable $e) {
            \Log::error('Failed to dispatch scraper job', [
                'supermarket' => $identifier,
                'error' => $e->getMessage(),
            ]);
            
            return redirect()
                ->route('supermarkets.dashboard')
                ->with('error', "Kon sync niet starten voor {$supermarket->name}");
        }
    }
}
