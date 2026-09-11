<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Layar Browser Laptop & Monitor Venue)
|--------------------------------------------------------------------------
*/

// 1. Layar Web Customer (Depan)
Route::get('/', function () {
    return view('welcome');
});

// 2. Layar POS Kasir Frontdesk
Route::get('/pos', function () {
    return view('pos.index');
})->name('pos.index');

Route::post('/pos/check-in', function (\Illuminate\Http\Request $request, \App\Services\Padel\PadelBookingService $service) {
    $code = trim($request->input('code') ?? $request->input('qr_code_hash') ?? $request->input('booking_code') ?? '');
    if (empty($code)) {
        return response()->json(['success' => false, 'message' => 'Kode tiket atau QR wajib diisi.'], 422);
    }
    $user = auth()->user() ?? \App\Models\User::where('role', 'ADMIN')->first();
    try {
        $result = $service->checkIn($code, $user);
        return response()->json(['success' => true, 'data' => $result]);
    } catch (\Throwable $e) {
        return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
    }
})->name('pos.checkin');

// 3. Layar Monitor Dapur / KOT (Kitchen Display System)
Route::get('/kitchen', function () {
    return view('kitchen.kds');
})->name('kitchen.kds');

// 4. Dashboard Member / Customer
Route::get('/dashboard', function () {
    return view('dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/booking', function () {
        return view('customer.booking');
    })->name('customer.booking');

    Route::get('/cart', function () {
        return view('customer.cart');
    })->name('customer.cart');

    Route::get('/checkout', function () {
        return view('customer.checkout');
    })->name('customer.checkout');

    Route::get('/my-club', function () {
        return view('customer.my-club');
    })->name('customer.my-club');

    Route::get('/invoice', function () {
        return view('customer.invoice');
    })->name('customer.invoice');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
