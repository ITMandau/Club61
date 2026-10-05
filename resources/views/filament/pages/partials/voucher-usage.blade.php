@php $rp = fn ($v) => \App\Filament\Pages\BukuTransaksi::rupiah($v); @endphp
<div style="font-size:0.8rem; color:#1F170D;">
    <div style="display:flex; flex-wrap:wrap; gap:1rem; margin-bottom:0.75rem; color:#5C410F;">
        <span>Pemilik: <b>{{ $voucher->user?->name ?? 'Semua customer' }}</b></span>
        @if ($voucher->isCredit())
            <span>Nilai awal: <b>{{ $rp($voucher->discount_value) }}</b></span>
            <span>Sisa: <b>{{ $rp($voucher->balance) }}</b></span>
            @if ($available < (float) $voucher->balance)
                <span style="color:#B45309;">Sedang dipesan order belum dibayar: <b>{{ $rp((float) $voucher->balance - $available) }}</b></span>
            @endif
        @endif
    </div>

    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="text-align:left; color:#8C6418; font-size:0.7rem; text-transform:uppercase;">
                <th style="padding:0.4rem; border-bottom:1.5px solid #DFC387;">Waktu</th>
                <th style="padding:0.4rem; border-bottom:1.5px solid #DFC387;">No. Order</th>
                <th style="padding:0.4rem; border-bottom:1.5px solid #DFC387;">Customer</th>
                <th style="padding:0.4rem; border-bottom:1.5px solid #DFC387;">Status</th>
                <th style="padding:0.4rem; border-bottom:1.5px solid #DFC387; text-align:right;">Potongan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td style="padding:0.4rem; border-bottom:1px solid #FAF2DE; white-space:nowrap; color:#6B7280;">{{ $order->created_at?->timezone(\App\Services\Finance\LedgerReport::TIMEZONE)->format('d M Y H:i') }}</td>
                    <td style="padding:0.4rem; border-bottom:1px solid #FAF2DE; font-family:monospace; font-weight:700;">{{ $order->order_number }}</td>
                    <td style="padding:0.4rem; border-bottom:1px solid #FAF2DE;">{{ $order->user?->name ?? '-' }}</td>
                    <td style="padding:0.4rem; border-bottom:1px solid #FAF2DE;">{{ $order->payment_status }}</td>
                    <td style="padding:0.4rem; border-bottom:1px solid #FAF2DE; text-align:right; font-weight:800;">{{ $rp($order->discount_amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="padding:1rem; text-align:center; color:#8C7A58;">Voucher ini belum pernah dipakai.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>