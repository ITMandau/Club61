<?php

namespace App\Http\Controllers;

use App\Services\Finance\PromoVoucherService;
use App\Services\Finance\VoucherClaimService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Halaman klaim promo cara sebar C (Modul 22 tahap 3): /promo/{kode} — link yang disebar di banner / bio Instagram.
 * Halaman publik: tamu melihat promonya dulu lalu "Masuk untuk klaim" → setelah login / daftar kembali ke halaman ini
 * (url.intended). Tombol Klaim (POST) tetap wajib login akun customer.
 */
class PromoClaimController extends Controller
{
    public function show(Request $request, string $code, VoucherClaimService $claims): View
    {
        $voucher = $claims->findClaimable($code);
        abort_unless($voucher, 404);

        $user = $request->user();

        $claimCount = $voucher->claims()->count();
        $daysLeft = $voucher->valid_until
            ? (int) now('Asia/Jakarta')->startOfDay()->diffInDays($voucher->valid_until->copy()->timezone('Asia/Jakarta')->startOfDay(), false)
            : null;

        return view('customer.promo-claim', [
            'voucher' => $voucher,
            'state' => $claims->stateFor($voucher, $user),
            'terms' => app(PromoVoucherService::class)->terms($voucher),
            'viewer' => match (true) {
                ! $user => 'GUEST',
                $user->isCustomer() => 'CUSTOMER',
                default => 'STAFF',
            },
            'claimCount' => $claimCount,
            'totalClaims' => $voucher->quota === null ? null : (int) $voucher->quota + $claimCount,
            'daysLeft' => $daysLeft,
        ]);
    }

    /**
     * Tombol "Masuk untuk klaim" / "Buat akun" tamu: simpan promo ini sebagai tujuan setelah login / daftar
     * (AuthenticatedSessionController::customerIntended), lalu ke halaman login / daftar.
     */
    public function auth(string $code, string $mode, VoucherClaimService $claims): RedirectResponse
    {
        $voucher = $claims->findClaimable($code);
        abort_unless($voucher, 404);
        if (auth()->check()) {
            return redirect()->route('promo.claim', $voucher->code);
        }

        redirect()->setIntendedUrl(route('promo.claim', $voucher->code, absolute: false));

        return redirect()->route($mode === 'daftar' ? 'register' : 'login');
    }

    public function claim(Request $request, string $code, VoucherClaimService $claims): RedirectResponse
    {
        $voucher = $claims->findClaimable($code);
        abort_unless($voucher, 404);

        try {
            $claims->claim($voucher, $request->user());
        } catch (HttpException $e) {
            return redirect()->route('promo.claim', $voucher->code)->with('promo_error', $e->getMessage());
        }

        return redirect()->route('promo.claim', $voucher->code)->with('promo_claimed', true);
    }
}
