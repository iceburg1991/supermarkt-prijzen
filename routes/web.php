<?php

use App\Http\Controllers\ProductController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::inertia('/', 'Welcome', [
    'canRegister' => Features::enabled(Features::registration()),
])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    // Product routes
    Route::get('products', [ProductController::class, 'index'])->name('products.index');
    Route::get('products/{productId}/{supermarket}', [ProductController::class, 'show'])->name('products.show');
});

require __DIR__.'/settings.php';
