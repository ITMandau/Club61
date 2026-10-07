<?php

namespace App\Services\Membership;

use App\Models\Membership\MembershipFacility;
use App\Models\Membership\MembershipPlan;
use App\Models\Membership\MembershipPlanBenefit;
use Illuminate\Support\Facades\Cache;

/**
 * SATU sumber nama fasilitas & teks benefit membership: halaman membership customer, My Club, teaser halaman depan,
 * form paket di admin, dan validasi check-in. Dulu teksnya ditulis langsung di 4 view berbeda (dan sebagian dummy).
 */
class MembershipFacilityService
{
    public const CACHE_KEY = 'membership_facilities';

    public const CACHE_TTL_SECONDS = 300;

    /** Dipakai kalau tabel belum ada (deploy lupa `php artisan migrate`). */
    private const SYSTEM_DEFAULTS = [
        'PADEL' => ['name' => 'Padel Court', 'badge' => 'PADEL', 'description' => null, 'usage_mode' => MembershipFacility::MODE_PADEL_BOOKING],
        'GYM' => ['name' => 'Fitness & Gym', 'badge' => 'GYM', 'description' => null, 'usage_mode' => MembershipFacility::MODE_CHECK_IN],
        'SAUNA' => ['name' => 'Sauna', 'badge' => 'SAUNA', 'description' => null, 'usage_mode' => MembershipFacility::MODE_WELLNESS_BOOKING],
    ];

    /** @var array<string, array<string, mixed>>|null */
    private ?array $facilities = null;

    /** @return array<string, array{code: string, name: string, badge: string, description: ?string, usage_mode: string, is_system: bool, is_active: bool, sort_order: int}> */
    public function all(): array
    {
        return $this->facilities ??= $this->load();
    }

    /** @return array<string, string> kode => nama, fasilitas aktif (+ $keep walau nonaktif, supaya form lama tetap terbaca) */
    public function options(array $keep = []): array
    {
        $options = [];
        foreach ($this->all() as $code => $f) {
            if ($f['is_active'] || in_array($code, $keep, true)) {
                $options[$code] = $f['name'].($f['is_active'] ? '' : ' (nonaktif)');
            }
        }

        return $options;
    }

    public function find(?string $code): ?array
    {
        return $code === null ? null : ($this->all()[strtoupper($code)] ?? null);
    }

    public function name(?string $code): string
    {
        return $this->find($code)['name'] ?? (string) $code;
    }

    public function mode(?string $code): ?string
    {
        return $this->find($code)['usage_mode'] ?? null;
    }

    /**
     * Tipe kuota yang masuk akal per mode pemakaian.
     *
     * @return array<string, string>
     */
    public function quotaTypesFor(?string $mode): array
    {
        return match ($mode) {
            MembershipFacility::MODE_PADEL_BOOKING => ['HOURS' => 'Jam Bermain (Hours)', 'NONE' => 'Tanpa Kuota (Diskon saja)'],
            MembershipFacility::MODE_WELLNESS_BOOKING, MembershipFacility::MODE_CHECK_IN => ['VISITS' => 'Sesi Kunjungan (Visits)', 'NONE' => 'Tanpa Kuota (Diskon saja)'],
            MembershipFacility::MODE_INFO => ['NONE' => 'Tanpa Kuota (benefit tampilan)'],
            default => ['HOURS' => 'Jam Bermain (Hours)', 'VISITS' => 'Sesi Kunjungan (Visits)', 'NONE' => 'Tanpa Kuota (Diskon / Unlimited)'],
        };
    }

    /**
     * Kartu benefit siap tampil untuk customer, urut sesuai master fasilitas. Fasilitas yang dinonaktifkan tidak
     * ditampilkan di halaman penjualan.
     *
     * @return array<int, array{code: string, badge: string, title: string, details: array<int, string>, description: ?string}>
     */
    public function presentPlan(MembershipPlan $plan): array
    {
        $cards = [];
        foreach ($plan->benefits as $benefit) {
            $facility = $this->find($benefit->facility);
            if ($facility && ! $facility['is_active']) {
                continue;
            }
            $cards[] = $this->presentBenefit($benefit) + ['sort' => $facility['sort_order'] ?? 999];
        }

        usort($cards, fn ($a, $b) => $a['sort'] <=> $b['sort']);

        return array_map(fn ($c) => array_diff_key($c, ['sort' => true]), $cards);
    }

    /** @return array{code: string, badge: string, title: string, details: array<int, string>, description: ?string} */
    public function presentBenefit(MembershipPlanBenefit $benefit): array
    {
        $facility = $this->find($benefit->facility);
        $name = $facility['name'] ?? (string) $benefit->facility;
        $quota = $benefit->quota_value !== null ? (float) $benefit->quota_value : null;
        $discount = (float) $benefit->discount_percent;

        $title = match (true) {
            $benefit->quota_type === 'HOURS' && $quota !== null => $this->number($quota).' Jam '.$name,
            $benefit->quota_type === 'VISITS' && $quota !== null => $this->number($quota).' Sesi '.$name,
            $benefit->quota_type === 'VISITS' => 'Akses Unlimited '.$name,
            $discount > 0 => 'Diskon '.$this->number($discount).'% '.$name,
            default => $name,
        };

        $details = [];
        if ($discount > 0 && in_array($benefit->quota_type, ['HOURS', 'VISITS'], true)) {
            $details[] = 'Diskon '.$this->number($discount).'% di luar kuota';
        }
        if ((int) $benefit->booking_priority_days > 0) {
            $details[] = 'Booking hingga H-'.(int) $benefit->booking_priority_days.' lebih awal';
        }
        if ($benefit->time_window_start && $benefit->time_window_end) {
            $details[] = 'Jam akses '.substr((string) $benefit->time_window_start, 0, 5).'–'.substr((string) $benefit->time_window_end, 0, 5);
        }

        $note = trim((string) (($benefit->extra_benefits ?? [])['note'] ?? ''));

        return [
            'code' => (string) $benefit->facility,
            'badge' => $facility['badge'] ?? (string) $benefit->facility,
            'title' => $title,
            'details' => $details,
            'description' => $note !== '' ? $note : ($facility['description'] ?? null),
        ];
    }

    /** Ringkasan satu baris untuk kartu pilihan paket (benefit pertama). */
    public function headline(MembershipPlan $plan): string
    {
        return $this->presentPlan($plan)[0]['title'] ?? 'Lihat benefit';
    }

    public function flush(): void
    {
        $this->facilities = null;
        Cache::forget(self::CACHE_KEY);
    }

    private function number(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    private function load(): array
    {
        try {
            $rows = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn () => MembershipFacility::orderBy('sort_order')->orderBy('name')->get()
                ->map(fn (MembershipFacility $f) => [
                    'code' => $f->code,
                    'name' => $f->name,
                    'badge' => $f->badge,
                    'description' => $f->description,
                    'usage_mode' => $f->usage_mode,
                    'is_system' => (bool) $f->is_system,
                    'is_active' => (bool) $f->is_active,
                    'sort_order' => (int) $f->sort_order,
                ])->keyBy('code')->all());
        } catch (\Illuminate\Database\QueryException $e) {
            report($e);
            $rows = [];
        }

        if ($rows === []) {
            $i = 0;
            foreach (self::SYSTEM_DEFAULTS as $code => $f) {
                $rows[$code] = $f + ['code' => $code, 'is_system' => true, 'is_active' => true, 'sort_order' => $i++];
            }
        }

        return $rows;
    }
}
