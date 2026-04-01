<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\ScraperSettingsController;
use App\Http\Controllers\SupermarketController;
use Illuminate\Support\Facades\Route;

// Redirect home to dashboard (auto-login middleware handles authentication)
Route::redirect('/', '/supermarkets')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Supermarket routes
    Route::get('supermarkets', [SupermarketController::class, 'dashboard'])->name('supermarkets.dashboard');
    Route::post('supermarkets/{identifier}/sync', [SupermarketController::class, 'sync'])->name('supermarkets.sync');

    // Product routes
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{productId}/{supermarket}', [ProductController::class, 'show'])->name('products.show');

    // Scraper settings routes
    Route::get('settings/scrapers', [ScraperSettingsController::class, 'index'])->name('settings.scrapers');
    Route::post('settings/scrapers/token', [ScraperSettingsController::class, 'storeToken'])->name('settings.scrapers.token.store');
    Route::delete('settings/scrapers/token/{supermarket}', [ScraperSettingsController::class, 'destroyToken'])->name('settings.scrapers.token.destroy');
});

require __DIR__.'/settings.php';
