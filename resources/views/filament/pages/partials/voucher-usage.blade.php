@php $rp = fn ($v) => \App\Filament\Pages\BukuTransaksi::rupiah($v); @endphp
<div style="font-size:0.8rem; color:#4F2F2A;">
    <div style="display:flex; flex-wrap:wrap; gap:1rem; margin-bottom:0.75rem; color:#4F2F2A;">
        <span>Pemilik: <b>{{ $voucher->user?->name ?? 'Semua customer' }}</b></span>
        @if ($voucher->isCredit())
            <span>Nilai awal: <b>{{ $rp($voucher->discount_value) }}</b></span>
            <span>Sisa: <b>{{ $rp($voucher->balance) }}</b></span>
            @if ($available < (float) $voucher->balance)
                <span style="color:#B45309;">Sedang dipesan order belum dibayar: <b>{{ $rp((float) $voucher->balance - $available) }}</b></span>
            @endif
        @endif
        @if (! empty($campaignStats))
            {{-- Kampanye kode unik (B): kode yang sudah / belum dipakai. Daftar lengkap per kode ada di Unduh CSV. --}}
            <span>Jumlah kode: <b>{{ $campaignStats['total'] }}</b></span>
            <span style="color:#047857;">Sudah dipakai: <b>{{ $campaignStats['used'] }}</b></span>
            @if ($campaignStats['held'] > 0)
                <span style="color:#B45309;">Sedang dipesan: <b>{{ $campaignStats['held'] }}</b></span>
            @endif
            <span>Belum dipakai: <b>{{ $campaignStats['free'] }}</b></span>
        @endif
        @if ($voucher->isClaim())
            <span>Diklaim: <b>{{ $claimCount }} akun</b></span>
            <span style="color:#047857;">Dipakai: <b>{{ (int) $voucher->used_count }}</b></span>
        @endif
    </div>

    <table style="width:100%; border-collapse:collapse;">
        <thead>
            <tr style="text-align:left; color:#662721; font-size:0.7rem; text-transform:uppercase;">
                <th style="padding:0.4rem; border-bottom:1.5px solid #E6DAC0;">Waktu</th>
                <th style="padding:0.4rem; border-bottom:1.5px solid #E6DAC0;">No. Order</th>
                @if (! empty($campaignStats))
                    <th style="padding:0.4rem; border-bottom:1.5px solid #E6DAC0;">Kode</th>
                @endif
                <th style="padding:0.4rem; border-bottom:1.5px solid #E6DAC0;">Customer</th>
                <th style="padding:0.4rem; border-bottom:1.5px solid #E6DAC0;">Status</th>
                <th style="padding:0.4rem; border-bottom:1.5px solid #E6DAC0; text-align:right;">Potongan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($orders as $order)
                <tr>
                    <td style="padding:0.4rem; border-bottom:1px solid #EFE6D2; white-space:nowrap; color:#6B7280;">{{ $order->created_at?->timezone(\App\Services\Finance\LedgerReport::TIMEZONE)->format('d M Y H:i') }}</td>
                    <td style="padding:0.4rem; border-bottom:1px solid #EFE6D2; font-family:monospace; font-weight:700;">{{ $order->order_number }}</td>
                    @if (! empty($campaignStats))
                        <td style="padding:0.4rem; border-bottom:1px solid #EFE6D2; font-family:monospace;">{{ $order->voucher_code }}</td>
                    @endif
                    <td style="padding:0.4rem; border-bottom:1px solid #EFE6D2;">{{ $order->user?->name ?? '-' }}</td>
                    <td style="padding:0.4rem; border-bottom:1px solid #EFE6D2;">{{ $order->payment_status }}</td>
                    <td style="padding:0.4rem; border-bottom:1px solid #EFE6D2; text-align:right; font-weight:800;">{{ $rp($order->discount_amount) }}</td>
                </tr>
            @empty
                <tr><td colspan="{{ empty($campaignStats) ? 5 : 6 }}" style="padding:1rem; text-align:center; color:#7A5A52;">Voucher ini belum pernah dipakai.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>