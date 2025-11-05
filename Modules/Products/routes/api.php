<?php

use Illuminate\Support\Facades\Route;
use Modules\Products\Http\Controllers\ProductsController;
use Modules\Products\Http\Controllers\CategoryController;

Route::prefix('products')->group(function () {

    // 🟢 Public routes (fixed paths first)
    Route::get('active', [ProductsController::class, 'active']);
    Route::get('categories/active', [CategoryController::class, 'active']);

    // 🔒 Admin-only routes for categories
    Route::middleware(['auth:sanctum', 'role:admin'])->prefix('categories')->group(function () {
        Route::get('/', [CategoryController::class, 'index']);
        Route::post('/', [CategoryController::class, 'store']);
        Route::get('{category}', [CategoryController::class, 'show']);
        Route::put('{category}', [CategoryController::class, 'update']);
        Route::delete('{category}', [CategoryController::class, 'destroy']);
    });

    // 🔒 Admin-only routes for products
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::get('/', [ProductsController::class, 'index']);
        Route::post('/', [ProductsController::class, 'store']);
        Route::put('{product}', [ProductsController::class, 'update']);
        Route::delete('{product}', [ProductsController::class, 'destroy']);
    });

    // 🟡 Dynamic route last (public product detail)
    Route::get('{product}', [ProductsController::class, 'show']);
});