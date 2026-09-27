<?php

use App\Http\Controllers\Api\V1\Wellness\WellnessController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/wellness')->group(function () {
    Route::get('/facilities', [WellnessController::class, 'facilities']);
    Route::get('/slots', [WellnessController::class, 'slots']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/book', [WellnessController::class, 'book']);
        Route::post('/cancel', [WellnessController::class, 'cancel']);
    });
});
