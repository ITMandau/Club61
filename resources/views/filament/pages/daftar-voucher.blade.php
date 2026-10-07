<x-filament-panels::page>
    @php $rp = fn ($v) => \App\Filament\Pages\BukuTransaksi::rupiah($v); @endphp

    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 200px), 1fr)); gap:0.75rem;">
        <div style="padding:0.85rem 1rem; background:#FFFDF7; border:2px solid #D4AF37; border-radius:14px;">
            <div style="font-size:0.7rem; font-weight:800; color:#8C6418; text-transform:uppercase; letter-spacing:0.04em;">Sisa saldo voucher aktif</div>
            <div style="font-size:1.35rem; font-weight:900; color:#8C6418;">{{ $rp($summary['outstanding']) }}</div>
            <div style="font-size:0.72rem; color:#7A643E;">{{ $summary['active_count'] }} voucher &middot; uang customer yang masih disimpan klub</div>
        </div>
        <div style="padding:0.85rem 1rem; background:#FFFFFF; border:1.5px solid #DFC387; border-radius:14px;">
            <div style="font-size:0.7rem; font-weight:800; color:#8C6418; text-transform:uppercase; letter-spacing:0.04em;">Total voucher saldo terbit</div>
            <div style="font-size:1.35rem; font-weight:900; color:#1F170D;">{{ $rp($summary['issued_amount']) }}</div>
            <div style="font-size:0.72rem; color:#7A643E;">{{ $summary['issued_count'] }} voucher dari refund yang ditolak</div>
        </div>
        <div style="padding:0.85rem 1rem; background:#FFFFFF; border:1.5px solid #DFC387; border-radius:14px;">
            <div style="font-size:0.7rem; font-weight:800; color:#8C6418; text-transform:uppercase; letter-spacing:0.04em;">Sudah dipakai customer</div>
            <div style="font-size:1.35rem; font-weight:900; color:#047857;">{{ $rp($summary['used_amount']) }}</div>
            <div style="font-size:0.72rem; color:#7A643E;">Jadi potongan booking (bukan uang masuk baru)</div>
        </div>
        <div style="padding:0.85rem 1rem; background:#FFFFFF; border:1.5px solid #DFC387; border-radius:14px;">
            <div style="font-size:0.7rem; font-weight:800; color:#8C6418; text-transform:uppercase; letter-spacing:0.04em;">Saldo hangus (kedaluwarsa)</div>
            <div style="font-size:1.35rem; font-weight:900; color:#B45309;">{{ $rp($summary['expired_balance']) }}</div>
            <div style="font-size:0.72rem; color:#7A643E;">Tidak dipakai sampai masa berlaku habis</div>
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>