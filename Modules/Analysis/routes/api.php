<?php

use Illuminate\Support\Facades\Route;
use Modules\Analysis\Http\Controllers\SalesAnalysisController;
use Modules\Analysis\Http\Controllers\InventoryAnalysisController;
use Modules\Analysis\Http\Controllers\CustomerAnalysisController;
Route::middleware(['auth:sanctum', 'role:admin'])
    ->group(function () {
        Route::get('/analysis/sales', [SalesAnalysisController::class, 'index']);
        Route::get('/analysis/customers', [CustomerAnalysisController::class, 'index']);
        Route::get('/analysis/inventory', [InventoryAnalysisController::class, 'index']);
    });


