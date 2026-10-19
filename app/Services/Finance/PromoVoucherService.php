<?php

namespace App\Services\Finance;

use App\Models\Pos\Order;
use App\Models\Pos\Voucher;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Modul 22 tahap 2 — membuat & mengubah voucher promo dari halaman Daftar Voucher (cara sebar A: kode umum).
 * Aturan pemakaian (periode, kanal, kategori, 1x per akun, kuota) ada di VoucherService::resolve(); di sini hanya
 * validasi isian, penyimpanan, dan Log Aktivitas. Tanggal diisi dalam WIB: mulai = awal hari, berakhir = akhir hari.
 */
class PromoVoucherService
{
    /** Isian yang tidak boleh diubah setelah promo pernah dipakai (angka laporan & pesanan lama jadi tidak cocok). */
    private const LOCKED_AFTER_USE = ['code', 'discount_type', 'discount_value', 'max_discount_amount', 'categories'];

    public function create(array $data, User $actor): Voucher
    {
        $attributes = $this->validated($data, null);

        return DB::transaction(function () use ($attributes, $actor) {
            $voucher = Voucher::create($attributes + [
                'distribution' => Voucher::DISTRIBUTION_CODE,
                'used_count' => 0,
                'created_by_id' => $actor->id,
                'updated_by_id' => $actor->id,
            ]);

            ActivityLogger::record(
                module: 'MARKETING',
                event: 'promo.created',
                description: "Membuat promo {$voucher->name} ({$voucher->code}) — ".$this->describe($voucher),
                subject: $voucher,
                meta: ['kode' => $voucher->code, 'potongan' => $this->describe($voucher), 'kuota' => $voucher->quota ?? 'tanpa batas'],
            );

            return $voucher;
        });
    }

    public function update(Voucher $voucher, array $data, User $actor): Voucher
    {
        $this->assertPromo($voucher);
        $attributes = $this->validated($data, $voucher);

        return DB::transaction(function () use ($voucher, $attributes, $actor) {
            $locked = Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();

            if ($this->hasBeenUsed($locked)) {
                foreach (self::LOCKED_AFTER_USE as $field) {
                    if ($this->differs($locked, $field, $attributes[$field] ?? null)) {
                        throw new HttpException(422, 'Promo ini sudah dipakai — kode, potongan, dan kategori tidak bisa diubah lagi. Nonaktifkan lalu buat promo baru kalau potongannya berubah.');
                    }
                }
            }

            // Isian kuota = kuota TOTAL; yang disimpan sisa kuota (berkurang saat order lunas).
            if (array_key_exists('quota', $attributes) && $attributes['quota'] !== null) {
                if ($attributes['quota'] < (int) $locked->used_count) {
                    throw new HttpException(422, "Kuota tidak boleh lebih kecil dari yang sudah dipakai ({$locked->used_count}).");
                }
                $attributes['quota'] -= (int) $locked->used_count;
            }

            $before = $locked->only(array_keys($attributes));
            $locked->fill($attributes + ['updated_by_id' => $actor->id])->save();

            ActivityLogger::record(
                module: 'MARKETING',
                event: 'promo.updated',
                description: "Mengubah promo {$locked->name} ({$locked->code})",
                subject: $locked,
                changes: ['before' => $before, 'after' => $locked->only(array_keys($attributes))],
            );

            return $locked;
        });
    }

    public function setActive(Voucher $voucher, bool $active, User $actor): Voucher
    {
        $this->assertPromo($voucher);
        $voucher->update(['is_active' => $active, 'updated_by_id' => $actor->id]);

        ActivityLogger::record(
            module: 'MARKETING',
            event: $active ? 'promo.activated' : 'promo.deactivated',
            description: ($active ? 'Mengaktifkan' : 'Menonaktifkan')." promo {$voucher->name} ({$voucher->code})",
            subject: $voucher,
            severity: $active ? ActivityLogger::INFO : ActivityLogger::WARNING,
        );

        return $voucher;
    }

    /** Kode acak yang belum dipakai voucher mana pun (huruf & angka yang tidak mirip satu sama lain). */
    public function generateCode(string $prefix = 'C61'): string
    {
        do {
            $code = strtoupper($prefix).'-'.substr(str_shuffle(str_repeat('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', 3)), 0, 6);
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }

    /** Ringkas potongan untuk tabel & log, mis. "20% (maks. Rp50.000)" atau "Rp25.000". */
    public function describe(Voucher $voucher): string
    {
        if ($voucher->discount_type === Voucher::TYPE_PERCENT) {
            return rtrim(rtrim(number_format((float) $voucher->discount_value, 2, ',', '.'), '0'), ',').'%'
                .($voucher->max_discount_amount ? ' (maks. '.$this->rupiah($voucher->max_discount_amount).')' : '');
        }

        return $this->rupiah($voucher->discount_value);
    }

    /**
     * Rincian aturan voucher untuk kasir: potongannya apa + syarat yang berlaku (sama dengan yang dicek resolve()).
     *
     * @return array{name: ?string, headline: string, rules: list<string>}
     */
    public function terms(Voucher $voucher): array
    {
        $until = $voucher->valid_until ? 'Berlaku s/d '.$voucher->valid_until->timezone(LedgerReport::TIMEZONE)->format('d M Y') : null;

        if ($voucher->isCredit()) {
            return ['name' => null, 'headline' => 'Voucher saldo customer', 'rules' => array_values(array_filter([$until]))];
        }

        $target = implode(' + ', array_map(fn ($c) => $c === 'PADEL' ? 'sewa lapangan' : 'F&B', $voucher->promoCategories()));
        $headline = $voucher->discount_type === Voucher::TYPE_PERCENT
            ? 'Diskon '.$this->describe($voucher).' '.$target
            : 'Potongan '.$this->describe($voucher).' '.$target;

        return [
            'name' => $voucher->name,
            'headline' => $headline,
            'rules' => array_values(array_filter([
                (float) $voucher->min_order_amount > 0 ? 'Min. '.$target.' '.$this->rupiah($voucher->min_order_amount) : null,
                $until,
                match ($voucher->channel) {
                    Voucher::CHANNEL_ONLINE => 'Khusus booking online',
                    Voucher::CHANNEL_POS => 'Khusus kasir',
                    default => null,
                },
                $voucher->stack_with_membership ? 'Bisa digabung benefit member' : 'Tidak bisa digabung benefit member',
                '1x per akun',
            ])),
        ];
    }

    /** Satu baris keterangan voucher untuk struk, mis. "Promo Pembukaan · Diskon 20% (maks. Rp50.000) sewa lapangan". */
    public function receiptNote(?string $code): ?string
    {
        $voucher = $code ? Voucher::where('code', $code)->first() : null;
        if (! $voucher) {
            return null;
        }
        $terms = $this->terms($voucher);

        return ($terms['name'] ? $terms['name'].' · ' : '').$terms['headline'];
    }

    public function hasBeenUsed(Voucher $voucher): bool
    {
        return (int) $voucher->used_count > 0 || Order::where('voucher_code', $voucher->code)->exists();
    }

    private function validated(array $data, ?Voucher $existing): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw new HttpException(422, 'Nama promo wajib diisi (maks. 100 karakter).');
        }

        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        if ($code === '') {
            $code = $existing?->code ?? $this->generateCode();
        }
        if (! preg_match('/^[A-Z0-9][A-Z0-9-]{3,29}$/', $code)) {
            throw new HttpException(422, 'Kode promo 4–30 karakter: huruf, angka, dan tanda hubung saja.');
        }
        if (str_starts_with($code, 'KR-')) {
            throw new HttpException(422, 'Awalan KR- dipakai voucher saldo customer. Pakai awalan lain.');
        }
        if (Voucher::where('code', $code)->when($existing, fn ($q) => $q->whereKeyNot($existing->id))->exists()) {
            throw new HttpException(422, "Kode {$code} sudah dipakai voucher lain.");
        }

        $type = $data['discount_type'] ?? null;
        if (! in_array($type, [Voucher::TYPE_PERCENT, Voucher::TYPE_FIXED], true)) {
            throw new HttpException(422, 'Pilih jenis potongan: persen atau rupiah.');
        }
        $value = round((float) ($data['discount_value'] ?? 0), 2);
        if ($type === Voucher::TYPE_PERCENT && ($value <= 0 || $value > 100)) {
            throw new HttpException(422, 'Potongan persen harus 1–100%.');
        }
        if ($type === Voucher::TYPE_FIXED && $value < 1000) {
            throw new HttpException(422, 'Potongan rupiah minimal Rp1.000.');
        }
        $maxDiscount = $type === Voucher::TYPE_PERCENT && filled($data['max_discount_amount'] ?? null) ? round((float) $data['max_discount_amount'], 2) : null;
        if ($maxDiscount !== null && $maxDiscount < 1000) {
            throw new HttpException(422, 'Maksimal potongan minimal Rp1.000 (kosongkan kalau tanpa batas).');
        }

        $categories = array_values(array_intersect((array) ($data['categories'] ?? []), array_keys(Voucher::PROMO_CATEGORIES)));
        if ($categories === []) {
            throw new HttpException(422, 'Centang minimal satu kategori yang dipotong promo.');
        }

        $channel = $data['channel'] ?? Voucher::CHANNEL_ALL;
        if (! array_key_exists($channel, Voucher::CHANNELS)) {
            throw new HttpException(422, 'Pilihan kanal tidak dikenal.');
        }

        $quota = filled($data['quota'] ?? null) ? (int) $data['quota'] : null;
        if ($quota !== null && $quota < 1) {
            throw new HttpException(422, 'Kuota minimal 1 (kosongkan kalau tanpa batas).');
        }

        $from = filled($data['valid_from'] ?? null) ? Carbon::parse($data['valid_from'], LedgerReport::TIMEZONE)->startOfDay() : null;
        if (! filled($data['valid_until'] ?? null)) {
            throw new HttpException(422, 'Tanggal berakhir promo wajib diisi.');
        }
        $until = Carbon::parse($data['valid_until'], LedgerReport::TIMEZONE)->endOfDay();
        if ($from && $until->lt($from)) {
            throw new HttpException(422, 'Tanggal berakhir tidak boleh sebelum tanggal mulai.');
        }

        return [
            'name' => $name,
            'code' => $code,
            'discount_type' => $type,
            'discount_value' => $value,
            'max_discount_amount' => $maxDiscount,
            'min_order_amount' => max(0, round((float) ($data['min_order_amount'] ?? 0), 2)),
            'categories' => $categories,
            'channel' => $channel,
            'stack_with_membership' => (bool) ($data['stack_with_membership'] ?? false),
            'quota' => $quota,
            // Disimpan dalam zona waktu aplikasi (Eloquent menyimpan jam apa adanya tanpa konversi).
            'valid_from' => $from?->setTimezone(config('app.timezone')),
            'valid_until' => $until->setTimezone(config('app.timezone')),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];
    }

    private function differs(Voucher $voucher, string $field, mixed $new): bool
    {
        $old = $voucher->{$field};
        if ($field === 'categories') {
            return collect($old ?: ['PADEL'])->sort()->values()->all() !== collect($new ?: [])->sort()->values()->all();
        }
        if (in_array($field, ['discount_value', 'max_discount_amount'], true)) {
            return ($old === null ? null : round((float) $old, 2)) !== ($new === null ? null : round((float) $new, 2));
        }

        return (string) $old !== (string) $new;
    }

    private function assertPromo(Voucher $voucher): void
    {
        if ($voucher->isCredit()) {
            throw new HttpException(422, 'Voucher saldo customer tidak bisa diubah sebagai promo.');
        }
    }

    private function rupiah(mixed $value): string
    {
        return \App\Filament\Pages\BukuTransaksi::rupiah($value);
    }
}
