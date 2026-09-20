<?php

namespace App\Models\Pos;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ClubFinanceSetting extends Model
{
    use HasFactory;

    protected $table = 'club_finance_settings';

    protected $fillable = [
        'is_admin_fee_enabled',
        'admin_fee_name',
        'admin_fee_type',
        'admin_fee_amount',
        'admin_fee_channels',
        'is_tax_enabled',
        'tax_name',
        'tax_type',
        'tax_rate',
        'tax_channels',
        'module_overrides',
    ];

    protected $casts = [
        'is_admin_fee_enabled' => 'boolean',
        'admin_fee_amount' => 'float',
        'is_tax_enabled' => 'boolean',
        'tax_rate' => 'float',
        'module_overrides' => 'array',
    ];

    public const CACHE_KEY = 'club_finance_settings';

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget(self::CACHE_KEY);
        });

        static::deleted(function () {
            Cache::forget(self::CACHE_KEY);
        });
    }

    /**
     * Ambil singleton record pengaturan finansial klub dengan proteksi cache.
     */
    public static function getSettings(): self
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return self::firstOrCreate(
                ['id' => 1],
                [
                    'is_admin_fee_enabled' => false,
                    'admin_fee_name' => 'Biaya Layanan / Admin',
                    'admin_fee_type' => 'FIXED',
                    'admin_fee_amount' => 2500.00,
                    'admin_fee_channels' => 'ONLINE_ONLY',
                    'is_tax_enabled' => false,
                    'tax_name' => 'PB1 Pajak Daerah / PPh',
                    'tax_type' => 'PERCENTAGE',
                    'tax_rate' => 10.00,
                    'tax_channels' => 'ALL',
                    'module_overrides' => [
                        'PADEL' => ['tax_active' => true, 'admin_fee_active' => true],
                        'FNB' => ['tax_active' => true, 'admin_fee_active' => false],
                        'MERCH' => ['tax_active' => true, 'admin_fee_active' => false],
                        'WELLNESS' => ['tax_active' => true, 'admin_fee_active' => false],
                        'SALON' => ['tax_active' => true, 'admin_fee_active' => false],
                        'GYM' => ['tax_active' => true, 'admin_fee_active' => false],
                    ],
                ]
            );
        });
    }

    /**
     * Evaluasi apakah Pajak berlaku untuk channel dan modul tertentu.
     */
    public function isTaxApplicable(string $channel, string $module = 'PADEL'): bool
    {
        if (! $this->is_tax_enabled) {
            return false;
        }

        // Cek filter channel (ONLINE_ONLY, POS_ONLY, ALL)
        $channelUpper = strtoupper($channel);
        if ($this->tax_channels === 'ONLINE_ONLY' && ! str_contains($channelUpper, 'ONLINE')) {
            return false;
        }
        if ($this->tax_channels === 'POS_ONLY' && str_contains($channelUpper, 'ONLINE')) {
            return false;
        }

        // Cek modular override
        if (! empty($this->module_overrides[$module])) {
            return (bool) ($this->module_overrides[$module]['tax_active'] ?? true);
        }

        return true;
    }

    /**
     * Evaluasi apakah Biaya Admin berlaku untuk channel dan modul tertentu.
     */
    public function isAdminFeeApplicable(string $channel, string $module = 'PADEL'): bool
    {
        if (! $this->is_admin_fee_enabled) {
            return false;
        }

        // Cek filter channel (ONLINE_ONLY, POS_ONLY, ALL)
        $channelUpper = strtoupper($channel);
        if ($this->admin_fee_channels === 'ONLINE_ONLY' && ! str_contains($channelUpper, 'ONLINE')) {
            return false;
        }
        if ($this->admin_fee_channels === 'POS_ONLY' && str_contains($channelUpper, 'ONLINE')) {
            return false;
        }

        // Cek modular override
        if (! empty($this->module_overrides[$module])) {
            return (bool) ($this->module_overrides[$module]['admin_fee_active'] ?? true);
        }

        return true;
    }
}
