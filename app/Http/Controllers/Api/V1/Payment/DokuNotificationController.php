<?php

namespace App\Http\Controllers\Api\V1\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\Doku\DokuNotificationReceiver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Notifikasi DOKU Checkout (Modul 27 §7): verifikasi tanda tangan → simpan per Request-Id → balas 200 → proses setelah
 * respons. Logikanya di DokuNotificationReceiver (dipakai juga notifikasi QRIS SNAP yang berformat sama).
 */
class DokuNotificationController extends Controller
{
    public function __invoke(Request $request, DokuNotificationReceiver $receiver): JsonResponse
    {
        return $receiver->receive($request);
    }
}
