<x-filament-panels::page>
    @php
        $s = $this->summary();
        $rp = fn ($v) => \App\Filament\Pages\BukuTransaksi::rupiah($v);
        $canRefundQueue = auth()->user()?->can('process_refund_queue');
    @endphp

    <style>
        .ledger-cards { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:0.75rem; }
        .ledger-card { background:#FFFFFF; border:1.5px solid #E9DCBC; border-radius:14px; padding:0.85rem 1rem; }
        .ledger-card.main { background:linear-gradient(135deg, #FAF2DE 0%, #F5E5BE 100%); border-color:#D9BE84; }
        .ledger-card .lbl { font-size:0.68rem; font-weight:800; text-transform:uppercase; letter-spacing:0.08em; color:#8C754E; }
        .ledger-card .val { font-size:1.15rem; font-weight:900; color:#1F170D; margin-top:0.2rem; font-variant-numeric:tabular-nums; }
        .ledger-card .sub { font-size:0.7rem; color:#7A643E; margin-top:0.15rem; }
        .ledger-info { display:flex; flex-wrap:wrap; gap:0.5rem 1.25rem; font-size:0.75rem; color:#5C410F; padding:0.6rem 1rem; background:#FFFDF7; border:1.5px dashed #DFC387; border-radius:12px; }
        .ledger-info b { color:#1F170D; }
    </style>

    <div style="display:flex; flex-direction:column; gap:0.75rem;">
        <div style="font-size:0.78rem; color:#7A643E;">
            {{ $s['period_label'] }} · dihitung dari <b>tanggal uang diterima</b> (WIB), <b>setelah refund</b>, mengikuti filter tabel.
            {{ number_format($s['payments_count'], 0, ',', '.') }} pembayaran (uang diterima {{ $rp($s['money_in']) }}).
            Pendapatan = penjualan bersih <b>sebelum pajak</b>; pajak adalah titipan, bukan pendapatan.
            Tarif / pajak / biaya layanan yang diubah belakangan <b>tidak</b> mengubah transaksi lama.
        </div>

        <div class="ledger-cards">
            <div class="ledger-card">
                <div class="lbl">Penjualan Bersih</div>
                <div class="val">{{ $rp($s['net']) }}</div>
                <div class="sub">Harga item {{ $rp($s['gross']) }} − diskon {{ $rp($s['discount']) }}</div>
            </div>
            <div class="ledger-card">
                <div class="lbl">Biaya Layanan</div>
                <div class="val">{{ $rp($s['service']) }}</div>
                <div class="sub">Ditampilkan terpisah</div>
            </div>
            <div class="ledger-card">
                <div class="lbl">Pajak Terkumpul</div>
                <div class="val">{{ $rp($s['tax']) }}</div>
                <div class="sub">Disetor, bukan pendapatan</div>
            </div>
            <div class="ledger-card">
                <div class="lbl">Refund</div>
                <div class="val" style="color:#B42318;">{{ $rp($s['refunds']) }}</div>
                <div class="sub">{{ number_format($s['refunds_count'], 0, ',', '.') }} refund · sudah dikurangkan</div>
            </div>
            <div class="ledger-card main">
                <div class="lbl">Total Uang Masuk (Bersih)</div>
                <div class="val">{{ $rp($s['money_net']) }}</div>
                <div class="sub">= Bersih + Layanan + Pajak</div>
            </div>
        </div>

        <div class="ledger-info">
            <span>Nilai kuota member / voucher sponsor terpakai: <b>{{ $rp($s['benefit']) }}</b> (non-tunai)</span>
            <span>Selisih reschedule hangus: <b>{{ $rp($s['forfeited']) }}</b> (sudah tercatat saat bayar awal)</span>
            @if ($s['overpayments'] > 0)
                <span>Kelebihan bayar masuk: <b>{{ $rp($s['overpayments']) }}</b></span>
            @endif
            <span>
                Refund menunggu diproses: <b>{{ $s['pending_refund_count'] }} ({{ $rp($s['pending_refund_amount']) }})</b>
                @if ($canRefundQueue && $s['pending_refund_count'] > 0)
                    · <a href="{{ \App\Filament\Pages\AntrianRefund::getUrl() }}" style="color:#8C6418; font-weight:800; text-decoration:underline;">Buka Antrian Refund</a>
                @endif
            </span>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
