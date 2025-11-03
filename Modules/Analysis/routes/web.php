<?php

use Illuminate\Support\Facades\Route;
use Modules\Analysis\Http\Controllers\AnalysisController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('analyses', AnalysisController::class)->names('analysis');
});
