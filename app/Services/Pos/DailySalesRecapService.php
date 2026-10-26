<?php

namespace App\Services\Pos;

use App\Models\Audit\ActivityLog;
use App\Models\Pos\Payment;
use App\Models\Pos\PosCashierShift;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Padel\PadelBookingService;
use App\Support\ReceiptPaper;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Rekap Penjualan Harian (X-Report) per loket — hanya MEMBACA data, tidak menutup / mengubah apa pun.
 *
 * Periode = shift loket yang DIBUKA pada tanggal itu: dari shift pertama dibuka sampai sekarang (masih ada shift buka)
 * atau sampai shift terakhir ditutup. Angka = pembayaran SUCCESS shift-shift itu, dikelompokkan dengan
 * PosCashierShift::categorize (kategori yang sama dengan form Tutup Shift & Z-Report), jadi baris rekap bisa langsung
 * dicocokkan dengan struk settlement EDC / mutasi QRIS.
 *
 * Rekap memperlihatkan angka sistem yang di form Tutup Shift sengaja disembunyikan (blind) — karena itu dibatasi izin
 * print_sales_recap dan setiap kali dibuka tercatat di Log Aktivitas (jumlahnya ikut tampil di Z-Report shift itu).
 */
class DailySalesRecapService
{
    public const PERMISSION = 'print_sales_recap';

    public const LOG_EVENT = 'shift.sales_recap_viewed';

    public const COUNTER_LABELS = [
        'PADEL_FRONTDESK' => 'Frontdesk Padel',
        'FNB_COUNTER' => 'Kasir F&B',
    ];

    /** Pembayaran yang bukan uang dari payment gateway (kasir, kuota, transfer manual) — tidak masuk bagian "online". */
    private const NON_ONLINE_GATEWAYS = ['CASHIER_POS', 'MEMBERSHIP_QUOTA', 'TRANSFER_MANUAL'];

    private const TZ = 'Asia/Jakarta';

    private const EDC_SHORT = ['EDC_BCA' => 'BCA', 'EDC_MANDIRI' => 'Mandiri', 'EDC_LAINNYA' => 'Lainnya'];

    private const QRIS_SHORT = [
        'BCA_QRIS' => 'BCA',
        'MANDIRI_QRIS' => 'Mandiri',
        'GOPAY' => 'GoPay',
        'GOPAY_QRIS' => 'GoPay',
        'MIDTRANS_QRIS' => 'Otomatis',
        'DOKU_QRIS' => 'Otomatis DOKU',
        'OVO' => 'OVO',
        'SHOPEEPAY' => 'ShopeePay',
        'DANA' => 'DANA',
        'LIVIN' => 'Livin Mandiri',
        'LAINNYA' => 'Lainnya',
    ];

    private const OTHER_SHORT = [
        'CASH' => 'Tunai',
        'MEMBERSHIP_QUOTA' => 'Kuota Membership',
        'SPONSOR_VOUCHER' => 'Voucher Corporate',
        'MEMBERSHIP_AND_SPONSOR_VOUCHER' => 'Kuota + Voucher Corporate',
        'PROMO_VOUCHER' => 'Kode Promo',
        'VOUCHER' => 'Voucher',
        'TRANSFER_BANK' => 'Transfer Bank',
    ];

    /**
     * @return array<string, mixed>|null null = belum ada shift loket ini yang dibuka pada tanggal itu
     */
    public function build(string $counter, string $date, User $printedBy): ?array
    {
        $dayStart = Carbon::parse($date, self::TZ)->startOfDay();

        $shifts = PosCashierShift::query()
            ->where('counter', $counter)
            ->whereBetween('opened_at', [
                $dayStart->copy()->setTimezone(config('app.timezone')),
                $dayStart->copy()->endOfDay()->setTimezone(config('app.timezone')),
            ])
            ->orderBy('opened_at')
            ->get();

        if ($shifts->isEmpty()) {
            return null;
        }

        $openCount = $shifts->where('status', 'OPEN')->count();
        $from = $shifts->first()->opened_at;
        $until = $openCount > 0 ? now() : $shifts->max('closed_at');
        $until = $until ? Carbon::parse($until) : now();

        $payments = Payment::query()
            ->whereIn('pos_shift_id', $shifts->pluck('id'))
            ->where('status', 'SUCCESS')
            ->get();

        $methods = [];
        foreach ($payments as $payment) {
            $category = PosCashierShift::categorize($payment);
            $methods[$category['key']] ??= ['label' => $this->counterLabel($category['key'], $payment), 'count' => 0, 'amount' => 0.0];
            $methods[$category['key']]['count']++;
            $methods[$category['key']]['amount'] += (float) $payment->amount;
        }
        ksort($methods);

        $now = now()->setTimezone(self::TZ);

        return [
            'counter' => $counter,
            'counter_label' => self::COUNTER_LABELS[$counter] ?? $counter,
            'date' => $dayStart->toDateString(),
            'date_label' => $dayStart->locale('id')->translatedFormat('l').', '.$dayStart->format('d/m/Y'),
            'opened_at' => Carbon::parse($from)->setTimezone(self::TZ)->format('H:i'),
            'until' => $until->copy()->setTimezone(self::TZ)->format('H:i'),
            'still_open' => $openCount > 0,
            'shift_count' => $shifts->count(),
            'open_shift_count' => $openCount,
            'shift_ids' => $shifts->pluck('id')->all(),
            'printed_at' => $now->format('d/m/Y H:i'),
            'printed_by' => self::fit((string) $printedBy->name, 20),
            'transactions' => $payments->count(),
            'total' => (float) $payments->sum('amount'),
            'methods' => array_values($methods),
            // Bayar online customer hanya untuk booking & membership — loket padel saja.
            'online' => $counter === 'PADEL_FRONTDESK' ? $this->online($from, $until) : null,
        ];
    }

    /**
     * Rekap dibuka → Log Aktivitas, menempel ke shift loket yang sedang buka (kalau ada) supaya Z-Report shift itu bisa
     * menyebut berapa kali rekap dilihat sebelum tutup shift.
     */
    public function recordView(array $recap, User $by): void
    {
        $openShift = PosCashierShift::getActiveShift($recap['counter']);

        ActivityLogger::record(
            module: 'POS',
            event: self::LOG_EVENT,
            description: 'Membuka Rekap Penjualan Harian (X-Report) '.$recap['counter_label'].' '.$recap['date_label'],
            subject: $openShift,
            meta: [
                'loket' => $recap['counter_label'],
                'tanggal' => $recap['date'],
                'total' => ActivityLogger::rupiah($recap['total']),
                'transaksi' => $recap['transactions'],
            ],
            causer: $by,
        );
    }

    /** Berapa kali rekap harian dibuka selama shift ini berjalan (baris info di Z-Report). */
    public static function viewsDuringShift(PosCashierShift $shift): int
    {
        return ActivityLog::query()
            ->where('event', self::LOG_EVENT)
            ->where('subject_type', class_basename($shift))
            ->where('subject_id', $shift->getKey())
            ->count();
    }

    /** Pembayaran online customer (website / aplikasi) yang lunas dalam periode rekap — info, tidak lewat EDC. */
    private function online(CarbonInterface $from, CarbonInterface $until): array
    {
        $payments = Payment::query()
            ->whereNull('pos_shift_id')
            ->where('status', 'SUCCESS')
            ->whereNotIn('payment_gateway', self::NON_ONLINE_GATEWAYS)
            ->where('amount', '>', 0)
            ->whereBetween('paid_at', [$from, $until])
            ->get();

        $methods = [];
        $labeler = app(PadelBookingService::class);
        foreach ($payments as $payment) {
            $label = self::fit($labeler->formatPaymentMethodLabel($payment->payment_method, is_array($payment->payload_log) ? $payment->payload_log : null));
            $methods[$label] ??= ['label' => $label, 'count' => 0, 'amount' => 0.0];
            $methods[$label]['count']++;
            $methods[$label]['amount'] += (float) $payment->amount;
        }
        ksort($methods);

        return [
            'transactions' => $payments->count(),
            'total' => (float) $payments->sum('amount'),
            'methods' => array_values($methods),
        ];
    }

    /**
     * Nama metode pendek supaya satu baris struk (lebar kertas PC = ReceiptPaper::PC_COLUMNS karakter) tidak pernah
     * turun ke baris berikutnya. Pengelompokannya tetap kunci categorize(), hanya labelnya yang dipendekkan.
     */
    private function counterLabel(string $key, Payment $payment): string
    {
        if (preg_match('/^(EDC_[A-Z]+)_(DEBIT|CREDIT)$/', $key, $m)) {
            return self::fit(($m[2] === 'CREDIT' ? 'Kredit' : 'Debit').' EDC '.(self::EDC_SHORT[$m[1]] ?? Str::title(str_replace('_', ' ', $m[1]))));
        }

        if (str_starts_with($key, 'QRIS_')) {
            $provider = substr($key, 5);

            return self::fit('QRIS '.(self::QRIS_SHORT[$provider] ?? Str::title(str_replace('_', ' ', $provider))));
        }

        if (str_starts_with($key, 'OTHER_')) {
            $method = strtoupper((string) $payment->payment_method);

            return self::fit(self::OTHER_SHORT[$method] ?? Str::title(str_replace('_', ' ', strtolower($method))));
        }

        // AUTO_* (Bayar Otomatis non-QRIS, mis. VA dari layar kasir): label categorize() sudah pendek.
        return self::fit(PosCashierShift::categorize($payment)['label']);
    }

    /** Potong ke lebar struk (tanpa memotong di tengah kata kalau bisa). */
    public static function fit(string $text, int $width = ReceiptPaper::PC_COLUMNS): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text) <= $width) {
            return $text;
        }

        $cut = mb_substr($text, 0, $width);
        $space = mb_strrpos($cut, ' ');

        return rtrim($space !== false && $space >= $width - 8 ? mb_substr($cut, 0, $space) : $cut);
    }
}
