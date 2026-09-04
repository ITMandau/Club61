<?php

use App\Http\Controllers\Api\V1\Pos\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/payments')->group(function () {
    Route::post('/webhook', [PaymentController::class, 'webhook']);
    Route::post('/simulate', [PaymentController::class, 'simulate']);
});
