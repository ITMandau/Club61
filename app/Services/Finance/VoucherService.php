<?php

namespace App\Services\Finance;

use App\Models\Pos\Order;
use App\Models\Pos\Refund;
use App\Models\Pos\Voucher;
use App\Models\Pos\VoucherClaim;
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

    /** Order yang menghitung "sudah memakai promo ini" untuk aturan 1x per akun (T3): lunas + yang masih menunggu bayar. */
    private const PROMO_HOLDING_STATUSES = ['PAID', 'PARTIALLY_PAID', 'UNPAID', 'PENDING_PAYMENT'];

    /**
     * Potongan voucher untuk satu order. Wajib dipanggil di dalam transaksi DB kalau $lock = true.
     *
     * $context (promo, PRD Modul 22 §3–§5):
     *  - channel: 'ONLINE' | 'POS' — kanal transaksi (promo bisa dibatasi online / kasir saja);
     *  - amounts: ['PADEL' => sewa lapangan, 'PADEL_ADDON' => add-on] — promo hanya memotong kategori yang dicentang
     *    (PADEL = sewa lapangan saja). Tanpa amounts, seluruh $orderAmount dianggap sewa lapangan;
     *  - membership_applied: transaksi ini memakai benefit member (promo yang tidak boleh digabung → ditolak).
     * Voucher saldo (CREDIT) tetap memotong seluruh $orderAmount (lapangan + add-on).
     *
     * @return array{voucher: ?Voucher, discount: float, error: ?string}
     */
    public function resolve(?string $code, ?User $customer, float $orderAmount, bool $lock = false, array $context = []): array
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

        // Baris kampanye kode unik (B) hanya wadah aturan — kodenya sendiri tidak bisa dipakai.
        if (! $voucher || ! $voucher->is_active || $voucher->isCampaign()) {
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
            $available = $this->availableBalance($voucher, $lock);
            if ($available < 1) {
                return $fail('Saldo voucher sudah habis atau sedang dipakai di pesanan lain yang belum dibayar.');
            }

            return ['voucher' => $voucher, 'discount' => round(min($available, $orderAmount), 2), 'error' => null];
        }

        // ---- Promo ----
        // Kode unik (B): kampanyenya ikut dikunci (aturan 1 kode per akun per kampanye berlaku lintas kode) & wajib aktif.
        if ($voucher->isUniqueCode()) {
            $campaign = $this->freshRead(Voucher::whereKey($voucher->campaign_id), $lock)->first();
            if (! $campaign || ! $campaign->is_active) {
                return $fail('Kode voucher tidak ditemukan atau sudah tidak aktif.');
            }
        }

        if ($voucher->valid_from && $voucher->valid_from->gt(now())) {
            return $fail('Promo ini baru berlaku mulai '.$voucher->valid_from->timezone(LedgerReport::TIMEZONE)->format('d M Y').'.');
        }

        $channel = $context['channel'] ?? null;
        if ($channel && $voucher->channel === Voucher::CHANNEL_ONLINE && $channel !== Voucher::CHANNEL_ONLINE) {
            return $fail('Promo ini hanya berlaku untuk booking online.');
        }
        if ($channel && $voucher->channel === Voucher::CHANNEL_POS && $channel !== Voucher::CHANNEL_POS) {
            return $fail('Promo ini hanya berlaku untuk transaksi di kasir.');
        }

        if (! empty($context['membership_applied']) && ! $voucher->stack_with_membership) {
            return $fail('Promo ini tidak bisa digabung dengan benefit member. Matikan benefit member untuk memakai promo.');
        }

        // Nilai yang boleh dipotong = jumlah kategori yang dicentang di promo (PADEL = sewa lapangan, tanpa add-on).
        $amounts = $context['amounts'] ?? ['PADEL' => $orderAmount];
        $base = round((float) collect($voucher->promoCategories())->sum(fn (string $category) => (float) ($amounts[$category] ?? 0)), 2);
        if ($base <= 0) {
            return $fail('Promo ini tidak berlaku untuk item di transaksi ini.');
        }

        // T3 — 1x per akun per promo (pembagian merata). Dihitung dari order lunas + yang masih menunggu bayar milik akun
        // ini; order yang kedaluwarsa / batal tidak dihitung lagi, jadi jatahnya kembali (T2).
        if (! $customer) {
            return $fail('Promo ini butuh akun customer.');
        }
        // C — klaim ke akun: hanya akun yang sudah klaim (kuota dihitung saat klaim).
        if ($voucher->isClaim() && ! VoucherClaim::where('voucher_id', $voucher->id)->where('user_id', $customer->id)->exists()) {
            return $fail('Promo ini harus diklaim dulu di halaman promo sebelum dipakai.');
        }
        if ($this->promoUsedBy($voucher, $customer, $lock)) {
            return $fail($voucher->isUniqueCode()
                ? 'Kamu sudah memakai satu kode dari kampanye ini — satu akun hanya bisa memakai satu kode.'
                : 'Promo ini sudah pernah kamu pakai — satu akun hanya bisa memakai promo yang sama sekali.');
        }

        // Kuota dipotong permanen saat lunas (consume), jadi order yang sudah mengklaim kode ini tapi belum
        // lunas ikut dihitung — kalau tidak, checkout paralel saat kuota tinggal 1 bisa sama-sama lolos.
        if ($voucher->quota !== null && ! $voucher->isClaim()) {
            $reservedInFlight = $this->freshRead(Order::where('voucher_code', $voucher->code)
                ->whereIn('payment_status', ['UNPAID', 'PENDING_PAYMENT', 'PARTIALLY_PAID']), $lock)
                ->count();
            if ($voucher->quota - $reservedInFlight <= 0) {
                return $fail($voucher->isUniqueCode() ? 'Kode ini sudah dipakai.' : 'Kuota voucher sudah habis.');
            }
        }

        $minOrder = (float) ($voucher->min_order_amount ?? 0);
        if ($base < $minOrder) {
            return $fail('Minimal transaksi untuk voucher ini Rp '.number_format($minOrder, 0, ',', '.').' (dihitung dari '.strtolower(implode(' + ', array_map(fn ($c) => Voucher::PROMO_CATEGORIES[$c], $voucher->promoCategories()))).').');
        }

        if ($voucher->discount_type === Voucher::TYPE_PERCENT) {
            $calc = $base * ((float) $voucher->discount_value / 100);
            $discount = $voucher->max_discount_amount ? min($calc, (float) $voucher->max_discount_amount) : $calc;
        } else {
            $discount = (float) $voucher->discount_value;
        }

        return ['voucher' => $voucher, 'discount' => round(min($discount, $base, $orderAmount), 2), 'error' => null];
    }

    /**
     * T3 — akun ini sudah memakai promo ini (order lunas + yang masih menunggu bayar; order kedaluwarsa / batal / yang
     * promonya dikembalikan tidak dihitung → jatahnya kembali, T2). Kode unik (B): dihitung lintas semua kode kampanye.
     */
    public function promoUsedBy(Voucher $voucher, User $customer, bool $lock = false): bool
    {
        $codes = $voucher->isUniqueCode()
            ? Voucher::where('campaign_id', $voucher->campaign_id)->select('code')
            : [$voucher->code];

        return $this->freshRead(Order::whereIn('voucher_code', $codes)
            ->where('user_id', $customer->id)
            ->whereIn('payment_status', self::PROMO_HOLDING_STATUSES)
            // Order lunas yang dibatalkan & promonya sudah dikembalikan (restoreCreditForCancelledOrder) tidak dihitung.
            ->whereNull('voucher_restored_at'), $lock)
            ->exists();
    }

    /** Sisa saldo voucher CREDIT dikurangi potongan yang sedang dipesan order belum bayar. */
    public function availableBalance(Voucher $voucher, bool $lock = false): float
    {
        if (! $voucher->isCredit()) {
            return 0.0;
        }

        $reserved = (float) $this->freshRead(Order::where('voucher_code', $voucher->code)
            ->whereIn('payment_status', self::RESERVING_STATUSES), $lock)
            ->sum('discount_amount');

        return max(0.0, round((float) $voucher->balance - $reserved, 2));
    }

    /**
     * Checkout ($lock): baris voucher sudah dikunci, jadi checkout dengan kode yang sama berjalan bergiliran. Tapi di
     * MySQL REPEATABLE READ (default) SELECT biasa memakai snapshot awal transaksi — checkout kedua tidak melihat order
     * checkout pertama yang baru commit, sehingga saldo / kuota yang sama terpakai dua kali (audit 16 Okt 2026).
     * Locking read selalu membaca versi terbaru yang sudah commit. Tampilan saldo (tanpa $lock) tetap baca biasa.
     */
    private function freshRead(\Illuminate\Database\Eloquent\Builder $query, bool $lock): \Illuminate\Database\Eloquent\Builder
    {
        return $lock ? $query->lockForUpdate() : $query;
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

        // Promo klaim (C): kuotanya = jumlah klaim, sudah berkurang saat diklaim.
        if ($voucher->quota !== null && $voucher->quota > 0 && ! $voucher->isClaim()) {
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
        if (! $voucher || ! $order->payments()->where('status', 'SUCCESS')->exists()) {
            return; // belum lunas = saldo / kuota memang belum terpotong
        }

        // Promo (T2): kuota yang terpotong saat lunas dikembalikan. Jatah 1x per akun ikut kembali karena order dengan
        // voucher_restored_at tidak dihitung lagi di resolve().
        if (! $voucher->isCredit()) {
            // Promo klaim (C): klaimnya tetap ada (voucher kembali ke Voucher Saya), kuota klaim tidak berubah.
            if ($voucher->quota !== null && ! $voucher->isClaim()) {
                $voucher->quota = (int) $voucher->quota + 1;
            }
            $voucher->used_count = max(0, (int) $voucher->used_count - 1);
            $voucher->save();
            $order->forceFill(['voucher_restored_at' => now()])->save();

            ActivityLogger::record(
                module: 'FINANCE',
                event: 'voucher.restored',
                description: 'Kuota promo '.$voucher->code.' dikembalikan 1 (order '.$order->order_number.' dibatalkan)',
                subject: $order,
                meta: ['voucher' => $voucher->code, 'no_order' => $order->order_number],
            );

            return;
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
