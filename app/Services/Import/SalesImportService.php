<?php

namespace App\Services\Import;

use App\Models\Finance\LedgerEntry;
use App\Models\Finance\SalesImportBatch;
use App\Models\Fnb\FnbMenu;
use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelCourt;
use App\Models\Pos\Order;
use App\Models\Pos\Payment;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use App\Services\Finance\LedgerWriter;
use App\Services\Padel\PadelBookingService;
use Carbon\Carbon;
use DateInterval;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OpenSpout\Reader\XLSX\Reader;

/**
 * Import penjualan soft opening dari Excel (template SalesImportTemplate): booking padel walk-in (jadwal, akun
 * customer, pembayaran) dan penjualan F&B. Semua pembayaran QRIS.
 *
 * - analyze(): baca & cek SEMUA baris (tanpa menulis apa pun) → pratinjau + daftar error per baris.
 * - commit(): baca ulang file yang sama, cek ulang di dalam satu transaksi database, lalu tulis semuanya — kalau
 *   masih ada satu error, tidak ada yang ditulis.
 *
 * Data import TIDAK lewat shift kasir / Midtrans / slip dapur: order langsung lunas pada Tanggal Bayar-nya dan
 * dicatat ke Buku Transaksi (LedgerWriter) dengan tanggal itu. Penanda: order_number "IMP-PAD-…" / "IMP-FNB-…" dan
 * payload_log.source = IMPORT_EXCEL. Transaksi yang No. Transaksi-nya sudah pernah di-import dilewati.
 */
class SalesImportService
{
    public const SHEET_PADEL = 'Booking Padel';

    public const SHEET_FNB = 'Penjualan F&B';

    public const SOURCE = 'IMPORT_EXCEL';

    public const MAX_ROWS_PER_SHEET = 5000;

    /**
     * Kolom template. "No." = nomor urut baris (hanya penunjuk, tidak dibaca). Semua pembayaran QRIS, jadi tidak ada
     * kolom metode bayar / referensi — kolom itu dari template lama tetap dibaca kalau masih ada.
     */
    public const PADEL_HEADERS = [
        'No.', 'No. Transaksi', 'Tanggal Bayar*', 'Nama Customer*', 'No. HP*', 'Email Customer', 'Lapangan*',
        'Tanggal Main*', 'Jam Mulai*', 'Durasi (Jam)*', 'Harga Sewa*', 'Diskon', 'Pajak', 'Biaya Layanan', 'Total Dibayar*',
        'Email Kasir', 'Catatan',
    ];

    public const FNB_HEADERS = [
        'No.', 'No. Transaksi', 'Tanggal Bayar*', 'Tipe Pesanan*', 'No. Meja', 'Nama Pelanggan', 'Menu*', 'Qty*',
        'Harga Satuan*', 'Catatan Item', 'Diskon', 'Pajak', 'Biaya Layanan', 'Total Dibayar*', 'Email Kasir',
    ];

    /** Judul kolom (huruf & angka saja, huruf kecil) → kunci internal. "Jam Selesai" = format template lama. */
    private const PADEL_COLUMNS = [
        'notransaksi' => 'ref', 'tanggalbayar' => 'paid_at', 'namacustomer' => 'name', 'nohp' => 'phone',
        'emailcustomer' => 'email', 'lapangan' => 'court', 'tanggalmain' => 'play_date', 'jammulai' => 'start',
        'durasijam' => 'duration', 'durasi' => 'duration', 'jamselesai' => 'end', 'hargasewa' => 'price',
        'diskon' => 'discount', 'pajak' => 'tax', 'biayalayanan' => 'service', 'totaldibayar' => 'total',
        'metodebayar' => 'method', 'refqris' => 'qris_ref', 'emailkasir' => 'cashier', 'catatan' => 'notes',
    ];

    private const FNB_COLUMNS = [
        'notransaksi' => 'ref', 'tanggalbayar' => 'paid_at', 'tipepesanan' => 'order_type', 'nomeja' => 'table',
        'namapelanggan' => 'customer', 'menu' => 'menu', 'qty' => 'qty', 'hargasatuan' => 'price',
        'catatanitem' => 'notes', 'diskon' => 'discount', 'pajak' => 'tax', 'biayalayanan' => 'service',
        'totaldibayar' => 'total', 'metodebayar' => 'method', 'refqris' => 'qris_ref', 'emailkasir' => 'cashier',
    ];

    private const PADEL_REQUIRED = ['paid_at', 'name', 'phone', 'court', 'play_date', 'start', 'price', 'total'];

    private const FNB_REQUIRED = ['paid_at', 'order_type', 'menu', 'qty', 'price', 'total'];

    /** Status booking yang menempati slot — sama dengan jadwal, plus booking yang sudah selesai dimainkan. */
    private const OCCUPYING_STATUSES = ['LOCKED', 'PENDING_PAYMENT', 'PENDING', 'PAID', 'CHECKED_IN', 'COMPLETED'];

    /** Panjang maksimal No. Transaksi supaya "IMP-PAD-" + nomor muat di orders.order_number (35). */
    private const MAX_REF_LENGTH = 27;

    /** @var array<int, array{sheet: string, row: int|null, ref: string|null, message: string}> */
    private array $errors = [];

    /** Hal yang perlu dicek admin tapi tidak menghalangi import (mis. No. HP milik akun dengan nama lain). */
    private array $warnings = [];

    /** @var array<int, string> akun customer yang dibuat oleh import yang sedang ditulis */
    private array $newCustomerIds = [];

    public function __construct(private PadelBookingService $padel) {}

    /**
     * Cek seluruh isi file tanpa menulis apa pun.
     *
     * @return array{padel: array<int, array>, fnb: array<int, array>, errors: array<int, array>, skipped: array<int, array>, summary: array<string, int|float>}
     */
    public function analyze(string $path): array
    {
        $this->errors = [];
        $this->warnings = [];
        $skipped = [];

        $sheets = $this->readSheets($path);
        $padel = $this->analyzePadel($sheets[self::SHEET_PADEL] ?? [], $skipped);
        $fnb = $this->analyzeFnb($sheets[self::SHEET_FNB] ?? [], $skipped);

        if (! $this->errors && ! $padel && ! $fnb && ! $skipped) {
            $this->error('-', null, null, 'Tidak ada data. Isi sheet "'.self::SHEET_PADEL.'" dan/atau "'.self::SHEET_FNB.'" dari template.');
        }

        return [
            'padel' => $padel,
            'fnb' => $fnb,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'skipped' => $skipped,
            'summary' => [
                'padel_transactions' => count($padel),
                'padel_bookings' => array_sum(array_map(fn ($tx) => count($tx['bookings']), $padel)),
                'padel_total' => array_sum(array_column($padel, 'total')),
                'new_customers' => count(array_unique(array_column(array_filter($padel, fn ($tx) => ! $tx['customer_exists']), 'phone'))),
                'fnb_transactions' => count($fnb),
                'fnb_items' => array_sum(array_map(fn ($tx) => array_sum(array_column($tx['items'], 'qty')), $fnb)),
                'fnb_total' => array_sum(array_column($fnb, 'total')),
                'skipped' => count($skipped),
                'errors' => count($this->errors),
                'warnings' => count($this->warnings),
            ],
        ];
    }

    /**
     * Tulis semua transaksi. Gagal (RuntimeException berisi daftar error) kalau masih ada error — tidak ada yang ditulis.
     *
     * @return array{batch: string, summary: array<string, int|float>, skipped: array<int, array>}
     */
    public function commit(string $path, User $admin, ?string $fileName = null): array
    {
        $this->padel->releaseExpiredLocks();
        $this->newCustomerIds = [];

        $result = DB::transaction(function () use ($path, $admin, $fileName) {
            // Kunci semua lapangan dulu (urutan sama dengan hold slot kasir) supaya slot tidak diambil kasir / online
            // di antara pengecekan bentrok dan penulisan.
            PadelCourt::query()->orderBy('id')->lockForUpdate()->get();

            $analysis = $this->analyze($path);
            if ($analysis['errors']) {
                throw new SalesImportFailed($analysis['errors']);
            }

            $batch = 'IMP-'.now('Asia/Jakarta')->format('YmdHis').'-'.strtoupper(Str::random(4));

            foreach ($analysis['padel'] as $tx) {
                $this->writePadel($tx, $admin, $batch);
            }
            foreach ($analysis['fnb'] as $tx) {
                $this->writeFnb($tx, $admin, $batch);
            }

            ActivityLogger::record(
                module: 'FINANCE',
                event: 'sales.imported',
                description: "Import penjualan Excel {$batch}: {$analysis['summary']['padel_transactions']} transaksi padel, {$analysis['summary']['fnb_transactions']} transaksi F&B",
                meta: [
                    'batch' => $batch,
                    'transaksi_padel' => $analysis['summary']['padel_transactions'],
                    'booking_padel' => $analysis['summary']['padel_bookings'],
                    'total_padel' => ActivityLogger::rupiah((float) $analysis['summary']['padel_total']),
                    'transaksi_fnb' => $analysis['summary']['fnb_transactions'],
                    'total_fnb' => ActivityLogger::rupiah((float) $analysis['summary']['fnb_total']),
                    'dilewati' => $analysis['summary']['skipped'],
                ],
                severity: ActivityLogger::WARNING,
                causer: $admin,
            );

            $record = SalesImportBatch::create([
                'batch_code' => $batch,
                'file_name' => $fileName ? mb_substr($fileName, 0, 255) : null,
                'imported_by_id' => $admin->id,
                'padel_transactions' => $analysis['summary']['padel_transactions'],
                'padel_bookings' => $analysis['summary']['padel_bookings'],
                'padel_total' => $analysis['summary']['padel_total'],
                'fnb_transactions' => $analysis['summary']['fnb_transactions'],
                'fnb_total' => $analysis['summary']['fnb_total'],
                'new_customer_ids' => array_values(array_unique($this->newCustomerIds)),
                'status' => SalesImportBatch::STATUS_IMPORTED,
            ]);

            return ['batch' => $batch, 'record' => $record, 'summary' => $analysis['summary'], 'skipped' => $analysis['skipped']];
        });

        // File asli disimpan setelah data berhasil masuk (bukti import, bisa diunduh lagi dari Riwayat Import).
        $stored = 'sales-imports/'.$result['batch'].'.xlsx';
        if (Storage::disk('local')->put($stored, file_get_contents($path))) {
            $result['record']->update(['file_path' => $stored]);
        }

        return $result;
    }

    /**
     * Batalkan satu import yang salah: semua order, booking, pembayaran, baris Buku Transaksi dari batch itu dihapus,
     * akun customer yang dibuat import ikut dihapus kalau belum dipakai transaksi lain. Ditolak kalau ada transaksi
     * yang sudah dipakai operasional (check-in, reschedule, refund, pembayaran tambahan). Jejaknya di Log Aktivitas.
     *
     * @return array{orders: int, bookings: int, total: float, customers_removed: int}
     */
    public function cancelBatch(SalesImportBatch $batch, User $admin): array
    {
        return DB::transaction(function () use ($batch, $admin) {
            $batch = SalesImportBatch::whereKey($batch->id)->lockForUpdate()->firstOrFail();
            if ($batch->status === SalesImportBatch::STATUS_CANCELLED) {
                throw new SalesImportCancelBlocked(['Import ini sudah dibatalkan sebelumnya.']);
            }

            $payments = Payment::where('payment_gateway', 'CASHIER_POS')
                ->where('payload_log->source', self::SOURCE)
                ->where('payload_log->import_batch', $batch->batch_code)
                ->get();
            $orders = Order::withTrashed()->with(['padelBookings' => fn ($q) => $q->withTrashed(), 'payments', 'refunds'])
                ->whereIn('id', $payments->pluck('order_id')->unique())
                ->lockForUpdate()
                ->get();

            $blocked = [];
            foreach ($orders as $order) {
                if ($order->payments->whereNotIn('id', $payments->pluck('id'))->isNotEmpty()) {
                    $blocked[] = "{$order->order_number}: ada pembayaran lain di luar import (mis. pelunasan selisih).";
                }
                if ($order->refunds->isNotEmpty()) {
                    $blocked[] = "{$order->order_number}: sudah ada pengajuan refund.";
                }
                foreach ($order->padelBookings as $booking) {
                    if ($booking->checked_in_at || $booking->status === 'CHECKED_IN') {
                        $blocked[] = "{$order->order_number}: booking {$booking->booking_code} sudah check-in.";
                    } elseif ((int) $booking->reschedule_count > 0) {
                        $blocked[] = "{$order->order_number}: booking {$booking->booking_code} sudah di-reschedule.";
                    } elseif ($booking->status === 'CANCELLED') {
                        $blocked[] = "{$order->order_number}: booking {$booking->booking_code} sudah dibatalkan lewat admin.";
                    }
                }
            }
            if ($blocked) {
                throw new SalesImportCancelBlocked($blocked);
            }

            $orderIds = $orders->pluck('id')->all();
            $bookingIds = $orders->flatMap(fn (Order $o) => $o->padelBookings->pluck('id'))->all();
            $total = (float) $payments->sum('amount');

            // Baris Buku Transaksi biasanya tidak boleh dihapus (koreksi = baris baru). Pengecualian khusus transaksi
            // import yang salah: transaksinya tidak pernah terjadi di sistem, jadi dihapus bersih — baris pembalik akan
            // tampil sebagai uang masuk + refund palsu di laporan. Total yang dihapus tercatat di Log Aktivitas.
            LedgerEntry::query()->whereIn('payment_id', $payments->pluck('id'))->toBase()->delete();
            \App\Models\Pos\OrderItem::whereIn('order_id', $orderIds)->delete();
            PadelBooking::withTrashed()->whereIn('id', $bookingIds)->forceDelete();
            Payment::whereIn('id', $payments->pluck('id'))->delete();
            Order::withTrashed()->whereIn('id', $orderIds)->forceDelete();

            $removedCustomers = 0;
            foreach (User::whereIn('id', $batch->new_customer_ids ?: [])->get() as $customer) {
                $inUse = Order::withTrashed()->where('user_id', $customer->id)->exists()
                    || PadelBooking::withTrashed()->where('user_id', $customer->id)->exists()
                    || \App\Models\Membership\UserMembership::where('user_id', $customer->id)->exists();
                if ($inUse) {
                    continue;
                }
                $customer->tokens()->delete();
                $customer->syncRoles([]);
                $customer->forceDelete();
                $removedCustomers++;
            }

            $batch->update([
                'status' => SalesImportBatch::STATUS_CANCELLED,
                'cancelled_by_id' => $admin->id,
                'cancelled_at' => now(),
            ]);

            ActivityLogger::record(
                module: 'FINANCE',
                event: 'sales.import_cancelled',
                description: "Import penjualan {$batch->batch_code} dibatalkan: ".count($orderIds).' transaksi (Rp '.number_format($total, 0, ',', '.').') dihapus',
                meta: [
                    'batch' => $batch->batch_code,
                    'transaksi_dihapus' => count($orderIds),
                    'booking_dihapus' => count($bookingIds),
                    'total_dihapus' => ActivityLogger::rupiah($total),
                    'no_order' => $orders->pluck('order_number')->take(50)->implode(', '),
                    'akun_customer_dihapus' => $removedCustomers,
                ],
                severity: ActivityLogger::CRITICAL,
                causer: $admin,
            );

            return ['orders' => count($orderIds), 'bookings' => count($bookingIds), 'total' => $total, 'customers_removed' => $removedCustomers];
        });
    }

    // ================================================================== BACA FILE

    /** @return array<string, array<int, array<string, mixed>>> nama sheet → [nomor baris Excel → nilai per kunci] */
    private function readSheets(string $path): array
    {
        $reader = new Reader();
        try {
            $reader->open($path);
        } catch (\Throwable) {
            $this->error('-', null, null, 'File tidak bisa dibaca. Pastikan file Excel (.xlsx) dari template, bukan .xls / .csv.');

            return [];
        }

        $result = [];
        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $name = match ($this->key($sheet->getName())) {
                    $this->key(self::SHEET_PADEL) => self::SHEET_PADEL,
                    $this->key(self::SHEET_FNB) => self::SHEET_FNB,
                    default => null,
                };
                if ($name === null) {
                    continue;
                }

                $columns = $name === self::SHEET_PADEL ? self::PADEL_COLUMNS : self::FNB_COLUMNS;
                $required = $name === self::SHEET_PADEL ? self::PADEL_REQUIRED : self::FNB_REQUIRED;
                $map = null;
                $rows = [];

                foreach ($sheet->getRowIterator() as $index => $row) {
                    $cells = $row->toArray();
                    if ($map === null) {
                        $map = [];
                        foreach ($cells as $col => $title) {
                            $key = $columns[$this->key((string) $this->stringify($title))] ?? null;
                            if ($key !== null && ! in_array($key, $map, true)) {
                                $map[$col] = $key;
                            }
                        }
                        $missing = array_diff($required, $map);
                        if ($name === self::SHEET_PADEL && ! array_intersect(['duration', 'end'], $map)) {
                            $missing[] = 'duration';
                        }
                        if ($missing) {
                            $this->error($name, $index, null, 'Kolom wajib tidak ditemukan di baris judul: '.implode(', ', array_map(fn ($k) => $this->columnTitle($name, $k), $missing)).'. Pakai template terbaru.');
                            continue 2;
                        }

                        continue;
                    }

                    $data = [];
                    foreach ($map as $col => $key) {
                        $data[$key] = $cells[$col] ?? null;
                    }
                    if (collect($data)->every(fn ($v) => $this->blank($v))) {
                        continue;
                    }
                    if (count($rows) >= self::MAX_ROWS_PER_SHEET) {
                        $this->error($name, $index, null, 'Maksimal '.self::MAX_ROWS_PER_SHEET.' baris per sheet — pecah jadi beberapa file.');
                        break;
                    }
                    $rows[$index] = $data;
                }

                $result[$name] = $rows;
            }
        } catch (\Throwable) {
            $this->error('-', null, null, 'Isi file Excel rusak / tidak bisa dibaca. Simpan ulang sebagai .xlsx lalu upload lagi.');
        } finally {
            $reader->close();
        }

        return $result;
    }

    // ================================================================== BOOKING PADEL

    private function analyzePadel(array $rows, array &$skipped): array
    {
        $sheet = self::SHEET_PADEL;
        $groups = $this->groupByRef($sheet, $rows);
        if (! $groups) {
            return [];
        }

        $courts = PadelCourt::all()->keyBy(fn (PadelCourt $c) => mb_strtolower(trim($c->name)));
        $existingOrders = $this->existingOrderNumbers(array_map(fn ($ref) => 'IMP-PAD-'.$ref, array_keys($groups)));
        $fingerprints = $this->existingFingerprints();
        $phoneNames = [];
        $now = now();
        $transactions = [];
        $intervals = [];
        $seen = [];

        foreach ($groups as $key => $txRows) {
            $ref = $this->isAutoRef($key) ? null : $key;
            $firstRow = array_key_first($txRows);
            if ($ref !== null && isset($existingOrders['IMP-PAD-'.$ref])) {
                $skipped[] = ['sheet' => $sheet, 'row' => $firstRow, 'ref' => $ref, 'message' => 'No. Transaksi sudah pernah di-import — dilewati.'];

                continue;
            }

            $errorsBefore = count($this->errors);
            $field = fn (string $col) => $this->txField($sheet, $ref, $txRows, $col);

            $paidAt = $this->parsePaidAt($sheet, $firstRow, $ref, $field('paid_at'));
            $name = trim((string) $this->stringify($field('name')));
            if ($name === '') {
                $this->error($sheet, $firstRow, $ref, 'Nama Customer wajib diisi.');
            } elseif (mb_strlen($name) > 100) {
                $this->error($sheet, $firstRow, $ref, 'Nama Customer maksimal 100 karakter.');
            }

            $phoneDigits = preg_replace('/\D/', '', (string) $this->stringify($field('phone')));
            // Sel angka di Excel membuang 0 di depan (0812… → 812…): kembalikan supaya cocok dengan akun yang ada.
            if (str_starts_with($phoneDigits, '8')) {
                $phoneDigits = '0'.$phoneDigits;
            }
            $phone = null;
            if (strlen($phoneDigits) < 8 || strlen($phoneDigits) > 15) {
                $this->error($sheet, $firstRow, $ref, 'No. HP tidak valid (8–15 digit angka).');
            } else {
                $phone = $this->padel->normalizePhoneNumber($phoneDigits);
            }

            $email = trim((string) $this->stringify($field('email')));
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->error($sheet, $firstRow, $ref, "Email Customer \"{$email}\" tidak valid.");
            }

            $common = $this->commonPayment($sheet, $firstRow, $ref, $field);

            $bookings = [];
            foreach ($txRows as $rowNo => $row) {
                $court = $courts[mb_strtolower(trim((string) $this->stringify($row['court'] ?? null)))] ?? null;
                if (! $court) {
                    $this->error($sheet, $rowNo, $ref, 'Lapangan "'.trim((string) $this->stringify($row['court'] ?? '')).'" tidak ada di sistem — tulis persis seperti di sheet "Lapangan & Menu".');
                }
                $playDate = $this->parseDate($row['play_date'] ?? null);
                if (! $playDate) {
                    $this->error($sheet, $rowNo, $ref, 'Tanggal Main tidak valid (format 2026-10-05).');
                }
                $start = $this->parseHour($row['start'] ?? null);
                if ($start === null || $start > 23) {
                    $this->error($sheet, $rowNo, $ref, 'Jam Mulai harus jam penuh 00:00–23:00 (contoh 10:00).');
                    $start = null;
                }

                // Durasi (Jam) = total jam main satu booking. Kolom "Jam Selesai" dari template lama tetap dibaca.
                $end = null;
                if (! $this->blank($row['duration'] ?? null)) {
                    $hours = $this->parseDuration($row['duration']);
                    if ($hours === null) {
                        $this->error($sheet, $rowNo, $ref, 'Durasi (Jam) harus angka jam bulat 1–24 (contoh 3).');
                    } elseif ($start !== null) {
                        $end = $start + $hours;
                    }
                } elseif (! $this->blank($row['end'] ?? null)) {
                    $end = $this->parseHour($row['end']);
                    if ($end === null) {
                        $this->error($sheet, $rowNo, $ref, 'Jam Selesai harus jam penuh 01:00–24:00 (contoh 12:00).');
                    }
                } else {
                    $this->error($sheet, $rowNo, $ref, 'Durasi (Jam) wajib diisi (contoh 3 untuk main 3 jam).');
                }
                if ($start !== null && $end !== null && $end <= $start) {
                    $this->error($sheet, $rowNo, $ref, 'Jam Selesai harus setelah Jam Mulai.');
                    $end = null;
                }
                if ($end !== null && $end > 24) {
                    $this->error($sheet, $rowNo, $ref, sprintf('Jam Mulai %02d:00 + durasi melewati jam 24:00 — pecah jadi 2 baris (sebelum & sesudah tengah malam).', $start));
                    $end = null;
                }

                $price = $this->parseMoney($row['price'] ?? null);
                if ($price === null) {
                    $this->error($sheet, $rowNo, $ref, 'Harga Sewa wajib diisi (angka rupiah, total untuk seluruh durasi).');
                }

                if (! $court || ! $playDate || $start === null || $end === null || $price === null) {
                    continue;
                }

                $startAt = Carbon::parse($playDate.' '.sprintf('%02d:00:00', $start));
                $endAt = Carbon::parse($playDate.' '.sprintf('%02d:00:00', $end));

                $bookings[] = [
                    'row' => $rowNo,
                    'court_id' => $court->id,
                    'court_name' => $court->name,
                    'play_date' => $playDate,
                    'start' => sprintf('%02d:00', $start),
                    'end' => sprintf('%02d:00', $end),
                    'hours' => $end - $start,
                    'start_at' => $startAt,
                    'end_at' => $endAt,
                    'price' => $price,
                    // Jam main sudah lewat → selesai; belum lewat → booking aktif (slot terkunci, QR check-in).
                    'status' => $endAt->lte($now) ? 'COMPLETED' : 'PAID',
                    'notes' => trim((string) $this->stringify($row['notes'] ?? null)) ?: null,
                ];
            }

            $subtotal = array_sum(array_column($bookings, 'price'));
            if (count($bookings) === count($txRows)) {
                $this->checkTotals($sheet, $firstRow, $ref, $subtotal, $common);
            }

            if (count($this->errors) > $errorsBefore) {
                continue;
            }

            // Upload ulang: transaksi yang isinya sama persis dengan yang sudah di-import dilewati (dengan atau tanpa No. Transaksi).
            $fingerprint = $this->fingerprint($sheet, $paidAt, [$phone], array_map(fn ($b) => [$b['court_id'], $b['play_date'], $b['start'], $b['end'], $b['price']], $bookings), $common);
            if (isset($fingerprints[$fingerprint])) {
                $skipped[] = ['sheet' => $sheet, 'row' => $firstRow, 'ref' => $ref, 'message' => 'Transaksi yang sama persis sudah pernah di-import — dilewati.'];

                continue;
            }
            if (isset($seen[$fingerprint])) {
                $this->error($sheet, $firstRow, $ref, "Sama persis dengan transaksi No. ".($seen[$fingerprint] - 1).". Kalau memang 2 transaksi berbeda, isi No. Transaksi yang berbeda.");

                continue;
            }
            $seen[$fingerprint] = $firstRow;

            foreach ($bookings as $b) {
                $slot = "{$b['court_name']} {$b['play_date']} {$b['start']}–{$b['end']}";
                foreach ($intervals[$b['court_id']] ?? [] as $other) {
                    if ($b['start_at']->lt($other['end']) && $b['end_at']->gt($other['start'])) {
                        $this->error($sheet, $b['row'], $ref, "{$slot} bentrok dengan No. ".($other['row'] - 1)." di file ini.");
                    }
                }
                $intervals[$b['court_id']][] = ['start' => $b['start_at'], 'end' => $b['end_at'], 'row' => $b['row']];

                $conflict = PadelBooking::where('court_id', $b['court_id'])
                    ->whereIn('status', self::OCCUPYING_STATUSES)
                    ->where('start_time', '<', $b['end_at']->format('Y-m-d H:i:s'))
                    ->where('end_time', '>', $b['start_at']->format('Y-m-d H:i:s'))
                    ->first();
                if ($conflict) {
                    $this->error($sheet, $b['row'], $ref, "{$slot} sudah terisi booking {$conflict->booking_code} di sistem.");
                }
            }

            if (count($this->errors) > $errorsBefore) {
                continue;
            }

            $this->checkCustomerName($sheet, $firstRow, $ref, $phone, $name, $phoneNames);

            $transactions[] = [
                'ref' => $ref,
                'row' => $firstRow,
                'order_number' => $this->orderNumber('PAD', $ref, $paidAt),
                'fingerprint' => $fingerprint,
                'paid_at' => $paidAt,
                'name' => $name,
                'phone' => $phone,
                'email' => $email ?: null,
                'customer_exists' => User::where('phone', $phone)->exists(),
                'cashier' => $common['cashier'],
                'qris_ref' => $common['qris_ref'],
                'bookings' => $bookings,
                'subtotal' => $subtotal,
                'discount' => $common['discount'],
                'tax' => $common['tax'],
                'service' => $common['service'],
                'total' => $common['total'],
            ];
        }

        return $transactions;
    }

    /**
     * No. HP adalah kunci akun customer. Kalau nomor itu sudah milik akun dengan nama lain (atau dipakai beberapa nama
     * berbeda di file), booking masuk ke SATU akun itu — admin diberi peringatan supaya salah ketik No. HP ketahuan.
     */
    private function checkCustomerName(string $sheet, int $row, ?string $ref, string $phone, string $name, array &$phoneNames): void
    {
        $owner = User::where('phone', $phone)->first();
        if ($owner && ! $this->sameName($owner->name, $name)) {
            $this->warnings[] = ['sheet' => $sheet, 'row' => $row, 'ref' => $ref, 'message' => "No. HP {$phone} sudah dipakai akun \"{$owner->name}\" — booking masuk ke akun itu, bukan atas nama \"{$name}\"."];
        } elseif (! $owner && isset($phoneNames[$phone]) && ! $this->sameName($phoneNames[$phone]['name'], $name)) {
            $this->warnings[] = ['sheet' => $sheet, 'row' => $row, 'ref' => $ref, 'message' => "No. HP {$phone} juga dipakai No. ".($phoneNames[$phone]['row'] - 1)." atas nama \"{$phoneNames[$phone]['name']}\" — semua masuk ke satu akun atas nama itu, bukan \"{$name}\"."];
        }
        $phoneNames[$phone] ??= ['name' => $name, 'row' => $row];
    }

    private function sameName(string $a, string $b): bool
    {
        $clean = fn (string $v) => trim(preg_replace('/\s+/', ' ', preg_replace('/\([^)]*\)|[^\p{L}\s]/u', ' ', mb_strtolower($v))));
        [$a, $b] = [$clean($a), $clean($b)];

        return $a !== '' && $b !== '' && (str_contains($a, $b) || str_contains($b, $a));
    }

    private function writePadel(array $tx, User $admin, string $batch): void
    {
        $paidAt = $tx['paid_at'];
        $cashier = $tx['cashier'] ?? $admin;
        $customer = $this->padel->findOrCreateWalkInCustomer($tx['name'], $tx['phone'], $tx['email']);
        if ($customer->wasRecentlyCreated) {
            $this->newCustomerIds[] = $customer->id;
        }

        $order = (new Order())->forceFill([
            'order_number' => $tx['order_number'],
            'user_id' => $customer->id,
            'cashier_id' => $cashier->id,
            'order_type' => 'WALK_IN',
            'subtotal' => $tx['subtotal'],
            'discount_amount' => $tx['discount'],
            'tax_amount' => $tx['tax'],
            'service_charge' => $tx['service'],
            'grand_total' => $tx['total'],
            'payment_status' => 'PAID',
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ]);
        $order->save();

        foreach ($tx['bookings'] as $b) {
            $booking = (new PadelBooking())->forceFill([
                'booking_code' => 'BK-PAD-'.strtoupper(Str::random(8)),
                'order_id' => $order->id,
                'user_id' => $customer->id,
                'court_id' => $b['court_id'],
                'booking_date' => $b['play_date'],
                'start_time' => $b['start_at'],
                'end_time' => $b['end_at'],
                'court_fee' => $b['price'],
                'coach_fee' => 0,
                'equipment_fee' => 0,
                'total_amount' => $b['price'],
                'status' => $b['status'],
                // Sama dengan booking lunas dari checkout (tiket QR untuk check-in).
                'qr_code_hash' => 'VNT-TICKET-'.strtoupper(bin2hex(random_bytes(16))),
                'created_at' => $paidAt,
                'updated_at' => $paidAt,
            ]);
            $booking->save();

            $order->items()->create([
                'item_type' => 'PADEL',
                'reference_id' => $booking->id,
                'item_name' => 'Sewa '.$b['court_name'],
                'quantity' => 1,
                'unit_price' => $b['price'],
                'subtotal' => $b['price'],
            ]);
        }

        $notes = array_values(array_filter(array_column($tx['bookings'], 'notes')));
        $this->writePayment($order, $tx, $cashier, $admin, $batch, 'PADEL_FRONTDESK', $notes);
    }

    // ================================================================== PENJUALAN F&B

    private function analyzeFnb(array $rows, array &$skipped): array
    {
        $sheet = self::SHEET_FNB;
        $groups = $this->groupByRef($sheet, $rows);
        if (! $groups) {
            return [];
        }

        $menus = FnbMenu::all()->keyBy(fn (FnbMenu $m) => mb_strtolower(trim($m->name)));
        $existingOrders = $this->existingOrderNumbers(array_map(fn ($ref) => 'IMP-FNB-'.$ref, array_keys($groups)));
        $fingerprints = $this->existingFingerprints();
        $transactions = [];
        $seen = [];

        foreach ($groups as $key => $txRows) {
            $ref = $this->isAutoRef($key) ? null : $key;
            $firstRow = array_key_first($txRows);
            if ($ref !== null && isset($existingOrders['IMP-FNB-'.$ref])) {
                $skipped[] = ['sheet' => $sheet, 'row' => $firstRow, 'ref' => $ref, 'message' => 'No. Transaksi sudah pernah di-import — dilewati.'];

                continue;
            }

            $errorsBefore = count($this->errors);
            $field = fn (string $col) => $this->txField($sheet, $ref, $txRows, $col);

            $paidAt = $this->parsePaidAt($sheet, $firstRow, $ref, $field('paid_at'));
            $type = match (preg_replace('/[^a-z]/', '', mb_strtolower((string) $this->stringify($field('order_type'))))) {
                'dinein', 'makanditempat' => 'DINE_IN',
                'bawapulang', 'takeaway', 'takeout' => 'TAKE_AWAY',
                default => null,
            };
            if ($type === null) {
                $this->error($sheet, $firstRow, $ref, 'Tipe Pesanan harus "Dine-In" atau "Bawa Pulang".');
            }
            $table = trim((string) $this->stringify($field('table')));
            if (mb_strlen($table) > 20) {
                $this->error($sheet, $firstRow, $ref, 'No. Meja maksimal 20 karakter.');
            }
            $customer = trim((string) $this->stringify($field('customer')));
            if (mb_strlen($customer) > 100) {
                $this->error($sheet, $firstRow, $ref, 'Nama Pelanggan maksimal 100 karakter.');
            }

            $common = $this->commonPayment($sheet, $firstRow, $ref, $field);

            $items = [];
            foreach ($txRows as $rowNo => $row) {
                $menuName = trim((string) $this->stringify($row['menu'] ?? null));
                $menu = $menus[mb_strtolower($menuName)] ?? null;
                if (! $menu) {
                    $this->error($sheet, $rowNo, $ref, "Menu \"{$menuName}\" tidak ada di sistem — tulis persis seperti di sheet \"Lapangan & Menu\".");
                }
                $qty = $this->parseMoney($row['qty'] ?? null);
                if ($qty === null || $qty < 1 || $qty > 999) {
                    $this->error($sheet, $rowNo, $ref, 'Qty harus angka 1–999.');
                }
                $price = $this->parseMoney($row['price'] ?? null);
                if ($price === null) {
                    $this->error($sheet, $rowNo, $ref, 'Harga Satuan wajib diisi (angka rupiah).');
                }
                $notes = trim(preg_replace('/\s+/u', ' ', (string) $this->stringify($row['notes'] ?? null)));
                if (mb_strlen($notes) > 120) {
                    $this->error($sheet, $rowNo, $ref, 'Catatan Item maksimal 120 karakter.');
                }

                if ($menu && $qty !== null && $qty >= 1 && $qty <= 999 && $price !== null) {
                    $items[] = [
                        'row' => $rowNo,
                        'menu_id' => $menu->id,
                        'menu_name' => $menu->name,
                        'qty' => (int) $qty,
                        'price' => $price,
                        'subtotal' => $price * (int) $qty,
                        'notes' => $notes ?: null,
                    ];
                }
            }

            $subtotal = array_sum(array_column($items, 'subtotal'));
            if (count($items) === count($txRows)) {
                $this->checkTotals($sheet, $firstRow, $ref, $subtotal, $common);
            }

            if (count($this->errors) > $errorsBefore) {
                continue;
            }

            $fingerprint = $this->fingerprint($sheet, $paidAt, [$type, $type === 'DINE_IN' ? $table : '', mb_strtolower($customer)], array_map(fn ($i) => [$i['menu_id'], $i['qty'], $i['price'], $i['notes']], $items), $common);
            if (isset($fingerprints[$fingerprint])) {
                $skipped[] = ['sheet' => $sheet, 'row' => $firstRow, 'ref' => $ref, 'message' => 'Transaksi yang sama persis sudah pernah di-import — dilewati.'];

                continue;
            }
            if (isset($seen[$fingerprint])) {
                $this->error($sheet, $firstRow, $ref, "Sama persis dengan transaksi No. ".($seen[$fingerprint] - 1).". Kalau memang 2 transaksi berbeda, isi No. Transaksi yang berbeda.");

                continue;
            }
            $seen[$fingerprint] = $firstRow;

            $transactions[] = [
                'ref' => $ref,
                'row' => $firstRow,
                'order_number' => $this->orderNumber('FNB', $ref, $paidAt),
                'fingerprint' => $fingerprint,
                'paid_at' => $paidAt,
                'order_type' => $type,
                'table' => $type === 'DINE_IN' ? ($table ?: null) : null,
                'customer' => $customer ?: null,
                'cashier' => $common['cashier'],
                'qris_ref' => $common['qris_ref'],
                'items' => $items,
                'subtotal' => $subtotal,
                'discount' => $common['discount'],
                'tax' => $common['tax'],
                'service' => $common['service'],
                'total' => $common['total'],
            ];
        }

        return $transactions;
    }

    private function writeFnb(array $tx, User $admin, string $batch): void
    {
        $paidAt = $tx['paid_at'];
        $cashier = $tx['cashier'] ?? $admin;

        $order = (new Order())->forceFill([
            'order_number' => $tx['order_number'],
            'user_id' => null,
            'cashier_id' => $cashier->id,
            'order_type' => $tx['order_type'],
            'table_number' => $tx['table'],
            'customer_name' => $tx['customer'],
            'subtotal' => $tx['subtotal'],
            'discount_amount' => $tx['discount'],
            'tax_amount' => $tx['tax'],
            'service_charge' => $tx['service'],
            'grand_total' => $tx['total'],
            'payment_status' => 'PAID',
            'created_at' => $paidAt,
            'updated_at' => $paidAt,
        ]);
        $order->save();

        foreach ($tx['items'] as $item) {
            $order->items()->create([
                'item_type' => 'FNB',
                'reference_id' => $item['menu_id'],
                'item_name' => $item['menu_name'],
                'quantity' => $item['qty'],
                'unit_price' => $item['price'],
                'subtotal' => $item['subtotal'],
                'notes' => $item['notes'],
            ]);
        }

        $this->writePayment($order, $tx, $cashier, $admin, $batch, 'FNB_COUNTER');
    }

    // ================================================================== PEMBAYARAN & BUKU TRANSAKSI

    private function writePayment(Order $order, array $tx, User $cashier, User $admin, string $batch, string $counter, array $notes = []): void
    {
        $payment = (new Payment())->forceFill([
            'order_id' => $order->id,
            'payment_gateway' => 'CASHIER_POS',
            'transaction_id' => $order->order_number,
            'amount' => $tx['total'],
            'payment_method' => 'QRIS',
            'status' => 'SUCCESS',
            'paid_at' => $tx['paid_at'],
            'pos_shift_id' => null,
            'payload_log' => array_filter([
                'source' => self::SOURCE,
                'import_batch' => $batch,
                'import_ref' => $tx['ref'],
                'import_fingerprint' => $tx['fingerprint'],
                'excel_customer_name' => $tx['name'] ?? $tx['customer'] ?? null,
                'imported_by_id' => $admin->id,
                'imported_by_name' => $admin->name,
                'counter' => $counter,
                'cashier_id' => $cashier->id,
                'cashier_name' => $cashier->name,
                'payment_method' => 'QRIS',
                'qris_details' => ['provider' => 'LAINNYA', 'rrn' => $tx['qris_ref'], 'sender_name' => null],
                'notes' => $notes ?: null,
            ], fn ($v) => $v !== null),
            'created_at' => $tx['paid_at'],
            'updated_at' => $tx['paid_at'],
        ]);
        $payment->save();

        app(LedgerWriter::class)->recordPayment($payment, LedgerEntry::TYPE_PAYMENT, $tx['paid_at']);
    }

    // ================================================================== ATURAN BERSAMA

    /** Kolom tingkat transaksi: Diskon, Pajak, Biaya Layanan, Total Dibayar, Metode, Ref QRIS, Email Kasir. */
    private function commonPayment(string $sheet, int $row, ?string $ref, callable $field): array
    {
        $money = function (string $key, string $label, bool $required = false) use ($sheet, $row, $ref, $field) {
            $raw = $field($key);
            if ($this->blank($raw)) {
                if ($required) {
                    $this->error($sheet, $row, $ref, "{$label} wajib diisi.");
                }

                return $required ? null : 0;
            }
            $value = $this->parseMoney($raw);
            if ($value === null) {
                $this->error($sheet, $row, $ref, "{$label} harus angka rupiah.");
            }

            return $value ?? 0;
        };

        $method = strtoupper(trim((string) $this->stringify($field('method'))));
        if ($method !== '' && $method !== 'QRIS') {
            $this->error($sheet, $row, $ref, "Metode Bayar \"{$method}\" tidak didukung — semua pembayaran import dicatat QRIS.");
        }

        $qrisRef = trim((string) $this->stringify($field('qris_ref')));
        if (mb_strlen($qrisRef) > 64) {
            $this->error($sheet, $row, $ref, 'Ref QRIS maksimal 64 karakter.');
        }

        $cashier = null;
        $cashierEmail = trim((string) $this->stringify($field('cashier')));
        if ($cashierEmail !== '') {
            $cashier = User::where('email', $cashierEmail)->first();
            if (! $cashier || ! $cashier->isStaff()) {
                $this->error($sheet, $row, $ref, "Email Kasir \"{$cashierEmail}\" bukan akun staf di sistem.");
                $cashier = null;
            }
        }

        return [
            'discount' => $money('discount', 'Diskon'),
            'tax' => $money('tax', 'Pajak'),
            'service' => $money('service', 'Biaya Layanan'),
            'total' => $money('total', 'Total Dibayar', true),
            'qris_ref' => $qrisRef ?: null,
            'cashier' => $cashier,
        ];
    }

    private function checkTotals(string $sheet, int $row, ?string $ref, int $subtotal, array $common): void
    {
        if ($common['total'] === null) {
            return;
        }
        if ($common['discount'] > $subtotal) {
            $this->error($sheet, $row, $ref, 'Diskon Rp '.$this->rp($common['discount']).' lebih besar dari total harga Rp '.$this->rp($subtotal).'.');

            return;
        }

        $expected = $subtotal - $common['discount'] + $common['tax'] + $common['service'];
        if ($expected !== $common['total']) {
            $this->error($sheet, $row, $ref, 'Total Dibayar Rp '.$this->rp($common['total']).' tidak sama dengan hitungan Rp '.$this->rp($expected)
                .' (harga Rp '.$this->rp($subtotal).' − diskon Rp '.$this->rp($common['discount']).' + pajak Rp '.$this->rp($common['tax']).' + biaya layanan Rp '.$this->rp($common['service']).').');
        }
    }

    private function parsePaidAt(string $sheet, int $row, ?string $ref, mixed $raw): ?Carbon
    {
        $paidAt = $this->parseDateTime($raw);
        if (! $paidAt) {
            $this->error($sheet, $row, $ref, 'Tanggal Bayar tidak valid (format 2026-10-05 14:30).');

            return null;
        }
        if ($paidAt->gt(now()->addMinutes(5))) {
            $this->error($sheet, $row, $ref, 'Tanggal Bayar tidak boleh di masa depan.');
        }

        return $paidAt;
    }

    /**
     * Baris → transaksi. Dengan No. Transaksi: baris ber-nomor sama = satu transaksi. Tanpa No. Transaksi: baris yang
     * Tanggal Bayar-nya terisi membuka transaksi baru (nomor dibuat sistem); baris di bawahnya yang Tanggal Bayar &
     * No. Transaksi-nya kosong ikut transaksi itu (lapangan / menu tambahan).
     *
     * @return array<string, array<int, array<string, mixed>>> kunci transaksi → baris-barisnya
     */
    private function groupByRef(string $sheet, array $rows): array
    {
        $groups = [];
        $last = null;
        foreach ($rows as $rowNo => $row) {
            $raw = trim((string) $this->stringify($row['ref'] ?? null));
            if ($raw === '') {
                if (! $this->blank($row['paid_at'] ?? null)) {
                    $last = '#'.$rowNo;
                } elseif ($last === null) {
                    $this->error($sheet, $rowNo, null, 'Tanggal Bayar wajib diisi di baris pertama transaksi.');

                    continue;
                }
                $groups[$last][$rowNo] = $row;

                continue;
            }

            $ref = trim(preg_replace('/-+/', '-', preg_replace('/[^A-Z0-9-]/', '-', strtoupper($raw))), '-');
            if ($ref === '' || strlen($ref) > self::MAX_REF_LENGTH) {
                $this->error($sheet, $rowNo, null, "No. Transaksi \"{$raw}\" tidak valid (huruf / angka / tanda -, maksimal ".self::MAX_REF_LENGTH.' karakter).');
                $last = null;

                continue;
            }
            $groups[$ref][$rowNo] = $row;
            $last = $ref;
        }

        return $groups;
    }

    private function isAutoRef(string $key): bool
    {
        return str_starts_with($key, '#');
    }

    /** Nomor order: dari No. Transaksi, atau dibuat sistem (tanggal bayar + kode acak) kalau kosong. */
    private function orderNumber(string $prefix, ?string $ref, Carbon $paidAt): string
    {
        return $ref !== null
            ? "IMP-{$prefix}-{$ref}"
            : "IMP-{$prefix}-".$paidAt->copy()->setTimezone('Asia/Jakarta')->format('ymd').'-'.strtoupper(Str::random(6));
    }

    /** Sidik jari isi transaksi (tanggal bayar, customer, isi, nominal) — penanda transaksi yang sama saat upload ulang. */
    private function fingerprint(string $sheet, Carbon $paidAt, array $who, array $lines, array $common): string
    {
        return sha1(json_encode([
            $sheet,
            $paidAt->copy()->setTimezone('Asia/Jakarta')->format('Y-m-d H:i'),
            $who,
            $lines,
            [$common['discount'], $common['tax'], $common['service'], $common['total']],
        ]));
    }

    /** @return array<string, true> */
    private function existingFingerprints(): array
    {
        return Payment::where('payment_gateway', 'CASHIER_POS')
            ->where('payload_log->source', self::SOURCE)
            ->get(['payload_log'])
            ->map(fn (Payment $p) => $p->payload_log['import_fingerprint'] ?? null)
            ->filter()
            ->flip()
            ->map(fn () => true)
            ->all();
    }

    /** Durasi main dalam jam bulat 1–24: 3 / "3" / "3 jam". */
    private function parseDuration(mixed $raw): ?int
    {
        if (is_int($raw) || is_float($raw)) {
            return $raw == (int) $raw && $raw >= 1 && $raw <= 24 ? (int) $raw : null;
        }
        if (! preg_match('/^(\d{1,2})(?:[.,]0+)?\s*(jam)?$/i', trim((string) $raw), $m)) {
            return null;
        }
        $hours = (int) $m[1];

        return $hours >= 1 && $hours <= 24 ? $hours : null;
    }

    /** Nilai kolom tingkat transaksi: baris pertama yang terisi; baris lain yang terisi harus sama. */
    private function txField(string $sheet, ?string $ref, array $txRows, string $key): mixed
    {
        $value = null;
        $shown = null;
        foreach ($txRows as $rowNo => $row) {
            $raw = $row[$key] ?? null;
            if ($this->blank($raw)) {
                continue;
            }
            $text = mb_strtolower(trim((string) $this->stringify($raw)));
            if ($shown === null) {
                $value = $raw;
                $shown = $text;
            } elseif ($text !== $shown) {
                $this->error($sheet, $rowNo, $ref, 'Kolom "'.$this->columnTitle($sheet, $key).'" beda dengan baris pertama transaksi ini. Cukup isi di baris pertama.');
            }
        }

        return $value;
    }

    /** @return array<string, true> */
    private function existingOrderNumbers(array $orderNumbers): array
    {
        return Order::withTrashed()->whereIn('order_number', $orderNumbers)->pluck('order_number')->flip()->map(fn () => true)->all();
    }

    // ================================================================== PARSER NILAI

    private function parseDateTime(mixed $raw): ?Carbon
    {
        if ($raw instanceof DateTimeInterface) {
            $wall = $raw->format('Y-m-d H:i:s');
            $dateOnly = $raw->format('H:i:s') === '00:00:00';

            return Carbon::parse($dateOnly ? $raw->format('Y-m-d').' 12:00:00' : $wall, 'Asia/Jakarta')->setTimezone(config('app.timezone'));
        }

        $text = trim((string) $this->stringify($raw));
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i', 'd/m/Y H:i:s', 'd/m/Y H:i', 'd-m-Y H:i', 'Y/m/d H:i'] as $format) {
            $date = $this->strictDate($format, $text);
            if ($date) {
                return $date->setTimezone(config('app.timezone'));
            }
        }
        $date = $this->parseDate($raw);

        return $date ? Carbon::parse($date.' 12:00:00', 'Asia/Jakarta')->setTimezone(config('app.timezone')) : null;
    }

    /** Tanggal saja → "Y-m-d". */
    private function parseDate(mixed $raw): ?string
    {
        if ($raw instanceof DateTimeInterface) {
            return $raw->format('Y-m-d');
        }

        $text = trim((string) $this->stringify($raw));
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'] as $format) {
            $date = $this->strictDate($format, $text);
            if ($date) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    private function strictDate(string $format, string $text): ?Carbon
    {
        if ($text === '') {
            return null;
        }
        try {
            $date = Carbon::createFromFormat('!'.$format, $text, 'Asia/Jakarta');
        } catch (\Throwable) {
            return null;
        }

        return $date && $date->format($format) === $text && $date->year >= 2020 && $date->year <= 2100 ? $date : null;
    }

    /** Jam penuh 0–24. Terima "10:00", "10.00", "10", sel jam Excel (DateTime / durasi / pecahan hari). */
    private function parseHour(mixed $raw): ?int
    {
        if ($raw instanceof DateTimeInterface) {
            return (int) $raw->format('i') === 0 && (int) $raw->format('s') === 0 ? (int) $raw->format('G') : null;
        }
        if ($raw instanceof DateInterval) {
            $hours = $raw->d * 24 + $raw->h;

            return $raw->i === 0 && $raw->s === 0 && $hours <= 24 ? $hours : null;
        }
        if (is_float($raw) || is_int($raw)) {
            if ($raw > 0 && $raw <= 1) {
                // Pecahan hari Excel (0,5 = 12:00).
                $minutes = (int) round($raw * 1440);

                return $minutes % 60 === 0 ? intdiv($minutes, 60) : null;
            }
            if ($raw == (int) $raw && $raw >= 0 && $raw <= 24) {
                return (int) $raw;
            }

            return null;
        }

        $text = trim((string) $raw);
        if (! preg_match('/^(\d{1,2})(?:[:.](\d{2}))?(?::00)?$/', $text, $m)) {
            return null;
        }
        $hour = (int) $m[1];

        return ($m[2] ?? '00') === '00' && $hour <= 24 ? $hour : null;
    }

    /** Rupiah bulat ≥ 0: 350000 / "350.000" / "Rp 350.000" / "350,000". */
    private function parseMoney(mixed $raw): ?int
    {
        if ($this->blank($raw)) {
            return null;
        }
        if (is_int($raw) || is_float($raw)) {
            return $raw >= 0 && abs($raw - round($raw)) < 0.001 ? (int) round($raw) : null;
        }

        $text = preg_replace('/^rp\.?/i', '', trim((string) $this->stringify($raw)));
        $text = str_replace([' ', "\u{00A0}"], '', $text);
        // Desimal ",00" / ".00" di ujung dibuang; titik / koma lain = pemisah ribuan.
        $text = preg_replace('/[.,]00$/', '', $text);
        if (! preg_match('/^\d{1,3}([.,]?\d{3})*$|^\d+$/', $text)) {
            return null;
        }

        return (int) preg_replace('/\D/', '', $text);
    }

    private function stringify(mixed $value): ?string
    {
        return match (true) {
            $value === null => null,
            $value instanceof DateTimeInterface => $value->format('H:i:s') === '00:00:00' ? $value->format('Y-m-d') : $value->format('Y-m-d H:i'),
            $value instanceof DateInterval => $value->format('%H:%I'),
            is_bool($value) => $value ? '1' : '0',
            is_float($value) && $value == (int) $value => (string) (int) $value,
            default => (string) $value,
        };
    }

    private function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }

    private function key(string $text): string
    {
        return preg_replace('/[^a-z0-9]/', '', mb_strtolower($text));
    }

    private function columnTitle(string $sheet, string $key): string
    {
        $columns = $sheet === self::SHEET_PADEL ? self::PADEL_COLUMNS : self::FNB_COLUMNS;
        $headers = $sheet === self::SHEET_PADEL ? self::PADEL_HEADERS : self::FNB_HEADERS;
        foreach ($headers as $title) {
            if (($columns[$this->key($title)] ?? null) === $key) {
                return rtrim($title, '*');
            }
        }

        return $key;
    }

    private function rp(int $amount): string
    {
        return number_format($amount, 0, ',', '.');
    }

    private function error(string $sheet, ?int $row, ?string $ref, string $message): void
    {
        $this->errors[] = ['sheet' => $sheet, 'row' => $row, 'ref' => $ref, 'message' => $message];
    }
}
