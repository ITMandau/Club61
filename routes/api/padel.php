<?php

use App\Http\Controllers\Api\V1\Padel\PadelBookingController;
use App\Http\Controllers\Api\V1\Padel\PadelCourtController;
use App\Http\Controllers\Api\V1\Payment\MidtransWebhookController;
use App\Http\Controllers\Api\V1\Payment\PaymentWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/padel')->group(function () {

    // 1. ENDPOINT PUBLIK (Dapat diakses tanpa login)
    Route::get('/courts', [PadelCourtController::class, 'index']);
    Route::get('/schedule', [PadelBookingController::class, 'schedule']);
    Route::get('/equipments', [PadelBookingController::class, 'equipments']);

    // 2. ENDPOINT WEBHOOK PAYMENT GATEWAY (Publik, Multi-Driver)
    Route::post('/webhook/midtrans', [MidtransWebhookController::class, 'handle']);
    Route::post('/webhook/{driver}', [PaymentWebhookController::class, 'handle'])->name('api.payment.webhook');

    // 3. ENDPOINT PRIVATE (Wajib Auth: Sanctum Token untuk Flutter, Session Cookie untuk Web Portal)
    Route::middleware(['auth:sanctum,web'])->group(function () {
        // Hold & Release Slot
        Route::post('/hold-slot', [PadelBookingController::class, 'hold'])->middleware('throttle:booking-throttle');
        Route::post('/release-slot', [PadelBookingController::class, 'release']);

        // Checkout & Payment Initialization (Midtrans Snap)
        Route::post('/checkout', [PadelBookingController::class, 'checkout']);

        // Riwayat & E-Tiket Member
        Route::get('/my-bookings', [PadelBookingController::class, 'myBookings']);
        Route::get('/bookings/{id}/ticket', [PadelBookingController::class, 'ticket']);
        Route::post('/bookings/{id}/retry-payment', [PadelBookingController::class, 'retryPayment']);
        Route::post('/bookings/{id}/refund', [PadelBookingController::class, 'refund']);

        // 4. ENDPOINT STAF GATE & KASIR VENUE (Single-Use QR Scanner)
        Route::post('/check-in', [PadelBookingController::class, 'checkIn']);
    });
});
