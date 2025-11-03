<?php

use Illuminate\Support\Facades\Route;
use Modules\Products\Http\Controllers\ProductsController;
use Modules\Products\Http\Controllers\CategoryController;

Route::prefix('products')->group(function () {

    // Public routes
    Route::get('active', [ProductsController::class, 'active']);
    Route::get('categories/active', [CategoryController::class, 'active']);

   
    Route::get('{product}', [ProductsController::class, 'show']);

    //  Admin-only routes
    Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
        Route::apiResource('categories', CategoryController::class)->except(['create', 'edit']);
        Route::apiResource('/', ProductsController::class)
            ->parameters(['' => 'product'])
            ->except(['create', 'edit', 'show']); // exclude show since it's public
    });
});
