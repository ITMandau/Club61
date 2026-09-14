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

// 2. Layar POS Kasir Frontdesk & KDS Dapur (Wajib Auth & Otorisasi Staf)
Route::middleware(['auth'])->group(function () {
    Route::get('/pos', function () {
        if (! in_array(auth()->user()->role, ['SUPER_ADMIN', 'ADMIN', 'CASHIER'])) {
            abort(403, 'Akses Ditolak: Hanya staf kasir atau admin yang dapat mengakses terminal POS.');
        }
        return view('pos.index');
    })->name('pos.index');

    Route::post('/pos/check-in', function (\Illuminate\Http\Request $request, \App\Services\Padel\PadelBookingService $service) {
        $user = auth()->user();
        if (! $user || ! in_array($user->role, ['SUPER_ADMIN', 'ADMIN', 'CASHIER'])) {
            return response()->json([
                'success' => false,
                'message' => 'Akses Ditolak: Hanya staf kasir atau admin yang berhak melakukan check-in tiket.',
            ], 403);
        }

        $code = trim($request->input('code') ?? $request->input('qr_code_hash') ?? $request->input('booking_code') ?? '');
        if (empty($code)) {
            return response()->json(['success' => false, 'message' => 'Kode tiket atau QR wajib diisi.'], 422);
        }

        try {
            $result = $service->checkIn($code, $user);
            return response()->json(['success' => true, 'data' => $result]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    })->name('pos.checkin');

    // 3. Layar Monitor Dapur / KOT (Kitchen Display System)
    Route::get('/kitchen', function () {
        if (! in_array(auth()->user()->role, ['SUPER_ADMIN', 'ADMIN', 'KITCHEN'])) {
            abort(403, 'Akses Ditolak: Hanya staf dapur atau admin yang dapat mengakses KDS.');
        }
        return view('kitchen.kds');
    })->name('kitchen.kds');
});

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
