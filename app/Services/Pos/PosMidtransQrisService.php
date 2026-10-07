<?php

namespace App\Services\Pos;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelBookingEquipment;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\Pos\PosCashierShift;
use App\Models\User;
use App\Services\Membership\MembershipOnlinePaymentService;
use App\Services\Padel\BookingTimeService;
use App\Services\Padel\PadelBookingService;
use App\Services\Payment\MidtransReconciliationService;
use App\Services\Payment\MidtransService;
use App\Services\Payment\OnlinePaymentMethodService;
use App\Services\Payment\PaymentOrchestratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * "Bayar Otomatis" di layar kasir (POS Walk-In padel, Jual Membership, F&B): popup pembayaran yang SAMA dengan checkout
 * online (QRIS / Virtual Account, sesuai metode yang dicentang "Tampil di Kasir" di menu Metode Pembayaran Online).
 * QRIS manual (input RRN) tetap ada sebagai cadangan.
 *
 * Alur: checkout kasir membuat order UNPAID + tagihan PENDING (gateway MIDTRANS) yang SUDAH terikat ke shift kasir
 * (pos_shift_id) → popup pembayaran tampil di layar → webhook / cek status melunasi tagihan itu lewat
 * PaymentOrchestratorService seperti pembayaran online. pos_shift_id tidak pernah ditimpa saat pelunasan, jadi uangnya
 * tetap masuk rekap tutup shift kasir yang menerima, dan Buku Transaksi mencatatnya sebagai transaksi kasir.
 *
 * Total yang ditagih = total kasir (biaya layanan / pajak mengikuti pengaturan channel POS). Batas waktu = batas bayar
 * dari pengaturan waktu booking.
 */
class PosMidtransQrisService
{
    /** Penanda internal pembayaran QRIS otomatis dari kasir di qris_details.provider (rekap shift & label struk). */
    public const PROVIDER = 'MIDTRANS_QRIS';

    public const PAID = 'PAID';

    public const PENDING = 'PENDING';

    public const EXPIRED = 'EXPIRED';

    public const CANCELLED = 'CANCELLED';

    public function __construct(
        protected MidtransService $midtrans,
        protected BookingTimeService $time,
    ) {}

    /**
     * Metode "Bayar Otomatis" yang dipakai: pilihan kasir kalau tersedia, selain itu metode pertama yang dicentang
     * "Tampil di Kasir" (layar kasir menandai metode yang sama). Null = tidak ada metode otomatis untuk kasir.
     */
    public static function resolveMethod(?string $requested, ?float $amount): ?string
    {
        $codes = array_column(app(OnlinePaymentMethodService::class)->forPos($amount), 'code');
        $requested = strtoupper((string) $requested);

        return in_array($requested, $codes, true) ? $requested : ($codes[0] ?? null);
    }

    /** Nama metode di struk / riwayat / rekap shift untuk pembayaran "Bayar Otomatis" dari kasir; null = bukan. */
    public static function labelFor(?array $payload): ?string
    {
        if (! is_array($payload) || ! isset($payload['pos_qris'])) {
            return null;
        }
        $code = strtoupper((string) ($payload['pos_qris']['method'] ?? 'QRIS'));

        return $code === 'QRIS'
            ? 'QRIS Otomatis (Kasir)'
            : (app(OnlinePaymentMethodService::class)->label($code) ?? $code).' (Kasir)';
    }

    /**
     * Buat tagihan untuk order kasir yang baru dibuat (dipanggil di DALAM transaksi checkout — gagal membuka sesi
     * pembayaran = seluruh checkout di-rollback). Mengembalikan data untuk popup pembayaran di layar kasir.
     *
     * @return array{payment_id: string, order_id: string, order_number: string, amount: float, method: string, method_label: string, snap_token: string, redirect_url: ?string, expires_at: string, is_mock: bool, sandbox: bool}
     */
    public function open(Order $order, string $counter, User $cashier, array $payloadLog = [], string $method = 'QRIS'): array
    {
        $shift = PosCashierShift::getActiveShift($counter);
        if (! $shift) {
            throw new HttpException(422, "Tidak ada shift kasir yang aktif untuk loket [{$counter}]. Silakan buka shift terlebih dahulu.");
        }

        $amount = (float) $order->grand_total;
        if ($amount <= 0) {
            throw new HttpException(422, 'Tagihan Rp 0 tidak perlu dibayar otomatis.');
        }

        $methods = app(OnlinePaymentMethodService::class);
        $method = $methods->assertPosSelectable(self::resolveMethod($method, $amount) ?? $method, $amount);

        $minutes = $this->time->paymentWindowMinutes();
        $expiresAt = now()->addMinutes($minutes);
        $sessionId = $order->order_number.'_'.time().strtoupper(Str::random(4));
        $gross = (int) round($amount);

        $session = $this->midtrans->createSnapTransaction([
            'order_id' => $sessionId,
            'gross_amount' => $gross,
            'item_details' => [['id' => $order->order_number, 'price' => $gross, 'quantity' => 1, 'name' => mb_substr('Club 61 '.$order->order_number, 0, 50)]],
            'customer_details' => array_filter([
                'first_name' => $order->user?->name ?? $order->customer_name,
                'email' => $order->user?->email,
                'phone' => $order->user?->phone,
            ]),
            'payment_method' => $method,
            'expiry_minutes' => $minutes,
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => $sessionId,
            'payment_method' => $method,
            'amount' => $amount,
            'status' => 'PENDING',
            'pos_shift_id' => $shift->id,
            'payload_log' => array_merge($payloadLog, [
                'cashier_id' => $cashier->id,
                'cashier_name' => $cashier->name,
                'counter' => $counter,
                // Token sesi disimpan supaya popup bisa dibuka lagi setelah halaman kasir di-refresh / tertutup.
                'pos_qris' => [
                    'method' => $method, 'expires_at' => $expiresAt->toIso8601String(), 'is_mock' => $session['is_mock'],
                    'snap_token' => $session['snap_token'], 'redirect_url' => $session['redirect_url'] ?? null,
                ],
                'midtrans_order_id' => $sessionId,
                'midtrans_order_ids' => [$sessionId],
            ], $method === 'QRIS' ? ['qris_details' => ['provider' => self::PROVIDER, 'rrn' => null, 'sender_name' => null]] : []),
        ]);

        $order->update(['pos_shift_id' => $shift->id, 'cashier_id' => $order->cashier_id ?? $cashier->id]);

        return self::present($payment->setRelation('order', $order));
    }

    /** Data popup kasir dari tagihan tersimpan (dipakai saat dibuat & saat dilanjutkan setelah halaman di-refresh). */
    public static function present(Payment $payment): array
    {
        $pos = $payment->payload_log['pos_qris'] ?? [];
        $method = strtoupper((string) ($pos['method'] ?? 'QRIS'));
        $isMock = (bool) ($pos['is_mock'] ?? false);

        return [
            'payment_id' => $payment->id,
            'order_id' => $payment->order_id,
            'order_number' => $payment->order?->order_number,
            'amount' => (float) $payment->amount,
            'method' => $method,
            'method_label' => app(OnlinePaymentMethodService::class)->label($method) ?? $method,
            'snap_token' => $pos['snap_token'] ?? null,
            'redirect_url' => $pos['redirect_url'] ?? null,
            'expires_at' => $pos['expires_at'] ?? now()->toIso8601String(),
            'is_mock' => $isMock,
            // Akun uji coba: QR / VA hanya bisa dibayar lewat simulator, bukan aplikasi bank / e-wallet asli.
            'sandbox' => ! $isMock && ! config('services.midtrans.is_production'),
        ];
    }

    /**
     * Tagihan Bayar Otomatis milik kasir ini di loket ini yang masih menunggu (popup dilanjutkan saat halaman kasir
     * dibuka lagi). $source membedakan layar yang berbagi loket (POS Walk-In vs Jual Membership di PADEL_FRONTDESK).
     */
    public function resumeFor(string $counter, string $source, ?string $cashierId): ?array
    {
        if (! $cashierId) {
            return null;
        }

        $payment = Payment::with('order')
            ->where('status', 'PENDING')
            ->where('payment_gateway', 'MIDTRANS')
            ->whereNotNull('pos_shift_id')
            ->latest()
            ->limit(20)
            ->get()
            ->first(fn (Payment $p) => isset($p->payload_log['pos_qris'])
                && ($p->payload_log['counter'] ?? null) === $counter
                && ($p->payload_log['source'] ?? null) === $source
                && ($p->payload_log['cashier_id'] ?? null) === $cashierId);

        return $payment ? self::present($payment) : null;
    }

    /**
     * Status tagihan untuk polling layar kasir: PAID / PENDING / EXPIRED / CANCELLED. Lunas biasanya datang dari webhook;
     * kalau webhook telat, Midtrans ditanya langsung (dibatasi tiap beberapa detik). Lewat batas waktu → dibatalkan.
     */
    public function status(string $paymentId): string
    {
        $payment = Payment::with('order')->find($paymentId);
        if (! $payment || ! $payment->order) {
            return self::CANCELLED;
        }
        if ($payment->status === 'SUCCESS') {
            return self::PAID;
        }
        if ($payment->status !== 'PENDING') {
            return self::CANCELLED;
        }

        $result = app(MidtransReconciliationService::class)->reconcileOrder($payment->order, cacheSeconds: 5);
        $current = $payment->fresh()->status;
        if ($result === MidtransReconciliationService::PAID || $current === 'SUCCESS') {
            return self::PAID;
        }
        // Midtrans sudah menyatakan QR ini kedaluwarsa / batal (tagihan ditutup pengecekan status) → bersihkan order.
        if ($current !== 'PENDING') {
            return $this->cancel($paymentId, 'EXPIRED') === self::PAID ? self::PAID : self::EXPIRED;
        }

        $expiresAt = $payment->payload_log['pos_qris']['expires_at'] ?? null;
        if ($expiresAt && now()->greaterThan(\Illuminate\Support\Carbon::parse($expiresAt)->addSeconds(30))) {
            return $this->cancel($paymentId, 'EXPIRED') === self::PAID ? self::PAID : self::EXPIRED;
        }

        return self::PENDING;
    }

    /**
     * Batalkan QR (kasir menekan Batalkan, atau batas waktu lewat): QR di Midtrans dibatalkan, order batal, slot
     * lapangan dilepas, kuota member / stok bola dikembalikan. Kalau ternyata customer sudah bayar → tetap PAID.
     */
    public function cancel(string $paymentId, string $reason = 'CASHIER_CANCEL'): string
    {
        $payment = Payment::with('order')->find($paymentId);
        if (! $payment || ! $payment->order) {
            return self::CANCELLED;
        }
        if ($payment->status === 'SUCCESS') {
            return self::PAID;
        }

        // Jangan sampai uang yang sudah masuk ikut dibatalkan: cek Midtrans dulu.
        if (app(MidtransReconciliationService::class)->reconcileOrder($payment->order) === MidtransReconciliationService::PAID) {
            return self::PAID;
        }

        $this->midtrans->cancelTransaction($payment->transaction_id);

        return DB::transaction(function () use ($payment, $reason) {
            $order = Order::whereKey($payment->order_id)->lockForUpdate()->firstOrFail();
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->status === 'SUCCESS') {
                return self::PAID;
            }
            if ($payment->status === 'PENDING') {
                $log = is_array($payment->payload_log) ? $payment->payload_log : [];
                $log['closed_by'] = 'POS_QRIS_'.$reason;
                $payment->update(['status' => 'FAILED', 'payload_log' => $log]);
            }

            if (Payment::where('order_id', $order->id)->where('status', 'SUCCESS')->exists()) {
                return self::CANCELLED;
            }

            $order->update(['payment_status' => 'CANCELLED']);
            MembershipOnlinePaymentService::cancelForCancelledOrder($order);

            $bookingService = app(PadelBookingService::class);
            PadelBooking::where('order_id', $order->id)
                ->whereIn('status', ['PENDING_PAYMENT', 'LOCKED', 'PENDING'])
                ->lockForUpdate()
                ->get()
                ->each(function (PadelBooking $booking) use ($bookingService) {
                    $bookingService->reverseBookingBenefits($booking);
                    $booking->update(['status' => 'CANCELLED']);
                });

            // Bola (habis pakai) dipotong saat checkout → kembalikan stoknya.
            PadelBookingEquipment::with('equipment')
                ->where('order_id', $order->id)
                ->whereNotNull('stock_deducted_at')
                ->get()
                ->each(function (PadelBookingEquipment $row) {
                    $row->equipment?->increment('stock_quantity', (int) $row->quantity);
                    $row->update(['stock_deducted_at' => null]);
                });

            return self::CANCELLED;
        });
    }

    /**
     * Pembersih berkala (scheduler, tiap 5 menit): Bayar Otomatis yang ditinggal — layar kasir ditutup / di-refresh
     * kasir lain, lalu QR kedaluwarsa — dibatalkan BESERTA ordernya. Dulu hanya tagihannya yang ditutup rekonsiliasi,
     * order F&B tetap UNPAID selamanya dan booking / kartu member menunggu tanpa kepastian. Uang yang ternyata sudah
     * masuk tetap dilunasi (cancel() mengecek status pembayaran dulu).
     *
     * @return int jumlah tagihan yang dibereskan
     */
    public function sweepAbandoned(): int
    {
        $count = 0;

        Payment::with('order')
            ->where('payment_gateway', 'MIDTRANS')
            ->whereNotNull('pos_shift_id')
            ->whereIn('status', ['PENDING', 'FAILED'])
            ->whereHas('order', fn ($q) => $q->where('payment_status', 'UNPAID'))
            ->where('created_at', '>=', now()->subDays(7))
            ->get()
            ->filter(fn (Payment $p) => isset($p->payload_log['pos_qris']))
            ->filter(function (Payment $p) {
                if ($p->status === 'FAILED') {
                    return true;
                }
                $expiresAt = $p->payload_log['pos_qris']['expires_at'] ?? null;

                return $expiresAt && now()->greaterThan(\Illuminate\Support\Carbon::parse($expiresAt)->addMinutes(2));
            })
            ->each(function (Payment $p) use (&$count) {
                try {
                    $this->cancel($p->id, 'EXPIRED');
                    $count++;
                } catch (\Throwable $e) {
                    report($e);
                }
            });

        return $count;
    }

    /**
     * Mesin developer tanpa server key (QR mock): pura-pura customer membayar, lewat jalur pelunasan yang sama dengan
     * webhook. Tidak pernah tersedia di server.
     */
    public function simulatePaid(string $paymentId): void
    {
        $payment = Payment::with('order')->findOrFail($paymentId);
        abort_unless(($payment->payload_log['pos_qris']['is_mock'] ?? false) && ! app()->environment('production'), 403);

        app(PaymentOrchestratorService::class)->markOrderAsPaid($payment->order, [
            'payment_gateway' => 'MIDTRANS',
            'transaction_id' => $payment->transaction_id,
            'payment_method' => 'QRIS',
            'amount' => (float) $payment->amount,
            'payload_log' => ['payment_type' => 'qris', 'transaction_status' => 'settlement', 'simulated' => true],
        ]);
    }
}
