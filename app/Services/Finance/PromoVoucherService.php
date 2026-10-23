<?php

namespace App\Services\Finance;

use App\Filament\Pages\BukuTransaksi;
use App\Models\Pos\Order;
use App\Models\Pos\Voucher;
use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Modul 22 — membuat & mengubah voucher promo dari halaman Daftar Voucher. Cara sebar A (kode umum), B (kode unik massal:
 * baris kampanye + satu baris per kode, kuota 1), dan C (klaim ke akun: kuota = jumlah klaim).
 * Aturan pemakaian (periode, kanal, kategori, 1x per akun, kuota, klaim) ada di VoucherService::resolve(); di sini hanya
 * validasi isian, penyimpanan, dan Log Aktivitas. Tanggal diisi dalam WIB: mulai = awal hari, berakhir = akhir hari.
 */
class PromoVoucherService
{
    /** Isian yang tidak boleh diubah setelah promo pernah dipakai (angka laporan & pesanan lama jadi tidak cocok). */
    private const LOCKED_AFTER_USE = ['code', 'discount_type', 'discount_value', 'max_discount_amount', 'categories'];

    /** Huruf & angka kode acak — tanpa yang mirip satu sama lain (0/O, 1/I/L). */
    private const CODE_ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public function create(array $data, User $actor): Voucher
    {
        $distribution = $data['distribution'] ?? Voucher::DISTRIBUTION_CODE;
        if (! array_key_exists($distribution, Voucher::DISTRIBUTIONS)) {
            throw new HttpException(422, 'Pilih cara sebar promo.');
        }
        $data['distribution'] = $distribution;
        $isCampaign = $distribution === Voucher::DISTRIBUTION_UNIQUE;
        $codeCount = $isCampaign ? $this->validatedCodeCount($data) : 0;
        $prefix = $isCampaign ? $this->validatedPrefix($data) : null;
        $attributes = $this->validated($data, null);
        if ($isCampaign) {
            $attributes['quota'] = null; // kuota kampanye = jumlah kode (masing-masing kuota 1)
        }

        return DB::transaction(function () use ($attributes, $actor, $distribution, $codeCount, $prefix) {
            $voucher = Voucher::create($attributes + [
                'distribution' => $distribution,
                'used_count' => 0,
                'created_by_id' => $actor->id,
                'updated_by_id' => $actor->id,
            ]);

            if ($codeCount > 0) {
                $this->generateCampaignCodes($voucher, $prefix, $codeCount, $actor);
            }

            ActivityLogger::record(
                module: 'MARKETING',
                event: 'promo.created',
                description: "Membuat promo {$voucher->name} (".$this->label($voucher).') — '.$this->describe($voucher),
                subject: $voucher,
                meta: array_filter([
                    'kode' => $codeCount > 0 ? null : $voucher->code,
                    'cara_sebar' => Voucher::DISTRIBUTIONS[$distribution],
                    'jumlah_kode' => $codeCount ?: null,
                    'awalan' => $prefix,
                    'potongan' => $this->describe($voucher),
                    'kuota' => $codeCount > 0 ? $codeCount : ($voucher->quota ?? 'tanpa batas'),
                ], fn ($v) => $v !== null),
            );

            return $voucher;
        });
    }

    public function update(Voucher $voucher, array $data, User $actor): Voucher
    {
        $this->assertPromo($voucher);
        if ($voucher->isUniqueCode()) {
            throw new HttpException(422, 'Kode unik diatur lewat kampanyenya.');
        }
        // Cara sebar tidak bisa diganti setelah dibuat.
        $data['distribution'] = $voucher->distribution ?: Voucher::DISTRIBUTION_CODE;
        $attributes = $this->validated($data, $voucher);

        return DB::transaction(function () use ($voucher, $attributes, $actor) {
            $locked = Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();

            // Kampanye kode unik (B): kodenya internal & kuota = jumlah kode — dua-duanya tidak diubah di sini.
            if ($locked->isCampaign()) {
                unset($attributes['quota'], $attributes['code']);
            }

            if ($this->hasBeenUsed($locked)) {
                foreach (self::LOCKED_AFTER_USE as $field) {
                    if (array_key_exists($field, $attributes) && $this->differs($locked, $field, $attributes[$field])) {
                        throw new HttpException(422, 'Promo ini sudah dipakai'.($locked->isClaim() ? ' / diklaim' : '').' — kode, potongan, dan kategori tidak bisa diubah lagi. Nonaktifkan lalu buat promo baru kalau potongannya berubah.');
                    }
                }
            }

            // Isian kuota = kuota TOTAL; yang disimpan sisa kuota (berkurang saat order lunas — untuk C saat diklaim).
            if (array_key_exists('quota', $attributes) && $attributes['quota'] !== null) {
                $taken = $locked->isClaim() ? $locked->claims()->count() : (int) $locked->used_count;
                if ($attributes['quota'] < $taken) {
                    throw new HttpException(422, 'Kuota tidak boleh lebih kecil dari yang sudah '.($locked->isClaim() ? 'diklaim' : 'dipakai')." ({$taken}).");
                }
                $attributes['quota'] -= $taken;
            }

            $before = $locked->only(array_keys($attributes));
            $locked->fill($attributes + ['updated_by_id' => $actor->id])->save();
            $this->syncCampaignCodes($locked);

            ActivityLogger::record(
                module: 'MARKETING',
                event: 'promo.updated',
                description: "Mengubah promo {$locked->name} (".$this->label($locked).')',
                subject: $locked,
                changes: ['before' => $before, 'after' => $locked->only(array_keys($attributes))],
            );

            return $locked;
        });
    }

    public function setActive(Voucher $voucher, bool $active, User $actor): Voucher
    {
        $this->assertPromo($voucher);
        if ($voucher->isUniqueCode()) {
            throw new HttpException(422, 'Kode unik diatur lewat kampanyenya.');
        }
        DB::transaction(function () use ($voucher, $active, $actor) {
            $voucher->update(['is_active' => $active, 'updated_by_id' => $actor->id]);
            $this->syncCampaignCodes($voucher);
        });

        ActivityLogger::record(
            module: 'MARKETING',
            event: $active ? 'promo.activated' : 'promo.deactivated',
            description: ($active ? 'Mengaktifkan' : 'Menonaktifkan')." promo {$voucher->name} (".$this->label($voucher).')',
            subject: $voucher,
            severity: $active ? ActivityLogger::INFO : ActivityLogger::WARNING,
        );

        return $voucher;
    }

    /** Kode acak yang belum dipakai voucher mana pun. */
    public function generateCode(string $prefix = 'C61'): string
    {
        do {
            $code = strtoupper($prefix).'-'.$this->randomSuffix();
        } while (Voucher::where('code', $code)->exists());

        return $code;
    }

    /** Kode yang tampil di log / notifikasi: kampanye B ditulis "50 kode TRN-…". */
    public function label(Voucher $voucher): string
    {
        if (! $voucher->isCampaign()) {
            return $voucher->code;
        }
        $sample = Voucher::where('campaign_id', $voucher->id)->value('code');

        return Voucher::where('campaign_id', $voucher->id)->count().' kode '.($sample ? explode('-', $sample)[0].'-…' : '');
    }

    /**
     * Ringkasan kode kampanye B: total, sudah dipakai (lunas), sedang dipesan (order belum lunas), masih bebas.
     *
     * @return array{total: int, used: int, held: int, free: int}
     */
    public function campaignStats(Voucher $campaign): array
    {
        $codes = Voucher::where('campaign_id', $campaign->id);
        $total = (clone $codes)->count();
        $used = (clone $codes)->where('used_count', '>', 0)->count();
        $held = Order::whereIn('voucher_code', (clone $codes)->where('used_count', 0)->select('code'))
            ->whereIn('payment_status', ['UNPAID', 'PENDING_PAYMENT', 'PARTIALLY_PAID'])
            ->distinct()->count('voucher_code');

        return ['total' => $total, 'used' => $used, 'held' => $held, 'free' => max(0, $total - $used - $held)];
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
                match (true) {
                    $voucher->isUniqueCode() => '1 kode per akun',
                    $voucher->isClaim() => 'Khusus akun yang sudah klaim · 1x per akun',
                    default => '1x per akun',
                },
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

    /** Sudah dipakai (untuk C: atau sudah diklaim) → potongan & kategori dikunci. Kampanye B dihitung dari kode-kodenya. */
    public function hasBeenUsed(Voucher $voucher): bool
    {
        if ($voucher->isCampaign()) {
            $codes = Voucher::where('campaign_id', $voucher->id);

            return (clone $codes)->where('used_count', '>', 0)->exists()
                || Order::whereIn('voucher_code', (clone $codes)->select('code'))->exists();
        }

        return (int) $voucher->used_count > 0
            || Order::where('voucher_code', $voucher->code)->exists()
            || ($voucher->isClaim() && $voucher->claims()->exists());
    }

    /**
     * Cara sebar B: buat $count kode acak "AWALAN-XXXXXX" (kuota 1 per kode) yang menyalin aturan kampanye. Disisipkan
     * massal (tanpa event model) supaya Log Aktivitas tidak banjir satu baris per kode — dicatat sekali di create().
     */
    private function generateCampaignCodes(Voucher $campaign, string $prefix, int $count, User $actor): void
    {
        $codes = [];
        while (count($codes) < $count) {
            $need = $count - count($codes);
            $batch = [];
            for ($i = 0; $i < $need * 2; $i++) {
                $batch[] = $prefix.'-'.$this->randomSuffix();
            }
            $batch = array_values(array_diff(array_unique($batch), $codes));
            $taken = Voucher::whereIn('code', $batch)->pluck('code')->all();
            $codes = array_merge($codes, array_slice(array_values(array_diff($batch, $taken)), 0, $need));
        }

        $now = now();
        $rules = $this->rulesForCodes($campaign);
        foreach (array_chunk($codes, 200) as $chunk) {
            Voucher::insert(array_map(fn (string $code) => $rules + [
                'id' => (string) Str::ulid(),
                'code' => $code,
                'distribution' => Voucher::DISTRIBUTION_UNIQUE,
                'campaign_id' => $campaign->id,
                'quota' => 1,
                'used_count' => 0,
                'created_by_id' => $actor->id,
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }
    }

    /** Aturan kampanye yang disalin ke tiap kode unik (kolom mentah untuk insert / update massal). */
    private function rulesForCodes(Voucher $campaign): array
    {
        return [
            'name' => $campaign->name,
            'discount_type' => $campaign->discount_type,
            'discount_value' => $campaign->discount_value,
            'max_discount_amount' => $campaign->max_discount_amount,
            'min_order_amount' => $campaign->min_order_amount,
            'categories' => json_encode($campaign->promoCategories()),
            'channel' => $campaign->channel,
            'stack_with_membership' => (bool) $campaign->stack_with_membership,
            'valid_from' => $campaign->valid_from,
            'valid_until' => $campaign->valid_until,
            'is_active' => (bool) $campaign->is_active,
            'updated_by_id' => $campaign->updated_by_id,
        ];
    }

    /** Perubahan aturan / status kampanye ikut ke semua kodenya (kuota & pemakaian tiap kode tidak disentuh). */
    private function syncCampaignCodes(Voucher $campaign): void
    {
        if ($campaign->isCampaign()) {
            Voucher::where('campaign_id', $campaign->id)->update($this->rulesForCodes($campaign) + ['updated_at' => now()]);
        }
    }

    /** 6 karakter acak (random_int = kriptografis) — kode unik tidak bisa ditebak / diurutkan. */
    private function randomSuffix(): string
    {
        $suffix = '';
        for ($i = 0; $i < 6; $i++) {
            $suffix .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
        }

        return $suffix;
    }

    private function validatedCodeCount(array $data): int
    {
        $count = (int) ($data['code_count'] ?? 0);
        if ($count < 1 || $count > Voucher::MAX_UNIQUE_CODES) {
            throw new HttpException(422, 'Jumlah kode unik 1–'.Voucher::MAX_UNIQUE_CODES.'.');
        }

        return $count;
    }

    private function validatedPrefix(array $data): string
    {
        $prefix = strtoupper(trim((string) ($data['prefix'] ?? '')));
        if (! preg_match('/^[A-Z0-9]{2,8}$/', $prefix)) {
            throw new HttpException(422, 'Awalan kode 2–8 huruf / angka, mis. TRN.');
        }
        if (in_array($prefix, ['KR', 'CMP'], true)) {
            throw new HttpException(422, "Awalan {$prefix} dipakai sistem. Pakai awalan lain.");
        }

        return $prefix;
    }

    private function validated(array $data, ?Voucher $existing): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '' || mb_strlen($name) > 100) {
            throw new HttpException(422, 'Nama promo wajib diisi (maks. 100 karakter).');
        }

        $code = strtoupper(trim((string) ($data['code'] ?? '')));
        // Kampanye kode unik (B): kode baris kampanye internal (tidak bisa dipakai di checkout), selalu dibuat otomatis.
        if (($data['distribution'] ?? null) === Voucher::DISTRIBUTION_UNIQUE) {
            $code = $existing?->code ?? $this->generateCode('CMP');
        }
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
        return BukuTransaksi::rupiah($value);
    }
}
