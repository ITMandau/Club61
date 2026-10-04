<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\OnlinePaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Daftar metode pembayaran online yang sedang aktif — dipakai aplikasi mobile supaya tidak menulis daftar
 * sendiri (sumber yang sama dengan halaman checkout web & validasi server).
 */
class PaymentMethodController extends Controller
{
    public function index(Request $request, OnlinePaymentMethodService $methods): JsonResponse
    {
        $validated = $request->validate([
            // Isi = hanya metode yang boleh dipakai untuk total tagihan ini (batas nominal, mis. QRIS maks Rp10 juta).
            'amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Daftar metode pembayaran online yang tersedia.',
            'data' => $methods->forFrontend(isset($validated['amount']) ? (float) $validated['amount'] : null),
        ]);
    }
}
