<?php

use App\Http\Controllers\Api\V1\Membership\MembershipController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/membership')->group(function () {
    // Publik: Katalog paket membership
    Route::get('/plans', [MembershipController::class, 'plans']);

    // Autentikasi Member (Sanctum Token & Web Session)
    Route::middleware(['auth:sanctum,web'])->group(function () {
        Route::post('/checkout', [MembershipController::class, 'checkout']);
        Route::get('/my-membership', [MembershipController::class, 'myMembership']);
        Route::get('/my-purchases', [MembershipController::class, 'myPurchases']);
        Route::post('/purchases/{id}/pay', [MembershipController::class, 'payPurchase'])->middleware('throttle:10,1');
        Route::post('/purchases/{id}/cancel', [MembershipController::class, 'cancelPurchase'])->middleware('throttle:10,1');
        Route::get('/history', [MembershipController::class, 'history']);
        Route::post('/checkin-gym', [MembershipController::class, 'checkinGym']);
        Route::post('/checkin', [MembershipController::class, 'checkin']);
    });
});
