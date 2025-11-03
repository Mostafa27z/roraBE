<?php

use Illuminate\Support\Facades\Route;
use Modules\Orders\Http\Controllers\OrdersController;

Route::prefix('orders')->middleware('auth:sanctum')->group(function () {

    
    Route::post('/', [OrdersController::class, 'store']);
    Route::get('/my', [OrdersController::class, 'myOrders']);

    
    Route::middleware('role:admin')->group(function () {
        Route::get('/', [OrdersController::class, 'index']);
        Route::put('/{id}/status', [OrdersController::class, 'updateStatus']);
    });
});
