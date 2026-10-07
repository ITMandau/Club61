{{--
    Struk Aktivasi Membership — SATU sumber untuk modal struk, tombol Cetak Struk, dan cetak otomatis Bayar Otomatis
    (App\Livewire\Concerns\AutoPrintsReceipts). Param: $receipt (JualMembership::buildMembershipReceipt()),
    $withActions (tombol Cetak Struk / Transaksi Baru — hanya di modal), $receiptFromHistory.
--}}
<div id="printable-membership-receipt"
    style="background: #FFFFFF; border: 2px solid #D4AF37; border-radius: 20px; width: 100%; max-width: 440px; padding: 1.5rem; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
    <div
        style="text-align: center; border-bottom: 1.5px dashed #DFC387; padding-bottom: 1rem; margin-bottom: 1rem;">
        <div
            style="font-size: 0.6875rem; font-weight: 800; color: #8C6418; letter-spacing: 0.1em; text-transform: uppercase;">
            Struk Aktivasi Membership</div>
        <div
            style="font-size: 1.375rem; font-weight: 900; color: #1F170D; font-family: serif; margin-top: 0.25rem;">
            CLUB 61 MEDAN</div>
        <div style="font-size: 0.75rem; color: #665033; margin-top: 0.25rem;">Nomor:
            {{ $receipt['membership_code'] }}</div>
    </div>

    <div
        style="display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.75rem; color: #1F170D; margin-bottom: 1rem;">
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #7A643E;">No. Order</span>
            <strong style="font-family: monospace;">{{ $receipt['order_number'] }}</strong>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #7A643E;">Waktu</span>
            <span>{{ $receipt['created_at'] ?? '-' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #7A643E;">Kasir</span>
            <span>{{ $receipt['cashier_name'] ?? '-' }}</span>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #7A643E;">Metode</span>
            <span>{{ $receipt['payment_method'] ?? '-' }}</span>
        </div>
        @php $pm = $receipt['payment_meta'] ?? []; @endphp
        @if (! empty($pm['card_last_4']))
            <div style="font-size: 0.6875rem; color: #665033; text-align: right;">
                **** {{ $pm['card_last_4'] }} &bull; Appr {{ $pm['approval_code'] ?? '-' }} &bull; Trace {{ $pm['trace_number'] ?? '-' }}
            </div>
        @elseif (! empty($pm['rrn']) || ! empty($pm['qris_rrn']))
            <div style="font-size: 0.6875rem; color: #665033; text-align: right;">RRN {{ $pm['rrn'] ?? $pm['qris_rrn'] }}</div>
        @endif
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #7A643E;">Member</span>
            <strong>{{ $receipt['customer_name'] }}</strong>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #7A643E;">Paket</span>
            <strong>{{ $receipt['plan_name'] }}</strong>
        </div>
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #7A643E;">Masa Aktif</span>
            <span>{{ $receipt['start_date'] }} s/d
                {{ $receipt['end_date'] }}</span>
        </div>
        @if (isset($receipt['subtotal']))
            <div style="display: flex; justify-content: space-between;">
                <span style="color: #7A643E;">Subtotal</span>
                <span>Rp {{ number_format($receipt['subtotal'], 0, ',', '.') }}</span>
            </div>
        @endif
        @if (($receipt['tax_amount'] ?? 0) > 0)
            <div style="display: flex; justify-content: space-between;">
                <span style="color: #7A643E;">{{ $receipt['tax_name'] ?: 'Pajak' }}</span>
                <span>Rp {{ number_format($receipt['tax_amount'], 0, ',', '.') }}</span>
            </div>
        @endif
        @if (($receipt['service_charge'] ?? 0) > 0)
            <div style="display: flex; justify-content: space-between;">
                <span style="color: #7A643E;">{{ $receipt['admin_fee_name'] ?: 'Biaya Layanan' }}</span>
                <span>Rp {{ number_format($receipt['service_charge'], 0, ',', '.') }}</span>
            </div>
        @endif
        <div style="display: flex; justify-content: space-between;">
            <span style="color: #7A643E;">Total Bayar</span>
            <strong>Rp {{ number_format($receipt['grand_total'], 0, ',', '.') }}</strong>
        </div>
    </div>

    <!-- Saldo Kuota Aktif -->
    <div
        style="background: #FAF5E8; border: 1px solid #DFC387; border-radius: 8px; padding: 0.75rem; margin-bottom: 1.25rem;">
        <div
            style="font-size: 0.6875rem; font-weight: 800; color: #7A5818; margin-bottom: 0.35rem; text-transform: uppercase;">
            Saldo Kuota Terisi (Top-Up)</div>
        @foreach ($receipt['balances'] as $b)
            <div
                style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #1F170D; padding: 0.15rem 0;">
                <span>{{ $b['facility'] }}</span>
                <strong>
                    @if ($b['quota_type'] === 'HOURS')
                        {{ (float) $b['remaining_quota'] }} Jam
                    @elseif($b['quota_type'] === 'VISITS')
                        {{ (float) $b['remaining_quota'] }} Sesi
                    @else
                        Diskon {{ $b['discount_percent'] }}%
                    @endif
                </strong>
            </div>
        @endforeach
    </div>

    @if (! empty($receipt['is_reprint']))
        <div style="text-align: center; font-size: 0.6875rem; font-weight: 900; color: #1F170D; margin-bottom: 0.75rem;">*** CETAK ULANG {{ $receipt['reprinted_at'] }} ***</div>
    @endif

    @if ($withActions ?? false)
    <div class="no-print" style="display: flex; gap: 0.5rem;">
        <button type="button" onclick="club61PrintReceipt('#printable-membership-receipt')"
            style="flex: 1; background: #FAF5E8; border: 1.5px solid #DFC387; color: #7A5818; padding: 0.6rem; border-radius: 8px; font-weight: 800; font-size: 0.75rem; cursor: pointer;">
            Cetak Struk
        </button>
        <button type="button" wire:click="closeReceipt"
            style="flex: 1; background: #D4AF37; border: none; color: #1F170D; padding: 0.6rem; border-radius: 8px; font-weight: 900; font-size: 0.75rem; cursor: pointer;">
            {{ ($receiptFromHistory ?? false) ? 'Tutup' : 'Selesai & Transaksi Baru' }}
        </button>
    </div>
    @endif
</div>
