<?php

use App\Http\Controllers\Api\V1\Pos\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/payments')->group(function () {
    Route::post('/webhook', [PaymentController::class, 'webhook']);

    // Simulator hanya aktif pada lingkungan pengembangan lokal atau testing
    if (app()->environment('local', 'testing')) {
        Route::post('/simulate', [PaymentController::class, 'simulate']);
    }
});
