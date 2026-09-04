<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-throttle');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-throttle');

    // Strictly protected by auth:sanctum (Anti-Bentrok Session Web)
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});
