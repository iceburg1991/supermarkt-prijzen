<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\SupermarketController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Supermarket routes
    Route::get('supermarkets', [SupermarketController::class, 'dashboard'])->name('supermarkets.dashboard');
    Route::post('supermarkets/{identifier}/sync', [SupermarketController::class, 'sync'])->name('supermarkets.sync');

    // Product routes
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{productId}/{supermarket}', [ProductController::class, 'show'])->name('products.show');
});

require __DIR__.'/settings.php';
