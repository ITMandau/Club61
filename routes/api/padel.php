<?php

use App\Http\Controllers\Api\V1\Padel\PadelBookingController;
use App\Http\Controllers\Api\V1\Padel\PadelCourtController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/padel')->group(function () {
    Route::get('/courts', [PadelCourtController::class, 'index']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/hold-slot', [PadelBookingController::class, 'hold'])->middleware('throttle:booking-throttle');
        Route::get('/my-bookings', [PadelBookingController::class, 'myBookings']);
    });
});
