<?php

namespace App\Filament\Pages;

use App\Models\Pos\ClubFinanceSetting;
use App\Services\Finance\TaxAndFeeService;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class PengaturanBiayaPajak extends Page
{
    use HasPageShield;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?string $navigationLabel = 'Biaya & Pajak';

    protected static string | UnitEnum | null $navigationGroup = 'Main Menu';

    protected static ?string $title = 'Pengaturan Biaya Layanan & Pajak Terpusat';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.pengaturan-biaya-pajak';

    // Biaya Admin / Layanan
    public bool $isAdminFeeEnabled = false;
    public string $adminFeeName = 'Biaya Layanan / Admin';
    public string $adminFeeType = 'FIXED';
    public int|float|string|null $adminFeeAmount = 2500.00;
    public string $adminFeeChannels = 'ONLINE_ONLY';

    // Pajak (PPh / PPN / PB1)
    public bool $isTaxEnabled = false;
    public string $taxName = 'PB1 Pajak Daerah / PPh';
    public string $taxType = 'PERCENTAGE';
    public int|float|string|null $taxRate = 10.00;
    public string $taxChannels = 'ALL';

    // Simulator Nominal
    public int|float|string|null $simulationSubtotal = 300000.00;

    public function mount(): void
    {
        $settings = ClubFinanceSetting::getSettings();

        $this->isAdminFeeEnabled = (bool) $settings->is_admin_fee_enabled;
        $this->adminFeeName = (string) ($settings->admin_fee_name ?: 'Biaya Layanan / Admin');
        $this->adminFeeType = (string) ($settings->admin_fee_type ?: 'FIXED');
        $this->adminFeeAmount = (float) $settings->admin_fee_amount;
        $this->adminFeeChannels = (string) ($settings->admin_fee_channels ?: 'ONLINE_ONLY');

        $this->isTaxEnabled = (bool) $settings->is_tax_enabled;
        $this->taxName = (string) ($settings->tax_name ?: 'PB1 Pajak Daerah / PPh');
        $this->taxType = (string) ($settings->tax_type ?: 'PERCENTAGE');
        $this->taxRate = (float) $settings->tax_rate;
        $this->taxChannels = (string) ($settings->tax_channels ?: 'ALL');
    }

    public function toggleTax(): void
    {
        $this->isTaxEnabled = ! $this->isTaxEnabled;
    }

    public function toggleAdminFee(): void
    {
        $this->isAdminFeeEnabled = ! $this->isAdminFeeEnabled;
    }

    public function setTaxType(string $type): void
    {
        $this->taxType = in_array($type, ['PERCENTAGE', 'FIXED']) ? $type : 'PERCENTAGE';
    }

    public function setAdminFeeType(string $type): void
    {
        $this->adminFeeType = in_array($type, ['FIXED', 'PERCENTAGE']) ? $type : 'FIXED';
    }

    public function saveSettings(): void
    {
        abort_unless(
            auth()->user() && (auth()->user()->hasAnyRole(['super_admin', 'admin']) || auth()->user()->can('manage_tax_and_fees')),
            403,
            'Akses ditolak: Anda tidak memiliki izin [manage_tax_and_fees] untuk memperbarui pengaturan finansial.'
        );

        $settings = ClubFinanceSetting::find(1) ?? new ClubFinanceSetting(['id' => 1]);

        $settings->is_admin_fee_enabled = $this->isAdminFeeEnabled;
        $settings->admin_fee_name = trim($this->adminFeeName) ?: 'Biaya Layanan / Admin';
        $settings->admin_fee_type = in_array($this->adminFeeType, ['FIXED', 'PERCENTAGE']) ? $this->adminFeeType : 'FIXED';
        $settings->admin_fee_amount = max(0, (float) ($this->adminFeeAmount ?: 0));
        $settings->admin_fee_channels = in_array($this->adminFeeChannels, ['ONLINE_ONLY', 'POS_ONLY', 'ALL']) ? $this->adminFeeChannels : 'ONLINE_ONLY';

        $settings->is_tax_enabled = $this->isTaxEnabled;
        $settings->tax_name = trim($this->taxName) ?: 'PB1 Pajak Daerah / PPh';
        $settings->tax_type = in_array($this->taxType, ['PERCENTAGE', 'FIXED']) ? $this->taxType : 'PERCENTAGE';
        $settings->tax_rate = max(0, (float) ($this->taxRate ?: 0));
        $settings->tax_channels = in_array($this->taxChannels, ['ALL', 'ONLINE_ONLY', 'POS_ONLY']) ? $this->taxChannels : 'ALL';

        $settings->save();

        Notification::make()
            ->title('Pengaturan Finansial Berhasil Disimpan')
            ->body('Konfigurasi pajak dan biaya admin telah aktif dan diperbarui ke seluruh kanal checkout.')
            ->success()
            ->send();
    }

    public function setSimulationSubtotal(float $val): void
    {
        $this->simulationSubtotal = max(0, $val);
    }

    public function getSimulationResultProperty(): array
    {
        $subtotal = max(0, (float) ($this->simulationSubtotal ?: 0));
        $taxRate = max(0, (float) ($this->taxRate ?: 0));
        $adminFee = max(0, (float) ($this->adminFeeAmount ?: 0));

        // Simulasi Online
        $onlineTax = 0;
        if ($this->isTaxEnabled && in_array($this->taxChannels, ['ALL', 'ONLINE_ONLY'])) {
            $onlineTax = $this->taxType === 'PERCENTAGE'
                ? (int) round(($subtotal * $taxRate) / 100)
                : (int) round($taxRate);
        }

        $onlineAdmin = 0;
        if ($this->isAdminFeeEnabled && in_array($this->adminFeeChannels, ['ALL', 'ONLINE_ONLY'])) {
            $onlineAdmin = $this->adminFeeType === 'PERCENTAGE'
                ? (int) round(($subtotal * $adminFee) / 100)
                : (int) round($adminFee);
        }

        // Simulasi Kasir POS Walk-in
        $posTax = 0;
        if ($this->isTaxEnabled && in_array($this->taxChannels, ['ALL', 'POS_ONLY'])) {
            $posTax = $this->taxType === 'PERCENTAGE'
                ? (int) round(($subtotal * $taxRate) / 100)
                : (int) round($taxRate);
        }

        $posAdmin = 0;
        if ($this->isAdminFeeEnabled && in_array($this->adminFeeChannels, ['ALL', 'POS_ONLY'])) {
            $posAdmin = $this->adminFeeType === 'PERCENTAGE'
                ? (int) round(($subtotal * $adminFee) / 100)
                : (int) round($adminFee);
        }

        return [
            'subtotal' => $subtotal,
            'online' => [
                'tax' => $onlineTax,
                'admin' => $onlineAdmin,
                'grand_total' => $subtotal + $onlineTax + $onlineAdmin,
            ],
            'pos' => [
                'tax' => $posTax,
                'admin' => $posAdmin,
                'grand_total' => $subtotal + $posTax + $posAdmin,
            ],
        ];
    }
}
