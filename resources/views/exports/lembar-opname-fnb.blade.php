{{--
    Lembar Stok Opname F&B (Modul 29) — dicetak lalu dibawa ke gudang / chiller. Kolom fisik & catatan sengaja kosong dan
    stok sistem tidak ditampilkan (hitung buta), hasilnya diketik di Stok F&B → Lainnya → Stok Opname.
    Data: FnbReportExport::opnameSheet(). Dirender dompdf (tabel & inline style).
--}}
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Lembar Stok Opname F&amp;B</title>
    <style>
        @page { margin: 26px 30px 38px 30px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9.5px; color: #1F170D; }
        h1 { font-size: 15px; margin: 0; }
        .muted { color: #7A643E; }
        table { width: 100%; border-collapse: collapse; }
        .grid th { background: #F5E5BE; color: #5C410F; font-size: 8px; text-align: left; padding: 5px 6px; border: 1px solid #D9BE84; }
        .grid td { padding: 9px 6px; border: 1px solid #E2D3AE; vertical-align: middle; }
        .sign td { padding-top: 34px; text-align: center; width: 33%; }
        .footer { position: fixed; bottom: -26px; left: 0; right: 0; font-size: 7px; color: #8C754E; }
        .pagenum:before { content: counter(page); }
    </style>
</head>
<body>
    <div class="footer">Club 61 · Lembar Stok Opname F&amp;B · dicetak {{ $printedAt }} oleh {{ $printedBy ?? '-' }} · halaman <span class="pagenum"></span></div>

    <table>
        <tr>
            <td style="width:60%;">
                <h1>Lembar Stok Opname F&amp;B</h1>
                <div class="muted" style="margin-top:3px;">Club 61 Padel &amp; Wellness · {{ $menus->count() }} menu dengan persediaan dilacak</div>
            </td>
            <td style="width:40%; text-align:right;">
                <div>Tanggal: ____________________</div>
                <div style="margin-top:6px;">Jam mulai / selesai: ________ / ________</div>
            </td>
        </tr>
    </table>
    <div class="muted" style="margin-top:6px;">Hitung jumlah fisik setiap menu, tulis di kolom Jumlah Fisik, lalu masukkan hasilnya di Stok F&amp;B → Lainnya → Stok Opname.</div>

    <table class="grid" style="margin-top:8px;">
        <thead><tr>
            <th style="width:6%;">No</th><th style="width:38%;">Menu</th><th style="width:18%;">Kategori</th>
            <th style="width:14%;">Jumlah Fisik</th><th style="width:24%;">Catatan</th>
        </tr></thead>
        <tbody>
            @forelse ($menus as $i => $menu)
                <tr><td>{{ $i + 1 }}</td><td>{{ $menu->name }}</td><td class="muted">{{ $menu->category?->name }}</td><td></td><td></td></tr>
            @empty
                <tr><td colspan="5" class="muted">Belum ada menu dengan persediaan dilacak.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="sign">
        <tr>
            <td>Dihitung oleh<br><br><br>( ____________________ )</td>
            <td>Diperiksa oleh<br><br><br>( ____________________ )</td>
            <td>Disetujui oleh<br><br><br>( ____________________ )</td>
        </tr>
    </table>
</body>
</html>
