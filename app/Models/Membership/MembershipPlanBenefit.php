<?php

namespace App\Models\Membership;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MembershipPlanBenefit extends Model
{
    use HasUlids;

    protected $fillable = [
        'plan_id',
        'facility',
        'quota_type',
        'quota_value',
        'discount_percent',
        'booking_priority_days',
        'time_window_start',
        'time_window_end',
        'extra_benefits',
    ];

    protected $casts = [
        'quota_value' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'booking_priority_days' => 'integer',
        'extra_benefits' => 'array',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(MembershipPlan::class, 'plan_id');
    }

    /**
     * Kalimat benefit siap tampil (dipakai di Membership Teaser halaman depan). quota_type
     * "NONE" BUKAN berarti benefit-nya kosong — itu artinya "tanpa kuota tetap, bayar per
     * pakai dengan diskon" (lihat MembershipPlanResource). quota_value null pada VISITS
     * berarti akses unlimited. Tanpa penanganan ini, render lama menampilkan teks rusak
     * seperti "0 none GYM" untuk baris diskon.
     */
    public function describe(): string
    {
        // Nama dari Master Fasilitas (bisa diubah admin), bukan lagi daftar tetap PADEL/GYM/SAUNA.
        $facilityLabel = app(\App\Services\Membership\MembershipFacilityService::class)->name($this->facility);

        if ($this->quota_type === 'HOURS' && $this->quota_value !== null) {
            return __('site.benefit_hours', ['value' => (int) $this->quota_value, 'facility' => $facilityLabel]);
        }

        if ($this->quota_type === 'VISITS') {
            return $this->quota_value !== null
                ? __('site.benefit_visits', ['value' => (int) $this->quota_value, 'facility' => $facilityLabel])
                : __('site.benefit_visits_unlimited', ['facility' => $facilityLabel]);
        }

        if ((float) $this->discount_percent > 0) {
            $percent = rtrim(rtrim(number_format((float) $this->discount_percent, 2, '.', ''), '0'), '.');

            return __('site.benefit_discount', ['percent' => $percent, 'facility' => $facilityLabel]);
        }

        return $facilityLabel;
    }
}
