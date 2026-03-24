<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Scraper\Repositories\AnalyticsRepository;
use App\Models\NormalizedCategory;
use App\Models\Product;
use App\Models\Supermarket;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for product listing and detail pages.
 */
class ProductController extends Controller
{
    /**
     * Display a listing of products with filters.
     */
    public function index(Request $request): Response
    {
        // Get filter parameters
        $search = $request->input('search');
        $supermarket = $request->input('supermarket');
        $category = $request->input('category');
        $promotionsOnly = $request->boolean('promotions');
        $perPage = $request->integer('per_page', 24);

        // Build query
        $query = Product::query()
            ->with(['supermarketModel'])
            ->orderBy('name');

        // Apply filters
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        if ($supermarket) {
            $query->where('supermarket', $supermarket);
        }

        if ($category) {
            $query->whereHas('categories.normalizedCategories', function ($q) use ($category) {
                $q->where('normalized_categories.id', $category);
            });
        }

        if ($promotionsOnly) {
            $query->whereHas('latestPrice', function ($q) {
                $q->where('promo_price_cents', '>', 0);
            });
        }

        // Paginate results
        $products = $query->paginate($perPage)->withQueryString();

        // Manually append latest_price to each product (since accessor doesn't work with pagination)
        $products->getCollection()->transform(function ($product) {
            $product->latest_price = $product->latest_price; // Trigger accessor

            return $product;
        });

        // Get filter options
        $supermarkets = Supermarket::where('enabled', true)
            ->orderBy('name')
            ->get(['identifier', 'name']);

        $categories = NormalizedCategory::whereNull('parent_id')
            ->orderBy('name')
            ->get(['id', 'name']);

        // Get last scrape run per supermarket
        $lastScrapeRuns = \App\Models\ScrapeRun::query()
            ->selectRaw('supermarket, MAX(completed_at) as last_scraped_at')
            ->where('status', 'completed')
            ->whereNotNull('completed_at')
            ->groupBy('supermarket')
            ->get()
            ->mapWithKeys(function ($run) {
                return [
                    $run->supermarket => [
                        'supermarket' => $run->supermarket,
                        // Cast to Carbon and format as ISO 8601 for JavaScript
                        'last_scraped_at' => \Carbon\Carbon::parse($run->last_scraped_at)->toIso8601String(),
                    ],
                ];
            });

        return Inertia::render('Products/Index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'supermarket' => $supermarket,
                'category' => $category,
                'promotions' => $promotionsOnly,
            ],
            'supermarkets' => $supermarkets,
            'categories' => $categories,
            'lastScrapeRuns' => $lastScrapeRuns,
        ]);
    }

    /**
     * Display the specified product with price history.
     */
    public function show(string $productId, string $supermarket): Response
    {
        $analytics = new AnalyticsRepository;

        // Get product with relationships
        $product = Product::where('product_id', $productId)
            ->where('supermarket', $supermarket)
            ->with([
                'supermarketModel',
                'categories.normalizedCategories',
            ])
            ->firstOrFail();

        // Manually load latest_price using accessor
        $product->latest_price = $product->latest_price;

        // Get price history (90 days)
        $priceHistory = $analytics->getPriceHistory($productId, $supermarket, 90);

        // Get average price (30 days)
        $averagePrice = $analytics->getAveragePrice($productId, $supermarket, 30);

        // Get price change percentage (7 days)
        $priceChange = $analytics->getPriceChangePercentage($productId, $supermarket, 7);

        // Get similar products from other supermarkets
        $similarProducts = Product::where('name', 'like', "%{$product->name}%")
            ->where('supermarket', '!=', $supermarket)
            ->with(['supermarketModel'])
            ->limit(5)
            ->get();

        // Manually append latest_price to each similar product
        $similarProducts->transform(function ($product) {
            $product->latest_price = $product->latest_price;

            return $product;
        });

        return Inertia::render('Products/Show', [
            'product' => $product,
            'priceHistory' => $priceHistory,
            'averagePrice' => $averagePrice,
            'priceChange' => $priceChange,
            'similarProducts' => $similarProducts,
        ]);
    }
}
