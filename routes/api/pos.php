<?php

use App\Http\Controllers\Api\V1\Pos\PaymentController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/payments')->group(function () {
    Route::post('/webhook', [PaymentController::class, 'webhook'])->middleware('throttle:300,1');

    // Notifikasi DOKU (Modul 27). URL ini yang didaftarkan di dashboard DOKU per kanal; path-nya ikut tanda tangan.
    Route::post('/doku/notification', \App\Http\Controllers\Api\V1\Payment\DokuNotificationController::class)
        ->middleware('throttle:300,1')
        ->name('api.payment.doku.notification');

    // DOKU SNAP (Modul 27b): DOKU minta token ke kita dulu, lalu mengirim notifikasi VA / QRIS dengan token itu.
    Route::prefix('doku-snap')->middleware('throttle:300,1')->controller(\App\Http\Controllers\Api\V1\Payment\DokuSnapNotificationController::class)->group(function () {
        Route::post('/v1.0/access-token/b2b', 'token')->name('api.payment.doku-snap.token');
        Route::post('/v1.1/transfer-va/payment', 'virtualAccountPayment')->name('api.payment.doku-snap.va');
        Route::post('/v1.0/transfer-va/payment', 'virtualAccountPayment');
        Route::post('/v1.0/qr/qr-mpm-notify', 'qrisNotify')->name('api.payment.doku-snap.qris');
    });

    // Simulator hanya aktif pada lingkungan pengembangan lokal atau testing
    if (app()->environment('local', 'testing')) {
        Route::post('/simulate', [PaymentController::class, 'simulate']);
    }
});
