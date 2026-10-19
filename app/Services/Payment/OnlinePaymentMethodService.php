<?php

namespace App\Services\Payment;

use App\Models\Pos\OnlinePaymentMethod;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * SATU-SATUNYA sumber daftar metode pembayaran online: halaman checkout padel, modal ganti metode di invoice,
 * halaman membership, endpoint API mobile, validasi server (checkout padel, bayar ulang / selisih reschedule,
 * checkout membership), pemetaan ke Midtrans Snap, dan label riwayat pembayaran.
 */
class OnlinePaymentMethodService
{
    public const CACHE_KEY = 'online_payment_methods';

    public const CACHE_TTL_SECONDS = 300;

    /** @var array<string, array<string, mixed>>|null */
    private ?array $methods = null;

    /**
     * Semua metode di katalog + pengaturannya (aktif & nonaktif), urut sesuai pengaturan admin.
     *
     * @return array<string, array{code: string, label: string, description: ?string, badge: string, group: string, is_active: bool, sort_order: int, min_amount: ?float, max_amount: ?float}>
     */
    public function all(): array
    {
        return $this->methods ??= $this->load();
    }

    /**
     * Metode yang boleh dipilih customer untuk tagihan sebesar $amount (null = abaikan batas nominal). Metode yang tidak
     * punya kode kanal untuk gateway yang dipakai (default: gateway online pilihan admin) disembunyikan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function available(?float $amount = null, ?string $gateway = null): array
    {
        $gateway ??= app(OnlineGatewayRouter::class)->onlineGateway();

        return array_values(array_filter(
            $this->all(),
            fn (array $m) => $m['is_active'] && $this->supportsGateway($m, $gateway) && ($amount === null || $this->withinLimits($m, $amount))
        ));
    }

    /**
     * Metode untuk "Bayar Otomatis" di layar kasir (POS Walk-In, F&B, Jual Membership): aktif, dicentang "Tampil di
     * Kasir" di menu Metode Pembayaran Online, punya kanal di gateway QR kasir, dan masuk batas nominal tagihan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forPos(?float $amount = null): array
    {
        $gateway = app(OnlineGatewayRouter::class)->posQrisGateway();

        return array_values(array_filter($this->available($amount, $gateway), fn (array $m) => $m['show_at_pos'] ?? false));
    }

    /** Validasi metode "Bayar Otomatis" pilihan kasir (server — pilihan di layar bisa direkayasa). */
    public function assertPosSelectable(string $code, float $amount): string
    {
        $code = $this->assertSelectable($code, $amount, app(OnlineGatewayRouter::class)->posQrisGateway());

        if (! ($this->all()[$code]['show_at_pos'] ?? false)) {
            throw new HttpException(422, 'Metode '.$this->all()[$code]['label'].' tidak diaktifkan untuk kasir. Aktifkan "Tampil di Kasir" di menu Metode Pembayaran Online, atau pilih metode lain.');
        }

        return $code;
    }

    /**
     * Data untuk halaman checkout (Alpine/JS): semua metode AKTIF beserta batas nominalnya — halaman menyaring
     * sendiri sesuai total tagihan yang bisa berubah (voucher, add-on), server tetap memvalidasi ulang.
     *
     * @return array<int, array<string, mixed>>
     */
    public function forFrontend(?float $amount = null): array
    {
        return array_map(fn (array $m) => $this->presentForFrontend($m), $this->available($amount));
    }

    /**
     * SEMUA metode katalog (termasuk yang nonaktif) — untuk menampilkan nama metode pada riwayat pembayaran lama,
     * bukan untuk dipilih.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalogForDisplay(): array
    {
        return array_values(array_map(fn (array $m) => $this->presentForFrontend($m), $this->all()));
    }

    private function presentForFrontend(array $m): array
    {
        return [
            'id' => strtolower($m['code']),
            'code' => $m['code'],
            'name' => $m['label'],
            'note' => $m['description'],
            'badge' => $m['badge'],
            'group' => $m['group'],
            'fee' => 0,
            'min_amount' => $m['min_amount'],
            'max_amount' => $m['max_amount'],
        ];
    }

    /**
     * Validasi metode pilihan customer sebelum membuat transaksi Midtrans. Dipanggil di semua jalur checkout.
     *
     * @return string kode metode yang sudah dinormalisasi
     *
     * @throws HttpException 422 metode tidak dikenal / nonaktif / di luar batas nominal
     */
    public function assertSelectable(string $code, float $amount, ?string $gateway = null): string
    {
        $code = strtoupper(trim($code));
        $method = $this->all()[$code] ?? null;
        $gateway ??= app(OnlineGatewayRouter::class)->onlineGateway();

        if ($method === null) {
            throw new HttpException(422, 'Metode pembayaran tidak dikenali. Pilih salah satu metode yang tersedia.');
        }

        if (! $method['is_active'] || ! $this->supportsGateway($method, $gateway)) {
            throw new HttpException(422, "Metode pembayaran {$method['label']} sedang tidak tersedia. Silakan pilih metode lain.");
        }

        if (! $this->withinLimits($method, $amount)) {
            $limit = $method['max_amount'] !== null && $amount > $method['max_amount']
                ? 'maksimal Rp '.number_format($method['max_amount'], 0, ',', '.')
                : 'minimal Rp '.number_format((float) $method['min_amount'], 0, ',', '.');

            throw new HttpException(422, "{$method['label']} hanya bisa dipakai untuk transaksi {$limit}. Total tagihan Anda Rp "
                .number_format($amount, 0, ',', '.').' — silakan pilih metode lain.');
        }

        return $code;
    }

    /**
     * Kode kanal gateway untuk kode metode. Metode bawaan diambil dari katalog (bukan dari tabel) supaya transaksi lama
     * yang dibayar ulang tetap terpetakan benar; metode tambahan admin dari pilihan kanalnya (hanya kode resmi).
     *
     * @return array<int, string>|null null = metode tidak dikenal
     */
    public function channels(string $code, string $gateway): ?array
    {
        $code = strtoupper($code);
        $key = strtoupper($gateway) === OnlineGatewayRouter::DOKU ? 'doku_channels' : 'midtrans_channels';

        if (OnlinePaymentCatalog::has($code)) {
            return $key === 'doku_channels' ? OnlinePaymentCatalog::dokuChannels($code) : OnlinePaymentCatalog::midtransChannels($code);
        }

        return $this->all()[$code][$key] ?? null;
    }

    /**
     * Channel Snap (`enabled_payments`) untuk kode metode (lihat channels()).
     *
     * @return array<int, string>|null
     */
    public function midtransChannels(string $code): ?array
    {
        return $this->channels($code, OnlineGatewayRouter::MIDTRANS);
    }

    /** Metode punya kode kanal di gateway ini? Tanpa kode = tidak bisa dibayar lewat gateway itu. */
    public function supportsGateway(array $method, string $gateway): bool
    {
        return ! empty($this->channels($method['code'], $gateway));
    }

    /** Nama tampilan metode online (pengaturan admin → katalog). Null kalau bukan metode online. */
    public function label(string $code): ?string
    {
        $code = strtoupper($code);

        return $this->all()[$code]['label'] ?? OnlinePaymentCatalog::defaultLabel($code);
    }

    public function flush(): void
    {
        $this->methods = null;
        Cache::forget(self::CACHE_KEY);
    }

    private function withinLimits(array $method, float $amount): bool
    {
        return ($method['min_amount'] === null || $amount >= $method['min_amount'])
            && ($method['max_amount'] === null || $amount <= $method['max_amount']);
    }

    private function load(): array
    {
        try {
            // TTL (bukan forever) sebagai jaring pengaman: kalau cache sempat terisi data lama (balapan baca vs simpan),
            // paling lama 5 menit sudah ikut pengaturan terbaru.
            $settings = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn () => OnlinePaymentMethod::orderBy('sort_order')->orderBy('code')->get()
                ->map(fn (OnlinePaymentMethod $m) => [
                    'code' => $m->code,
                    'label' => $m->label,
                    'description' => $m->description,
                    'badge' => $m->badge,
                    'is_active' => (bool) $m->is_active,
                    'show_at_pos' => (bool) ($m->show_at_pos ?? false),
                    'sort_order' => (int) $m->sort_order,
                    'min_amount' => $m->min_amount !== null ? (float) $m->min_amount : null,
                    'max_amount' => $m->max_amount !== null ? (float) $m->max_amount : null,
                    'kind' => $m->kind,
                    'midtrans_channels' => array_values((array) ($m->midtrans_channels ?? [])),
                    'doku_channels' => array_values((array) ($m->doku_channels ?? [])),
                    'is_custom' => (bool) ($m->is_custom ?? false),
                ])->keyBy('code')->all());
        } catch (\Illuminate\Database\QueryException $e) {
            // Tabel belum ada (deploy lupa `php artisan migrate`): pakai default katalog supaya checkout tetap jalan.
            report($e);
            $settings = [];
        }

        $methods = [];
        $index = 0;
        foreach (OnlinePaymentCatalog::all() as $code => $catalog) {
            $setting = $settings[$code] ?? null;
            $methods[$code] = [
                'code' => $code,
                'label' => $setting['label'] ?? $catalog['label'],
                'description' => $setting['description'] ?? $catalog['description'],
                'badge' => $setting['badge'] ?? $catalog['badge'],
                'group' => $catalog['group'],
                'is_active' => $setting['is_active'] ?? $catalog['default_active'],
                'show_at_pos' => $setting['show_at_pos'] ?? ($code === 'QRIS'),
                'sort_order' => $setting['sort_order'] ?? (1000 + $index),
                'min_amount' => $setting ? $setting['min_amount'] : $catalog['default_min'],
                'max_amount' => $setting ? $setting['max_amount'] : $catalog['default_max'],
                'kind' => $catalog['group'],
                'midtrans_channels' => $catalog['midtrans'],
                'doku_channels' => $catalog['doku'],
                'is_custom' => false,
            ];
            $index++;
        }

        // Metode tambahan admin (jenis QRIS / VA, kanal dari daftar kode resmi — lihat MetodePembayaranOnline).
        foreach ($settings as $code => $setting) {
            if (isset($methods[$code]) || ! ($setting['is_custom'] ?? false)) {
                continue;
            }
            $methods[$code] = [
                'code' => $code,
                'label' => $setting['label'],
                'description' => $setting['description'],
                'badge' => $setting['badge'],
                'group' => $setting['kind'] ?? 'VA',
                'is_active' => $setting['is_active'],
                'show_at_pos' => $setting['show_at_pos'],
                'sort_order' => $setting['sort_order'],
                'min_amount' => $setting['min_amount'],
                'max_amount' => $setting['max_amount'],
                'kind' => $setting['kind'] ?? 'VA',
                'midtrans_channels' => $setting['midtrans_channels'],
                'doku_channels' => $setting['doku_channels'],
                'is_custom' => true,
            ];
        }

        uasort($methods, fn ($a, $b) => $a['sort_order'] <=> $b['sort_order']);

        return $methods;
    }
}
