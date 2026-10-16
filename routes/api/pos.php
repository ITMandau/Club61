<?php

use App\Http\Controllers\Api\V1\Pos\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/payments')->group(function () {
    Route::post('/webhook', [PaymentController::class, 'webhook']);

    // Notifikasi DOKU (Modul 27). URL ini yang didaftarkan di dashboard DOKU per kanal; path-nya ikut tanda tangan.
    Route::post('/doku/notification', \App\Http\Controllers\Api\V1\Payment\DokuNotificationController::class)
        ->middleware('throttle:300,1')
        ->name('api.payment.doku.notification');

    // Simulator hanya aktif pada lingkungan pengembangan lokal atau testing
    if (app()->environment('local', 'testing')) {
        Route::post('/simulate', [PaymentController::class, 'simulate']);
    }
});
