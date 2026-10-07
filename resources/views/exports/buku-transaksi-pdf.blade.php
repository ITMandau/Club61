{{--
    Export PDF Buku Transaksi (Modul 17 FR-05) — dirender dompdf (CSS terbatas: tabel & inline style, tanpa flex/grid).
    Data: LedgerExportController::pdf(). Nomor HP & email customer tidak ditampilkan.
--}}
@php
    use App\Models\Finance\LedgerEntry;
    use App\Services\Finance\LedgerReport;
    $rp = fn ($v) => \App\Filament\Pages\BukuTransaksi::rupiah($v);
    $logoPath = public_path('images/club61-logo.png');
    $logo = is_file($logoPath) && filesize($logoPath) < 400000 ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Buku Transaksi</title>
    <style>
        @page { margin: 22px 26px 34px 26px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1F170D; }
        h1 { font-size: 15px; margin: 0; }
        .muted { color: #7A643E; }
        table { width: 100%; border-collapse: collapse; }
        .cards td { border: 1px solid #E2D3AE; padding: 6px 8px; width: 20%; vertical-align: top; }
        .cards .lbl { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #8C754E; letter-spacing: 0.5px; }
        .cards .val { font-size: 12px; font-weight: bold; margin-top: 2px; }
        .grid th { background: #F5E5BE; color: #5C410F; font-size: 7.5px; text-align: left; padding: 4px 5px; border-bottom: 1px solid #D9BE84; }
        .grid td { padding: 3.5px 5px; border-bottom: 1px solid #EFE4C8; vertical-align: top; }
        .grid tr:nth-child(even) td { background: #FFFCF4; }
        .num, .grid th.num { text-align: right; white-space: nowrap; }
        .recap { width: 70%; }
        .neg { color: #B42318; }
        /* Tabel sebagai judul seksi: div bermargin sebelum/sesudah tabel tidak dihitung benar oleh dompdf (judul tertimpa tabel). */
        .section td { font-size: 9px; font-weight: bold; color: #5C410F; padding: 14px 0 5px 0; text-transform: uppercase; letter-spacing: 0.6px; }
        .footer { position: fixed; bottom: -22px; left: 0; right: 0; font-size: 7px; color: #8C754E; }
        .pagenum:before { content: counter(page); }
    </style>
</head>
<body>
    <div class="footer">
        Club 61 · Buku Transaksi · dicetak {{ $printedAt }} oleh {{ $printedBy ?? '-' }} · halaman <span class="pagenum"></span>
    </div>

    <table>
        <tr>
            <td style="width:60%;">
                @if ($logo)
                    <img src="{{ $logo }}" style="height:34px; vertical-align:middle; margin-right:8px;">
                @endif
                <span style="vertical-align:middle;"><h1 style="display:inline;">Buku Transaksi Terpadu</h1></span>
                <div class="muted" style="margin-top:3px;">Club 61 Padel Court{{ $company?->address_line ? ' · '.$company->address_line : '' }}</div>
            </td>
            <td style="width:40%; text-align:right;" class="muted">
                <div style="font-size:10px; font-weight:bold; color:#1F170D;">{{ $periodLabel }}</div>
                <div>Tanggal = waktu uang diterima (WIB) · angka setelah refund</div>
                <div>Pendapatan = penjualan bersih sebelum pajak</div>
            </td>
        </tr>
    </table>

    <table class="cards" style="margin-top:10px;">
        <tr>
            <td><div class="lbl">Penjualan Bersih</div><div class="val">{{ $rp($summary['net']) }}</div><div class="muted">Harga item {{ $rp($summary['gross']) }} − diskon {{ $rp($summary['discount']) }}</div></td>
            <td><div class="lbl">Biaya Layanan</div><div class="val">{{ $rp($summary['service']) }}</div></td>
            <td><div class="lbl">Pajak Terkumpul</div><div class="val">{{ $rp($summary['tax']) }}</div><div class="muted">Bukan pendapatan</div></td>
            <td><div class="lbl">Refund</div><div class="val neg">{{ $rp($summary['refunds']) }}</div><div class="muted">{{ $summary['refunds_count'] }} refund · sudah dikurangkan</div></td>
            <td style="background:#FAF2DE;"><div class="lbl">Total Uang Masuk (Bersih)</div><div class="val">{{ $rp($summary['money_net']) }}</div><div class="muted">= Bersih + Layanan + Pajak</div></td>
        </tr>
    </table>
    <div class="muted" style="margin-top:4px;">
        {{ $summary['payments_count'] }} pembayaran (uang diterima {{ $rp($summary['money_in']) }}) ·
        Kuota member / voucher terpakai (non-tunai): {{ $rp($summary['benefit']) }} ·
        Selisih reschedule hangus: {{ $rp($summary['forfeited']) }} ·
        Refund menunggu diproses: {{ $summary['pending_refund_count'] }} ({{ $rp($summary['pending_refund_amount']) }})
    </div>

    <table class="section"><tr><td>Rekap per kategori</td></tr></table>
    <table class="grid recap">
        <thead><tr>
            <th style="width:28%;">Kategori</th><th class="num" style="width:12%;">Pembayaran</th><th class="num" style="width:17%;">Penjualan Bersih</th>
            <th class="num" style="width:14%;">Biaya Layanan</th><th class="num" style="width:12%;">Pajak</th><th class="num" style="width:17%;">Total (setelah refund)</th>
        </tr></thead>
        <tbody>
            @forelse ($byCategory as $c)
                <tr>
                    <td>{{ LedgerEntry::categoryLabel($c->category) }}</td>
                    <td class="num">{{ $c->payments }}</td>
                    <td class="num">{{ $rp($c->net) }}</td>
                    <td class="num">{{ $rp($c->service) }}</td>
                    <td class="num">{{ $rp($c->tax) }}</td>
                    <td class="num" style="font-weight:bold;">{{ $rp($c->total) }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="muted">Tidak ada transaksi.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="section"><tr><td>Daftar transaksi</td></tr></table>
    @if ($truncated)
        <div class="neg" style="margin-bottom:4px;">
            Hanya {{ number_format($rows->count(), 0, ',', '.') }} dari {{ number_format($total, 0, ',', '.') }} transaksi terbaru yang ditampilkan (batas PDF {{ number_format($limit, 0, ',', '.') }} baris).
            Ringkasan di atas tetap menghitung semuanya — gunakan export Excel untuk daftar lengkap.
        </div>
    @endif
    <table class="grid">
        <thead><tr>
            <th>Waktu</th><th>No. Order</th><th>Sumber</th><th>Kategori</th><th>Customer</th><th>Kasir</th><th>Metode</th>
            <th class="num">Bersih</th><th class="num">Layanan</th><th class="num">Pajak</th><th class="num">Total</th><th>Status</th>
        </tr></thead>
        <tbody>
            @forelse ($rows as $r)
                <tr>
                    <td style="white-space:nowrap;">{{ $r->occurred_at?->timezone(LedgerReport::TIMEZONE)->format('d/m/y H:i') }}</td>
                    <td style="font-family:DejaVu Sans Mono, monospace;">{{ $r->order_number }}</td>
                    <td>{{ LedgerEntry::sourceLabel($r->source) }}</td>
                    <td>{{ LedgerReport::categoriesLabel($r->categories) }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($r->customer_name ?? '-', 22) }}</td>
                    <td>{{ LedgerReport::cashierLabel($r) }}</td>
                    <td>{{ \Illuminate\Support\Str::limit($r->payment_method_label ?? $r->payment_method ?? '-', 26) }}</td>
                    <td class="num">{{ $rp($r->net_amount) }}</td>
                    <td class="num">{{ $rp($r->service_amount) }}</td>
                    <td class="num">{{ $rp($r->tax_amount) }}</td>
                    <td class="num {{ (float) $r->total_amount < 0 ? 'neg' : '' }}" style="font-weight:bold;">{{ $rp($r->total_amount) }}</td>
                    <td>{{ LedgerReport::statusLabel($r) }}</td>
                </tr>
            @empty
                <tr><td colspan="12" class="muted">Tidak ada transaksi pada filter ini.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
