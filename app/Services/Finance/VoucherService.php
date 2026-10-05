<?php

namespace App\Services\Finance;

use App\Models\Pos\Order;
use App\Models\Pos\Refund;
use App\Models\Pos\Voucher;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Satu tempat aturan voucher: validasi & besar potongan saat checkout (online maupun kasir), pemotongan kuota / saldo
 * saat order lunas, dan penerbitan voucher saldo dari refund yang ditolak (Modul 21).
 *
 * Voucher saldo (CREDIT) bukan uang masuk baru: uangnya sudah tercatat di Buku Transaksi saat pembayaran awal. Saat
 * dipakai ia tercatat sebagai potongan (discount) order, jadi tidak dihitung dua kali.
 */
class VoucherService
{
    public const CREDIT_VALID_MONTHS = 6;

    /** Order yang sudah mengklaim potongan voucher tapi belum dibayar sama sekali (saldonya sedang "dipesan"). */
    private const RESERVING_STATUSES = ['UNPAID', 'PENDING_PAYMENT'];

    /**
     * Potongan voucher untuk satu order. Wajib dipanggil di dalam transaksi DB kalau $lock = true.
     *
     * @return array{voucher: ?Voucher, discount: float, error: ?string}
     */
    public function resolve(?string $code, ?User $customer, float $orderAmount, bool $lock = false): array
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '') {
            return ['voucher' => null, 'discount' => 0.0, 'error' => null];
        }

        $query = Voucher::where('code', $code);
        if ($lock) {
            $query->lockForUpdate();
        }
        $voucher = $query->first();

        $fail = fn (string $message) => ['voucher' => null, 'discount' => 0.0, 'error' => $message];

        if (! $voucher || ! $voucher->is_active) {
            return $fail('Kode voucher tidak ditemukan atau sudah tidak aktif.');
        }
        if ($voucher->valid_until && $voucher->valid_until->lt(now())) {
            return $fail('Voucher sudah kedaluwarsa ('.$voucher->valid_until->timezone(LedgerReport::TIMEZONE)->format('d M Y').').');
        }
        if ($voucher->user_id && $voucher->user_id !== $customer?->id) {
            return $fail('Voucher ini milik akun customer lain.');
        }
        if ($orderAmount <= 0) {
            return $fail('Tidak ada tagihan yang bisa dipotong voucher.');
        }

        if ($voucher->isCredit()) {
            $available = $this->availableBalance($voucher);
            if ($available < 1) {
                return $fail('Saldo voucher sudah habis atau sedang dipakai di pesanan lain yang belum dibayar.');
            }

            return ['voucher' => $voucher, 'discount' => round(min($available, $orderAmount), 2), 'error' => null];
        }

        // Promo: kuota dipotong permanen saat lunas (consume), jadi order yang sudah mengklaim kode ini tapi belum
        // lunas ikut dihitung — kalau tidak, checkout paralel saat kuota tinggal 1 bisa sama-sama lolos.
        if ($voucher->quota !== null) {
            $reservedInFlight = Order::where('voucher_code', $voucher->code)
                ->whereIn('payment_status', ['UNPAID', 'PENDING_PAYMENT', 'PARTIALLY_PAID'])
                ->count();
            if ($voucher->quota - $reservedInFlight <= 0) {
                return $fail('Kuota voucher sudah habis.');
            }
        }

        $minOrder = (float) ($voucher->min_order_amount ?? 0);
        if ($orderAmount < $minOrder) {
            return $fail('Minimal transaksi untuk voucher ini Rp '.number_format($minOrder, 0, ',', '.').'.');
        }

        if ($voucher->discount_type === 'PERCENT') {
            $calc = $orderAmount * ((float) $voucher->discount_value / 100);
            $discount = $voucher->max_discount_amount ? min($calc, (float) $voucher->max_discount_amount) : $calc;
        } else {
            $discount = (float) $voucher->discount_value;
        }

        return ['voucher' => $voucher, 'discount' => round(min($discount, $orderAmount), 2), 'error' => null];
    }

    /** Sisa saldo voucher CREDIT dikurangi potongan yang sedang dipesan order belum bayar. */
    public function availableBalance(Voucher $voucher): float
    {
        if (! $voucher->isCredit()) {
            return 0.0;
        }

        $reserved = (float) Order::where('voucher_code', $voucher->code)
            ->whereIn('payment_status', self::RESERVING_STATUSES)
            ->sum('discount_amount');

        return max(0.0, round((float) $voucher->balance - $reserved, 2));
    }

    /**
     * Potong kuota / saldo voucher order ini. Dipanggil sekali, pada pembayaran sukses pertama order
     * (PaymentOrchestratorService::markOrderAsPaid) — pelunasan selisih reschedule tidak memotong lagi.
     */
    public function consume(Order $order): void
    {
        if (! $order->voucher_code) {
            return;
        }

        $voucher = Voucher::where('code', $order->voucher_code)->lockForUpdate()->first();
        if (! $voucher) {
            return;
        }

        if ($voucher->isCredit()) {
            $voucher->balance = max(0, round((float) $voucher->balance - (float) $order->discount_amount, 2));
            $voucher->used_count = (int) $voucher->used_count + 1;
            $voucher->save();

            return;
        }

        if ($voucher->quota !== null && $voucher->quota > 0) {
            $voucher->decrement('quota');
        }
        $voucher->increment('used_count');
    }

    /**
     * Semua booking order yang dibayar sebagian pakai voucher saldo sudah batal → potongan voucher dikembalikan ke
     * saldonya (uang tunainya diurus lewat refund). Idempoten lewat penanda di order. Wajib di dalam transaksi DB.
     */
    public function restoreCreditForCancelledOrder(Order $order): void
    {
        if (! $order->voucher_code || (float) $order->discount_amount <= 0 || ! empty($order->voucher_restored_at)) {
            return;
        }

        // Hanya kalau SEMUA booking order ini batal. Kalau ada yang sudah dimainkan / hangus / check-in, sebagian voucher
        // memang sudah terpakai — dulu tetap dikembalikan penuh sehingga customer mendapat jam main gratis.
        if (\App\Models\Padel\PadelBooking::where('order_id', $order->id)->whereNotIn('status', ['CANCELLED', 'REFUND_PENDING', 'REFUNDED'])->exists()) {
            return;
        }

        $voucher = Voucher::where('code', $order->voucher_code)->lockForUpdate()->first();
        if (! $voucher || ! $voucher->isCredit() || ! $order->payments()->where('status', 'SUCCESS')->exists()) {
            return; // belum lunas = saldonya memang belum terpotong
        }

        $voucher->balance = round((float) $voucher->balance + (float) $order->discount_amount, 2);
        // Voucher yang sudah kedaluwarsa saat booking dibatalkan diperpanjang — kalau tidak, saldo yang dikembalikan
        // langsung hangus tanpa jejak. Voucher yang dinonaktifkan admin tetap nonaktif (keputusan admin).
        if ($voucher->valid_until && $voucher->valid_until->lt(now())) {
            $voucher->valid_until = now(LedgerReport::TIMEZONE)->addMonthsNoOverflow(self::CREDIT_VALID_MONTHS)->endOfDay()->setTimezone(config('app.timezone'));
        }
        $voucher->save();
        $order->forceFill(['voucher_restored_at' => now()])->save();

        ActivityLogger::record(
            module: 'FINANCE',
            event: 'voucher.restored',
            description: 'Saldo voucher '.$voucher->code.' dikembalikan '.ActivityLogger::rupiah((float) $order->discount_amount)." (order {$order->order_number} dibatalkan)",
            subject: $order,
            meta: ['voucher' => $voucher->code, 'nominal' => (float) $order->discount_amount, 'no_order' => $order->order_number],
            severity: ActivityLogger::WARNING,
        );
    }

    /**
     * Refund yang ditolak → uangnya tetap di klub sebagai voucher saldo atas nama customer pemilik order.
     * Null kalau order tidak punya akun customer. Wajib di dalam transaksi DB.
     */
    public function issueRefundCredit(Refund $refund): ?Voucher
    {
        $customerId = $refund->order?->user_id;
        // Rupiah utuh: refund yang dibagi proporsional bisa berkoma (Rp133.333,33) dan sisa Rp0,33 tidak pernah bisa dipakai.
        $amount = round((float) $refund->refund_amount);
        if (! $customerId || $amount <= 0) {
            return null;
        }

        do {
            $code = 'KR-'.strtoupper(Str::random(8));
        } while (Voucher::where('code', $code)->exists());

        return Voucher::create([
            'user_id' => $customerId,
            'refund_id' => $refund->id,
            'code' => $code,
            'discount_type' => Voucher::TYPE_CREDIT,
            'discount_value' => $amount,
            'min_order_amount' => 0,
            'balance' => $amount,
            'quota' => null,
            'used_count' => 0,
            // Akhir hari WIB, disimpan dalam zona waktu aplikasi (Eloquent menyimpan jam apa adanya tanpa konversi).
            'valid_until' => now(LedgerReport::TIMEZONE)->addMonthsNoOverflow(self::CREDIT_VALID_MONTHS)->endOfDay()->setTimezone(config('app.timezone')),
            'is_active' => true,
        ]);
    }

    /** Voucher saldo customer yang masih bisa dipakai (untuk halaman customer & kasir). */
    public function walletFor(User $customer): Collection
    {
        return Voucher::where('user_id', $customer->id)
            ->where('discount_type', Voucher::TYPE_CREDIT)
            ->where('is_active', true)
            ->where('balance', '>=', 1)
            ->where(fn ($q) => $q->whereNull('valid_until')->orWhere('valid_until', '>=', now()))
            ->orderBy('valid_until')
            ->get()
            ->map(fn (Voucher $v) => [
                'code' => $v->code,
                'balance' => (float) $v->balance,
                'available' => $this->availableBalance($v),
                'initial' => (float) $v->discount_value,
                'valid_until' => $v->valid_until?->timezone(LedgerReport::TIMEZONE)->format('d M Y'),
            ]);
    }
}
