<?php

namespace App\Services\Finance;

use App\Models\Finance\LedgerEntry;
use App\Models\Padel\PadelBooking;
use App\Models\Pos\Refund;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Query Buku Transaksi (Modul 17 Fase 2) — SATU sumber untuk tabel halaman, kartu ringkasan, dan export, supaya
 * angka yang dilihat = angka yang diunduh. Semua tanggal dalam hari kalender WIB; occurred_at disimpan di zona waktu
 * aplikasi, jadi batas hari WIB dikonversi ke zona aplikasi (pola sama dengan LogAktivitas).
 */
class LedgerReport
{
    public const TIMEZONE = 'Asia/Jakarta';

    public const PRESETS = [
        'hari_ini' => 'Hari ini',
        'kemarin' => 'Kemarin',
        '7_hari' => '7 hari terakhir',
        'bulan_ini' => 'Bulan ini',
        'bulan_lalu' => 'Bulan lalu',
        'kustom' => 'Pilih tanggal sendiri',
        'semua' => 'Semua tanggal',
    ];

    public const DEFAULT_PRESET = 'bulan_ini';

    /** Filter status transaksi. */
    public const STATUSES = [
        'PAYMENT' => 'Pembayaran',
        'OVERPAYMENT' => 'Kelebihan bayar',
        'REFUND' => 'Refund (uang keluar)',
        'REFUNDED' => 'Pembayaran yang pernah direfund',
    ];

    public const MONEY_COLUMNS = ['gross_amount', 'discount_amount', 'net_amount', 'service_amount', 'tax_amount', 'total_amount', 'benefit_amount'];

    /** @return array{0: ?string, 1: ?string} tanggal Y-m-d (WIB) awal & akhir, null = tanpa batas */
    public static function resolveRange(?string $preset, ?string $from = null, ?string $until = null): array
    {
        $today = Carbon::now(self::TIMEZONE)->startOfDay();

        return match ($preset ?: self::DEFAULT_PRESET) {
            'hari_ini' => [$today->toDateString(), $today->toDateString()],
            'kemarin' => [$today->copy()->subDay()->toDateString(), $today->copy()->subDay()->toDateString()],
            '7_hari' => [$today->copy()->subDays(6)->toDateString(), $today->toDateString()],
            'bulan_ini' => [$today->copy()->startOfMonth()->toDateString(), $today->toDateString()],
            'bulan_lalu' => [$today->copy()->subMonthNoOverflow()->startOfMonth()->toDateString(), $today->copy()->subMonthNoOverflow()->endOfMonth()->toDateString()],
            'semua' => [null, null],
            default => [self::dateOnly($from), self::dateOnly($until)],
        };
    }

    public static function applyDateRange(Builder $query, ?string $from, ?string $until): Builder
    {
        $tz = config('app.timezone');

        return $query
            ->when($from, fn (Builder $q, $date) => $q->where('occurred_at', '>=', Carbon::parse($date, self::TIMEZONE)->startOfDay()->setTimezone($tz)))
            ->when($until, fn (Builder $q, $date) => $q->where('occurred_at', '<', Carbon::parse($date, self::TIMEZONE)->addDay()->startOfDay()->setTimezone($tz)));
    }

    /** Transaksi yang memuat salah satu kategori ini (seluruh baris transaksinya ikut, bukan hanya baris kategorinya). */
    public static function applyCategories(Builder $query, array $categories): Builder
    {
        if ($categories === []) {
            return $query;
        }

        return $query->whereIn('payment_id', LedgerEntry::query()->select('payment_id')->whereNotNull('payment_id')->whereIn('category', $categories));
    }

    public static function applyStatuses(Builder $query, array $statuses): Builder
    {
        if ($statuses === []) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($statuses) {
            $types = array_values(array_intersect($statuses, [LedgerEntry::TYPE_PAYMENT, LedgerEntry::TYPE_OVERPAYMENT, LedgerEntry::TYPE_REFUND]));
            if ($types !== []) {
                $q->orWhereIn('entry_type', $types);
            }
            if (in_array('REFUNDED', $statuses, true)) {
                $q->orWhere(fn (Builder $w) => $w->where('entry_type', '!=', LedgerEntry::TYPE_REFUND)
                    ->whereIn('payment_id', LedgerEntry::query()->select('payment_id')->where('entry_type', LedgerEntry::TYPE_REFUND)->whereNotNull('payment_id')));
            }
        });
    }

    /** Pencarian: no. order / kode booking / nama customer / no. HP customer / bukti bayar / nama kasir. */
    public static function applySearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);
        if ($search === '') {
            return $query;
        }
        $like = '%'.$search.'%';

        return $query->where(function (Builder $q) use ($like) {
            foreach (['order_number', 'customer_name', 'payment_reference', 'cashier_name', 'meta'] as $column) {
                $q->orWhere($column, 'like', $like);
            }
            $q->orWhereIn('customer_id', \App\Models\User::query()->select('id')->where('phone', 'like', $like));
        });
    }

    /**
     * Terapkan seluruh filter (sudah tervalidasi lewat filterRules(), atau dari state filter tabel yang dinormalisasi).
     *
     * @param  array<string, mixed>  $filters
     */
    public static function applyFilters(Builder $query, array $filters): Builder
    {
        [$from, $until] = self::resolveRange($filters['periode'] ?? null, $filters['dari'] ?? null, $filters['sampai'] ?? null);
        self::applyDateRange($query, $from, $until);

        $query
            ->when($filters['source'] ?? null, fn (Builder $q, $values) => $q->whereIn('source', (array) $values))
            ->when($filters['method'] ?? null, fn (Builder $q, $values) => $q->whereIn('payment_method', (array) $values))
            ->when($filters['cashier_id'] ?? null, fn (Builder $q, $value) => $q->where('cashier_id', $value))
            ->when($filters['pos_shift_id'] ?? null, fn (Builder $q, $value) => $q->where('pos_shift_id', $value));

        self::applyCategories($query, (array) ($filters['category'] ?? []));
        self::applyStatuses($query, (array) ($filters['status'] ?? []));

        return self::applySearch($query, $filters['search'] ?? null);
    }

    /**
     * Satu baris per pembayaran / refund (baris kategori digabung). Kolom snapshot sama untuk semua baris satu
     * transaksi, jadi MAX() aman (dan lolos ONLY_FULL_GROUP_BY MySQL).
     */
    public static function grouped(Builder $query): Builder
    {
        $sums = collect(self::MONEY_COLUMNS)->map(fn ($c) => "SUM({$c}) as {$c}")->implode(', ');
        $snapshot = collect(['occurred_at', 'order_id', 'order_number', 'source', 'customer_id', 'customer_name', 'cashier_id', 'cashier_name',
            'pos_shift_id', 'payment_gateway', 'payment_method', 'payment_method_label', 'payment_reference'])
            ->map(fn ($c) => "MAX({$c}) as {$c}")->implode(', ');

        return $query
            ->selectRaw("MIN(id) as id, payment_id, refund_id, entry_type, {$snapshot}, {$sums}, GROUP_CONCAT(category) as categories")
            ->groupBy('payment_id', 'refund_id', 'entry_type');
    }

    public static function orderNewestFirst(Builder $query, string $direction = 'desc'): Builder
    {
        $direction = $direction === 'asc' ? 'asc' : 'desc';

        return $query->orderByRaw("MAX(occurred_at) {$direction}")->orderByRaw("MIN(id) {$direction}");
    }

    /**
     * Angka kartu ringkasan — satu query agregat (bukan perulangan PHP).
     *
     * @return array<string, float|int>
     */
    public static function summary(Builder $filteredBase, ?string $from = null, ?string $until = null): array
    {
        // Kartu utama dihitung SETELAH refund (baris refund bernilai negatif ikut dijumlah), supaya
        // Penjualan Bersih + Biaya Layanan + Pajak = Total Uang Masuk (Bersih). Dulu penjualan bersih dihitung sebelum
        // refund sementara totalnya setelah refund — angkanya tidak bisa dicocokkan dengan menjumlah tabel.
        $in = "entry_type <> 'REFUND'";
        $row = (clone $filteredBase)->toBase()->selectRaw(
            "COALESCE(SUM(net_amount), 0) as net,
             COALESCE(SUM(service_amount), 0) as service,
             COALESCE(SUM(tax_amount), 0) as tax,
             COALESCE(SUM(gross_amount), 0) as gross,
             COALESCE(SUM(discount_amount), 0) as discount,
             COALESCE(SUM(CASE WHEN {$in} THEN net_amount ELSE 0 END), 0) as net_before_refund,
             COALESCE(SUM(CASE WHEN {$in} THEN total_amount ELSE 0 END), 0) as money_in,
             COALESCE(SUM(CASE WHEN entry_type = 'REFUND' THEN total_amount ELSE 0 END), 0) as refunds,
             COALESCE(SUM(CASE WHEN entry_type = 'OVERPAYMENT' THEN total_amount ELSE 0 END), 0) as overpayments,
             COALESCE(SUM(total_amount), 0) as money_net,
             COALESCE(SUM(benefit_amount), 0) as benefit,
             COUNT(DISTINCT CASE WHEN {$in} THEN payment_id END) as payments_count,
             COUNT(DISTINCT refund_id) as refunds_count"
        )->first();

        $pending = Refund::query()->where('status', 'PENDING');
        $forfeited = PadelBooking::query()->where('reschedule_forfeited_amount', '>', 0);
        if ($from || $until) {
            $tz = config('app.timezone');
            $forfeited
                ->when($from, fn ($q) => $q->where('updated_at', '>=', Carbon::parse($from, self::TIMEZONE)->startOfDay()->setTimezone($tz)))
                ->when($until, fn ($q) => $q->where('updated_at', '<', Carbon::parse($until, self::TIMEZONE)->addDay()->startOfDay()->setTimezone($tz)));
        }

        return [
            'net' => (float) $row->net,
            'service' => (float) $row->service,
            'tax' => (float) $row->tax,
            'gross' => (float) $row->gross,
            'discount' => (float) $row->discount,
            'net_before_refund' => (float) $row->net_before_refund,
            'money_in' => (float) $row->money_in,
            'refunds' => (float) $row->refunds,
            'overpayments' => (float) $row->overpayments,
            'money_net' => (float) $row->money_net,
            'benefit' => (float) $row->benefit,
            'payments_count' => (int) $row->payments_count,
            'refunds_count' => (int) $row->refunds_count,
            'forfeited' => (float) $forfeited->sum('reschedule_forfeited_amount'),
            'pending_refund_count' => (clone $pending)->count(),
            'pending_refund_amount' => (float) (clone $pending)->sum('refund_amount'),
        ];
    }

    /** @var array<string, array{processed: float, pending: float}> */
    private static array $refundMemo = [];

    /** Status satu baris transaksi (hasil grouped()). */
    public static function statusLabel(LedgerEntry $row): string
    {
        if ($row->entry_type === LedgerEntry::TYPE_REFUND) {
            return 'Refund';
        }

        $refunds = self::refundsFor($row->payment_id);
        $total = (float) $row->total_amount;
        $refunded = match (true) {
            $refunds['processed'] <= 0 => '',
            $refunds['processed'] >= $total - 1 => 'Direfund',
            default => 'Direfund sebagian',
        };

        if ($row->entry_type === LedgerEntry::TYPE_OVERPAYMENT) {
            return 'Kelebihan bayar'.($refunded !== '' ? ' · '.strtolower($refunded) : ($refunds['pending'] > 0 ? ' · refund menunggu' : ''));
        }

        return $refunded !== '' ? $refunded : ($refunds['pending'] > 0 ? 'Lunas · refund menunggu' : 'Lunas');
    }

    public static function statusColor(string $label): string
    {
        return match (true) {
            str_starts_with($label, 'Refund') => 'danger',
            str_starts_with($label, 'Kelebihan') => 'warning',
            str_starts_with($label, 'Direfund') => 'gray',
            str_contains($label, 'menunggu') => 'warning',
            default => 'success',
        };
    }

    /** @return array{processed: float, pending: float} */
    public static function refundsFor(?string $paymentId): array
    {
        if (! $paymentId) {
            return ['processed' => 0.0, 'pending' => 0.0];
        }

        return self::$refundMemo[$paymentId] ??= [
            'processed' => (float) Refund::where('payment_id', $paymentId)->where('status', 'PROCESSED')->sum('refund_amount'),
            'pending' => (float) Refund::where('payment_id', $paymentId)->where('status', 'PENDING')->sum('refund_amount'),
        ];
    }

    public static function flushMemo(): void
    {
        self::$refundMemo = [];
    }

    /**
     * Kasir yang melunasi. Pembayaran online = "Online"; pembayaran di loket tanpa nama kasir (data lama sebelum
     * nama kasir dicatat) = "-", bukan "Online".
     */
    public static function cashierLabel(LedgerEntry $row): string
    {
        if ($row->cashier_name) {
            return $row->cashier_name;
        }

        $atCounter = $row->pos_shift_id !== null || strtoupper((string) $row->payment_gateway) === 'CASHIER_POS'
            || in_array($row->source, ['POS_WALKIN_PADEL', 'RESCHEDULE_DELTA_POS', 'POS_MEMBERSHIP', 'POS_FNB'], true);

        return $atCounter ? '-' : 'Online';
    }

    public static function categoriesLabel(?string $csv): string
    {
        return collect(explode(',', (string) $csv))->filter()->unique()
            ->map(fn ($c) => LedgerEntry::categoryLabel($c))->implode(' + ');
    }

    /** Opsi filter metode bayar (kode → label yang tercatat), di-cache 10 menit. */
    public static function methodOptions(): array
    {
        $query = fn () => LedgerEntry::query()->toBase()
            ->selectRaw('payment_method, MAX(payment_method_label) as label')
            ->whereNotNull('payment_method')->groupBy('payment_method')->orderBy('payment_method')->limit(100)->get()
            ->mapWithKeys(fn ($r) => [$r->payment_method => $r->label ? "{$r->label} ({$r->payment_method})" : $r->payment_method])->all();

        try {
            return Cache::remember('ledger:method_options', 600, $query);
        } catch (Throwable) {
            return $query();
        }
    }

    public static function cashierOptions(): array
    {
        $query = fn () => LedgerEntry::query()->toBase()
            ->selectRaw('cashier_id, MAX(cashier_name) as name')
            ->whereNotNull('cashier_id')->groupBy('cashier_id')->orderBy('name')->limit(200)->get()
            ->mapWithKeys(fn ($r) => [$r->cashier_id => $r->name ?: $r->cashier_id])->all();

        try {
            return Cache::remember('ledger:cashier_options', 600, $query);
        } catch (Throwable) {
            return $query();
        }
    }

    /** Whitelist parameter route export (nilai enum dicek terhadap daftar yang dikenal). */
    public static function filterRules(): array
    {
        return [
            'periode' => ['nullable', Rule::in(array_keys(self::PRESETS))],
            'dari' => ['nullable', 'date_format:Y-m-d'],
            'sampai' => ['nullable', 'date_format:Y-m-d'],
            'source' => ['nullable', 'array', 'max:'.count(LedgerEntry::SOURCES)],
            'source.*' => ['string', Rule::in(array_keys(LedgerEntry::SOURCES))],
            'category' => ['nullable', 'array', 'max:'.count(LedgerEntry::CATEGORIES)],
            'category.*' => ['string', Rule::in(array_keys(LedgerEntry::CATEGORIES))],
            'method' => ['nullable', 'array', 'max:50'],
            'method.*' => ['string', 'max:40', 'regex:/^[A-Za-z0-9_\-]+$/'],
            'cashier_id' => ['nullable', 'string', 'ulid'],
            'pos_shift_id' => ['nullable', 'string', 'ulid'],
            'status' => ['nullable', 'array', 'max:'.count(self::STATUSES)],
            'status.*' => ['string', Rule::in(array_keys(self::STATUSES))],
            'search' => ['nullable', 'string', 'max:200'],
            'format' => ['nullable', Rule::in(['xlsx', 'pdf'])],
        ];
    }

    /**
     * Cegah CSV/Formula injection: sel yang diawali = + - @ (atau tab/CR) dieksekusi sebagai rumus oleh Excel.
     * Nama customer & catatan refund berasal dari input user.
     */
    public static function cellSafe(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$value : $value;
    }

    private static function dateOnly(?string $value): ?string
    {
        return filled($value) ? Carbon::parse($value)->toDateString() : null;
    }
}
