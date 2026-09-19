<?php

namespace App\Services\Finance;

use App\Models\Pos\ClubFinanceSetting;

class TaxAndFeeService
{
    /**
     * Menghitung rincian pajak (PPh/PPN/PB1) dan biaya layanan/admin
     * dengan presisi bilangan bulat Rupiah murni (Anti-Float Discrepancy).
     *
     * @param float|int $subtotal Nilai kotor pesanan (court + equipment / merchandise / item)
     * @param float|int $discountAmount Nilai potongan voucher diskon
     * @param string $channel Saluran transaksi ('ONLINE', 'POS_WALKIN')
     * @param string $module Modul unit bisnis ('PADEL', 'FNB', 'MERCH', 'WELLNESS', 'SALON', 'GYM')
     * @return array
     */
    public function calculate(
        float|int $subtotal,
        float|int $discountAmount = 0,
        string $channel = 'ONLINE',
        string $module = 'PADEL'
    ): array {
        $settings = ClubFinanceSetting::getSettings();

        // 1. Standarisasi ke integer Rupiah
        $intSubtotal = (int) round($subtotal);
        $intDiscount = (int) round($discountAmount);
        $taxableAmount = max(0, $intSubtotal - $intDiscount);

        // 2. Kalkulasi Pajak
        $taxAmount = 0;
        $isTaxActive = $settings->isTaxApplicable($channel, $module);
        if ($isTaxActive && $taxableAmount > 0) {
            if ($settings->tax_type === 'FIXED') {
                $taxAmount = (int) round($settings->tax_rate);
            } else {
                // Default PERCENTAGE
                $taxAmount = (int) round(($taxableAmount * (float) $settings->tax_rate) / 100);
            }
        }

        // 3. Kalkulasi Biaya Layanan / Admin
        $adminFee = 0;
        $isAdminFeeActive = $settings->isAdminFeeApplicable($channel, $module);
        if ($isAdminFeeActive) {
            if ($settings->admin_fee_type === 'PERCENTAGE') {
                $adminFee = (int) round(($taxableAmount * (float) $settings->admin_fee_amount) / 100);
            } else {
                // Default FIXED
                $adminFee = (int) round($settings->admin_fee_amount);
            }
        }

        // 4. Invarian Mutlak: grand_total adalah penjumlahan eksak integer
        $grandTotal = (int) ($taxableAmount + $taxAmount + $adminFee);

        return [
            'subtotal' => $intSubtotal,
            'discount_amount' => $intDiscount,
            'taxable_amount' => $taxableAmount,
            'tax_enabled' => $isTaxActive,
            'tax_name' => $settings->tax_name,
            'tax_type' => $settings->tax_type,
            'tax_rate' => (float) $settings->tax_rate,
            'tax_amount' => $taxAmount,
            'admin_fee_enabled' => $isAdminFeeActive,
            'admin_fee_name' => $settings->admin_fee_name,
            'admin_fee_type' => $settings->admin_fee_type,
            'admin_fee_rate' => (float) $settings->admin_fee_amount,
            'admin_fee_amount' => $adminFee,
            'grand_total' => $grandTotal,
        ];
    }
}
