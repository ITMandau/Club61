{{--
    Export PDF Pengeluaran F&B (Modul 29) — sesuai filter halaman (periode, kategori, status). Dirender dompdf (tabel &
    inline style). Total & rekap per kategori hanya menghitung catatan aktif. Data: FnbReportExport::expensesPdf().
--}}
@php
    $rp = fn ($v) => \App\Filament\Pages\BukuTransaksi::rupiah($v);
    $logoPath = public_path('images/club61-logo.png');
    $logo = is_file($logoPath) && filesize($logoPath) < 400000 ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath)) : null;
@endphp
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Pengeluaran F&amp;B</title>
    <style>
        @page { margin: 24px 28px 36px 28px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5px; color: #1F170D; }
        h1 { font-size: 15px; margin: 0; }
        .muted { color: #7A643E; }
        table { width: 100%; border-collapse: collapse; }
        .cards td { border: 1px solid #E2D3AE; padding: 7px 8px; width: 33%; vertical-align: top; }
        .cards .lbl { font-size: 7px; font-weight: bold; text-transform: uppercase; color: #8C754E; letter-spacing: 0.5px; }
        .cards .val { font-size: 12.5px; font-weight: bold; margin-top: 2px; }
        .grid th { background: #F5E5BE; color: #5C410F; font-size: 7.5px; text-align: left; padding: 4px 5px; border-bottom: 1px solid #D9BE84; }
        .grid td { padding: 3.5px 5px; border-bottom: 1px solid #EFE4C8; vertical-align: top; }
        .grid tr.total td { font-weight: bold; border-top: 1.5px solid #D9BE84; background: #FAF2DE; }
        .grid tr.voided td { color: #9A8A84; }
        .num, .grid th.num { text-align: right; white-space: nowrap; }
        .section td { font-size: 9px; font-weight: bold; color: #5C410F; padding: 14px 0 5px 0; text-transform: uppercase; letter-spacing: 0.6px; }
        .footer { position: fixed; bottom: -24px; left: 0; right: 0; font-size: 7px; color: #8C754E; }
        .pagenum:before { content: counter(page); }
    </style>
</head>
<body>
    <div class="footer">
        Club 61 · Pengeluaran F&amp;B · dicetak {{ $printedAt }} oleh {{ $printedBy ?? '-' }} · halaman <span class="pagenum"></span>
    </div>

    <table>
        <tr>
            <td style="width:60%;">
                @if ($logo)
                    <img src="{{ $logo }}" style="height:34px; vertical-align:middle; margin-right:8px;">
                @endif
                <span style="vertical-align:middle;"><h1 style="display:inline;">Laporan Pengeluaran F&amp;B</h1></span>
                <div class="muted" style="margin-top:3px;">Club 61 Padel &amp; Wellness</div>
            </td>
            <td style="width:40%; text-align:right;" class="muted">
                <div style="font-size:10px; font-weight:bold; color:#1F170D;">{{ $periodLabel }}</div>
                <div>Filter status: {{ $statusLabel }}</div>
                <div>Total hanya menghitung catatan aktif</div>
            </td>
        </tr>
    </table>

    <table class="cards" style="margin-top:10px;">
        <tr>
            <td style="background:#FAF2DE;"><div class="lbl">Total Pengeluaran</div><div class="val">{{ $rp($totals['all']) }}</div></td>
            <td><div class="lbl">Pembelian Persediaan &amp; Bahan</div><div class="val">{{ $rp($totals['stock']) }}</div><div class="muted">Pada Laba telah diwakili HPP</div></td>
            <td><div class="lbl">Biaya Operasional</div><div class="val">{{ $rp($totals['operational']) }}</div><div class="muted">Gas, listrik, gaji, perlengkapan, dst.</div></td>
        </tr>
    </table>

    <table class="section"><tr><td>Rekap per kategori</td></tr></table>
    <table class="grid" style="width:75%;">
        <thead><tr><th style="width:40%;">Kategori</th><th style="width:30%;">Jenis</th><th class="num" style="width:12%;">Catatan</th><th class="num" style="width:18%;">Total</th></tr></thead>
        <tbody>
            @forelse ($byCategory as $c)
                <tr><td>{{ $c['name'] }}</td><td class="muted">{{ $c['type'] }}</td><td class="num">{{ $c['count'] }}</td><td class="num">{{ $rp($c['total']) }}</td></tr>
            @empty
                <tr><td colspan="4" class="muted">Tidak ada pengeluaran aktif.</td></tr>
            @endforelse
            @if (count($byCategory))
                <tr class="total"><td colspan="3">Total</td><td class="num">{{ $rp($totals['all']) }}</td></tr>
            @endif
        </tbody>
    </table>

    <table class="section"><tr><td>Rincian pengeluaran</td></tr></table>
    <table class="grid">
        <thead><tr>
            <th style="width:10%;">Tanggal</th><th style="width:18%;">Kategori</th><th style="width:38%;">Keterangan</th>
            <th class="num" style="width:13%;">Nominal</th><th style="width:21%;">Dicatat Oleh</th>
        </tr></thead>
        <tbody>
            @forelse ($rows as $e)
                <tr class="{{ $e->voided_at ? 'voided' : '' }}">
                    <td>{{ $e->entry_date->format('d/m/Y') }}</td>
                    <td>{{ $e->category?->name }}</td>
                    <td>
                        {{ $e->notes ?: '-' }}
                        @if ($e->voided_at)
                            <div>Dibatalkan: {{ $e->void_reason }}</div>
                        @endif
                    </td>
                    <td class="num">{{ $e->voided_at ? '('.$rp($e->amount).')' : $rp($e->amount) }}</td>
                    <td>{{ $e->creator?->name ?? '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">Tidak ada catatan pada filter ini.</td></tr>
            @endforelse
        </tbody>
    </table>
    @if ($totals['voided'] > 0)
        <div class="muted" style="margin-top:4px;">{{ $totals['voided'] }} catatan dibatalkan ditampilkan abu-abu dengan nominal dalam kurung, tidak dihitung pada total.</div>
    @endif
</body>
</html>
