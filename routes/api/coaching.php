<?php

use App\Http\Controllers\Api\V1\Coaching\CoachController;
use Illuminate\Support\Facades\Route;

// Modul 24 Opsi B Tahap 2 — profil coach (publik, read-only). Customer tidak booking coach dari aplikasi.
Route::prefix('v1/coaching')->middleware('throttle:60,1')->group(function () {
    Route::get('/coaches', [CoachController::class, 'index'])->name('api.coaching.coaches');
});
