<div>
    @php
        $rp = fn ($v) => \App\Filament\Pages\BukuTransaksi::rupiah($v);
        $pct = fn ($part, $whole) => $whole != 0 ? round($part / $whole * 100) : 0;
        $trendMax = max(1, collect($trend)->max(fn ($m) => max($m['sales'], abs($m['net_profit']))));
    @endphp

    <div class="c61 lf-root" style="gap: 1rem;">
        @include('filament.partials.c61-admin-style')
        <style>
            .lf-root .lf-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem; }
            .lf-root .lf-lines { padding: 0.5rem 1.25rem 1rem; }
            .lf-root .lf-line { display: flex; justify-content: space-between; gap: 1rem; padding: 0.55rem 0; border-bottom: 1px dashed var(--c-line-soft); font-size: 0.8125rem; }
            .lf-root .lf-line span:last-child { font-family: var(--font-mono); font-weight: 700; white-space: nowrap; }
            .lf-root .lf-line.is-sub { padding-left: 1rem; color: var(--c-muted); font-size: 0.75rem; }
            .lf-root .lf-line.is-total { border-bottom: none; border-top: 2px solid var(--c-line); font-weight: 800; font-size: 0.9375rem; padding-top: 0.75rem; }
            .lf-root .lf-minus { color: #B42318; }
            .lf-root .lf-plus { color: #047857; }
            .lf-root .lf-bar { display: block; height: 8px; border-radius: 999px; background: var(--c-terra); min-width: 2px; }
            .lf-root .lf-bar.is-profit { background: #047857; }
            .lf-root .lf-bar.is-loss { background: #B42318; }
            .lf-root .lf-trend td { vertical-align: middle; }
            @media (max-width: 1000px) { .lf-root .lf-grid-2 { grid-template-columns: 1fr; } }
        </style>

        <div class="c61-hero">
            <div>
                <div class="c61-eyebrow">F&amp;B · Laporan</div>
                <div class="c61-hero-title">Laporan Keuangan F&amp;B</div>
                <div class="c61-hero-sub">Laba dihitung menggunakan HPP menu pada saat penjualan. Pembelian persediaan &amp; bahan hanya mengurangi Arus Kas dan tidak dipotong kembali pada Laba karena biayanya telah diperhitungkan melalui HPP.</div>
            </div>
            <div class="c61-hero-actions">
                <select wire:model.live="preset" class="c61-select" style="width: auto; background: #FFFFFF;">
                    @foreach (\App\Services\Finance\LedgerReport::PRESETS as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
                @if ($preset === 'kustom')
                    <input type="date" wire:model.live="from" class="c61-input" style="width: auto; background: #FFFFFF;">
                    <input type="date" wire:model.live="until" class="c61-input" style="width: auto; background: #FFFFFF;">
                @endif
                @if (\App\Filament\Pages\LaporanFnb::canExport())
                    <x-filament::dropdown placement="bottom-end" teleport>
                        <x-slot name="trigger">
                            <button type="button" class="c61-btn c61-btn-cream">
                                Unduh Laporan
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2.5" d="m6 9 6 6 6-6"/></svg>
                            </button>
                        </x-slot>
                        <x-filament::dropdown.list>
                            <x-filament::dropdown.list.item icon="heroicon-o-table-cells" wire:click="exportExcel" x-on:click="close()">Excel (lengkap, 5 sheet)</x-filament::dropdown.list.item>
                            <x-filament::dropdown.list.item icon="heroicon-o-document-text" wire:click="exportPdf" x-on:click="close()">PDF (ringkasan siap cetak)</x-filament::dropdown.list.item>
                        </x-filament::dropdown.list>
                    </x-filament::dropdown>
                @endif
            </div>
        </div>

        <div class="c61-kpis">
            <div class="c61-kpi">
                <div class="c61-kpi-label">Penjualan F&amp;B</div>
                <div class="c61-kpi-value is-terra" style="font-size: 1.4rem;">{{ $rp($s['sales']) }}</div>
                <div class="c61-kpi-foot"><span>{{ $periodLabel }}</span></div>
            </div>
            <div class="c61-kpi">
                <div class="c61-kpi-label">Laba Kotor</div>
                <div class="c61-kpi-value" style="font-size: 1.4rem;">{{ $rp($s['gross_profit']) }}</div>
                <div class="c61-kpi-foot"><span>Margin {{ $pct($s['gross_profit'], $s['sales']) }}% dari penjualan</span></div>
            </div>
            <div class="c61-kpi">
                <div class="c61-kpi-label">Laba Bersih</div>
                <div class="c61-kpi-value {{ $s['net_profit'] >= 0 ? 'is-ok' : '' }}" style="font-size: 1.4rem; {{ $s['net_profit'] < 0 ? 'color:#B42318;' : '' }}">{{ $rp($s['net_profit']) }}</div>
                <div class="c61-kpi-foot"><span>Setelah kerugian stok &amp; biaya operasional</span></div>
            </div>
            <div class="c61-kpi">
                <div class="c61-kpi-label">Arus Kas</div>
                <div class="c61-kpi-value" style="font-size: 1.4rem; {{ $s['cash_flow'] < 0 ? 'color:#B42318;' : '' }}">{{ $rp($s['cash_flow']) }}</div>
                <div class="c61-kpi-foot"><span>Uang masuk − semua uang keluar</span></div>
            </div>
        </div>

        @if ($s['missing_cost_qty'] > 0)
            <div class="c61-note c61-note-warn">{{ $s['missing_cost_qty'] }} item terjual di periode ini belum punya HPP (HPP menu belum diisi, penjualan sebelum fitur ini, atau data impor), dihitung HPP Rp0. Laba kotornya bisa terlihat lebih besar dari sebenarnya. Menu: {{ $s['menus']->where('missing_cost_qty', '>', 0)->pluck('name')->implode(', ') }}. Isi HPP di Menu F&B untuk penjualan berikutnya.</div>
        @endif

        <div class="lf-grid-2">
            <div class="c61-card">
                <div class="c61-card-head"><div><div class="c61-card-title">Laporan Laba</div><div class="c61-card-sub">{{ $periodLabel }}</div></div></div>
                <div class="lf-lines">
                    <div class="lf-line"><span>Penjualan (bersih, tanpa pajak &amp; biaya layanan)</span><span>{{ $rp($s['sales']) }}</span></div>
                    <div class="lf-line"><span>HPP terjual</span><span class="lf-minus">− {{ $rp($s['hpp']) }}</span></div>
                    <div class="lf-line" style="font-weight: 800;"><span>Laba Kotor</span><span>{{ $rp($s['gross_profit']) }}</span></div>
                    <div class="lf-line"><span>Kerugian stok (rusak, hilang, selisih opname)</span><span class="lf-minus">− {{ $rp($s['stock_loss']) }}</span></div>
                    <div class="lf-line"><span>Biaya operasional</span><span class="lf-minus">− {{ $rp($s['operational']) }}</span></div>
                    <div class="lf-line is-total"><span>Laba Bersih</span><span class="{{ $s['net_profit'] < 0 ? 'lf-minus' : '' }}">{{ $rp($s['net_profit']) }}</span></div>
                </div>
            </div>

            <div class="c61-card">
                <div class="c61-card-head"><div><div class="c61-card-title">Arus Kas</div><div class="c61-card-sub">{{ $periodLabel }}</div></div></div>
                <div class="lf-lines">
                    <div class="lf-line"><span>Uang masuk penjualan kasir F&amp;B (termasuk pajak &amp; biaya layanan)</span><span class="lf-plus">+ {{ $rp($s['money_in']) }}</span></div>
                    <div class="lf-line"><span>Pembelian persediaan &amp; bahan</span><span class="lf-minus">− {{ $rp($s['stock_purchases']) }}</span></div>
                    <div class="lf-line"><span>Biaya operasional</span><span class="lf-minus">− {{ $rp($s['operational']) }}</span></div>
                    @if (count($s['expense_by_category']))
                        <div class="lf-line is-sub" style="font-weight: 700;"><span>Rincian pengeluaran per kategori</span><span></span></div>
                    @endif
                    @foreach ($s['expense_by_category'] as $name => $amount)
                        <div class="lf-line is-sub"><span>{{ $name }}</span><span>{{ $rp($amount) }}</span></div>
                    @endforeach
                    <div class="lf-line is-total"><span>Selisih Arus Kas</span><span class="{{ $s['cash_flow'] < 0 ? 'lf-minus' : '' }}">{{ $rp($s['cash_flow']) }}</span></div>
                </div>
            </div>
        </div>

        <div class="c61-card">
            <div class="c61-card-head"><div><div class="c61-card-title">Tren 6 Bulan</div><div class="c61-card-sub">Penjualan dan laba bersih per bulan</div></div></div>
            <div class="c61-table-wrap">
                <table class="c61-table lf-trend">
                    <thead>
                        <tr>
                            <th>Bulan</th>
                            <th style="width: 34%;">Penjualan</th>
                            <th style="width: 34%;">Laba Bersih</th>
                            <th style="text-align: right;">Laba Kotor</th>
                            <th style="text-align: right;">Uang Keluar</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($trend as $m)
                            <tr>
                                <td class="strong">{{ $m['label'] }}</td>
                                <td>
                                    <i class="lf-bar" style="width: {{ $pct($m['sales'], $trendMax) }}%;"></i>
                                    <div class="sub">{{ $rp($m['sales']) }}</div>
                                </td>
                                <td>
                                    <i class="lf-bar {{ $m['net_profit'] < 0 ? 'is-loss' : 'is-profit' }}" style="width: {{ $pct(abs($m['net_profit']), $trendMax) }}%;"></i>
                                    <div class="sub">{{ $rp($m['net_profit']) }}</div>
                                </td>
                                <td class="num">{{ $rp($m['gross_profit']) }}</td>
                                <td class="num">{{ $rp($m['money_out']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="lf-grid-2">
            <div class="c61-card">
                <div class="c61-card-head"><div><div class="c61-card-title">Menu Terlaris</div><div class="c61-card-sub">Berdasarkan jumlah terjual · {{ $periodLabel }}</div></div></div>
                <div class="c61-table-wrap">
                    <table class="c61-table">
                        <thead><tr><th>Menu</th><th style="text-align: right;">Terjual</th><th style="text-align: right;">Penjualan</th></tr></thead>
                        <tbody>
                            @forelse ($topQty as $m)
                                <tr><td class="strong">{{ $m['name'] }}</td><td class="num">{{ $m['qty'] }}</td><td class="num">{{ $rp($m['sales']) }}</td></tr>
                            @empty
                                <tr><td colspan="3" class="c61-empty">Belum ada penjualan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="c61-card">
                <div class="c61-card-head"><div><div class="c61-card-title">Menu Paling Menguntungkan</div><div class="c61-card-sub">Berdasarkan laba kotor (penjualan − HPP)</div></div></div>
                <div class="c61-table-wrap">
                    <table class="c61-table">
                        <thead><tr><th>Menu</th><th style="text-align: right;">HPP</th><th style="text-align: right;">Laba Kotor</th><th style="text-align: right;">Margin</th></tr></thead>
                        <tbody>
                            @forelse ($topProfit as $m)
                                <tr><td class="strong">{{ $m['name'] }}</td><td class="num">{{ $rp($m['hpp']) }}</td><td class="num">{{ $rp($m['profit']) }}</td><td class="num">{{ $pct($m['profit'], $m['sales']) }}%</td></tr>
                            @empty
                                <tr><td colspan="4" class="c61-empty">Belum ada penjualan.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="c61-card">
            <div class="c61-card-head">
                <div><div class="c61-card-title">Stok Menipis / Habis / Minus</div><div class="c61-card-sub">Kondisi saat ini, menu yang stoknya dilacak</div></div>
                @if (\App\Filament\Pages\StokFnb::canAccess())
                    <a href="{{ \App\Filament\Pages\StokFnb::getUrl() }}" class="c61-btn c61-btn-ghost c61-btn-sm">Buka Stok F&amp;B</a>
                @endif
            </div>
            <div class="c61-table-wrap">
                <table class="c61-table">
                    <thead><tr><th>Menu</th><th style="text-align: right;">Stok</th><th style="text-align: right;">Minimum</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse ($alerts as $menu)
                            @php $st = $menu->stockStatus(); @endphp
                            <tr>
                                <td class="strong">{{ $menu->name }}</td>
                                <td class="num" style="{{ $menu->stock_qty < 0 ? 'color:#B42318;' : '' }}">{{ $menu->stock_qty }}</td>
                                <td class="num">{{ $menu->min_stock ?? '-' }}</td>
                                <td><span class="c61-pill {{ $st === 'LOW' ? 'c61-pill-warn' : 'c61-pill-danger' }}">{{ ['MINUS' => 'Minus', 'EMPTY' => 'Habis', 'LOW' => 'Menipis'][$st] ?? $st }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="c61-empty">Semua stok aman.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
