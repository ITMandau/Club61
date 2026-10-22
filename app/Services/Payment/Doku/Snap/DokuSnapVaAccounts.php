<?php

namespace App\Services\Payment\Doku\Snap;

use App\Models\Pos\PaymentGatewaySetting;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;

/**
 * Data akun SNAP VA per bank (DGPC — nomor VA dibuat DOKU): Partner Service ID (BIN, ≤8 digit) + prefix customer no.
 * Diisi admin di Metode Pembayaran Online → "SNAP VA DOKU" (izin manage_payment_gateway), disimpan di
 * payment_gateway_settings.doku_snap_va. Bukan rahasia: DOKU hanya menerima BIN milik client ID kita sendiri.
 * BCA juga bisa dari .env lama (DOKU_SNAP_BCA_*) kalau belum diisi di admin.
 *
 * Kanal = kode DOKU Checkout yang dipakai metode (OnlinePaymentCatalog::DOKU_CHANNELS); SNAP memakai nama kanalnya sendiri
 * (BSI: VIRTUAL_ACCOUNT_BSI — dokumentasi resmi DOKU SNAP BSI VA).
 */
class DokuSnapVaAccounts
{
    public const CACHE_KEY = 'doku_snap_va_accounts';

    public const CACHE_TTL_SECONDS = 300;

    /** kanal Checkout => [kanal SNAP (additionalInfo.channel), nama bank] — dokumentasi resmi DOKU SNAP VA per bank. */
    public const CHANNELS = [
        'VIRTUAL_ACCOUNT_BCA' => ['snap' => 'VIRTUAL_ACCOUNT_BCA', 'bank' => 'BCA'],
        'VIRTUAL_ACCOUNT_BANK_MANDIRI' => ['snap' => 'VIRTUAL_ACCOUNT_BANK_MANDIRI', 'bank' => 'Mandiri'],
        'VIRTUAL_ACCOUNT_BRI' => ['snap' => 'VIRTUAL_ACCOUNT_BRI', 'bank' => 'BRI'],
        'VIRTUAL_ACCOUNT_BNI' => ['snap' => 'VIRTUAL_ACCOUNT_BNI', 'bank' => 'BNI'],
        'VIRTUAL_ACCOUNT_BANK_SYARIAH_MANDIRI' => ['snap' => 'VIRTUAL_ACCOUNT_BSI', 'bank' => 'BSI'],
    ];

    public const PARTNER_SERVICE_ID_PATTERN = '/^\d{1,8}$/';

    /** Prefix boleh kosong untuk bank yang tidak memberi prefix sub-BIN. */
    public const CUSTOMER_NO_PATTERN = '/^\d{0,20}$/';

    public static function supports(string $channel): bool
    {
        return isset(self::CHANNELS[$channel]);
    }

    public static function snapChannel(string $channel): ?string
    {
        return self::CHANNELS[$channel]['snap'] ?? null;
    }

    public static function bank(string $channel): ?string
    {
        return self::CHANNELS[$channel]['bank'] ?? null;
    }

    /**
     * Isian admin yang tersimpan (sudah dibersihkan, kanal di luar daftar dibuang).
     *
     * @return array<string, array{partner_service_id: string, customer_no: string}>
     */
    public function saved(): array
    {
        try {
            $raw = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn () => PaymentGatewaySetting::query()->value('doku_snap_va'));
        } catch (QueryException $e) {
            // Kolom belum dimigrasi: semua VA tetap lewat DOKU Checkout (BCA masih bisa dari .env).
            report($e);
            $raw = null;
        }

        $raw = is_string($raw) ? json_decode($raw, true) : $raw;

        return self::clean(is_array($raw) ? $raw : []);
    }

    /**
     * Data akun untuk satu kanal, dan sumbernya. Null = belum diisi → kanal ini lewat DOKU Checkout.
     *
     * @return array{partner_service_id: string, customer_no: string, source: string}|null
     */
    public function account(string $channel): ?array
    {
        if (! self::supports($channel)) {
            return null;
        }
        if ($saved = $this->saved()[$channel] ?? null) {
            return $saved + ['source' => 'ADMIN'];
        }
        if ($channel === 'VIRTUAL_ACCOUNT_BCA') {
            $psid = trim((string) config('services.doku.snap.bca_partner_service_id'));
            $customerNo = trim((string) config('services.doku.snap.bca_customer_no'));
            if ($psid !== '' || $customerNo !== '') {
                return ['partner_service_id' => $psid, 'customer_no' => $customerNo, 'source' => 'ENV'];
            }
        }

        return null;
    }

    /**
     * Bersihkan isian admin: hanya kanal yang dikenal, Partner Service ID wajib (tanpa itu kanal dianggap kosong).
     *
     * @return array<string, array{partner_service_id: string, customer_no: string}>
     */
    public static function clean(array $input): array
    {
        $clean = [];
        foreach (self::CHANNELS as $channel => $_) {
            $row = is_array($input[$channel] ?? null) ? $input[$channel] : [];
            $psid = trim((string) ($row['partner_service_id'] ?? ''));
            if ($psid === '') {
                continue;
            }
            $clean[$channel] = ['partner_service_id' => $psid, 'customer_no' => trim((string) ($row['customer_no'] ?? ''))];
        }

        return $clean;
    }

    /**
     * Kesalahan format isian admin (null = valid). Dipakai saat simpan & sebelum membuat VA.
     */
    public static function invalidReason(array $account): ?string
    {
        if (! preg_match(self::PARTNER_SERVICE_ID_PATTERN, (string) ($account['partner_service_id'] ?? ''))) {
            return 'Partner Service ID harus 1–8 digit angka';
        }
        if (! preg_match(self::CUSTOMER_NO_PATTERN, (string) ($account['customer_no'] ?? ''))) {
            return 'Prefix customer no. hanya angka (maks. 20 digit)';
        }

        return null;
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
