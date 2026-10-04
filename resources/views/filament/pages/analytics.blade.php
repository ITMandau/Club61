{{-- Modul 17 Fase 3: semua angka uang dari Buku Transaksi (ledger_entries) — lihat App\Filament\Pages\Analytics. --}}
@php
    $rp = fn ($v) => \App\Filament\Pages\Analytics::rupiah($v);
    $num = fn ($v) => number_format((float) $v, 0, ',', '.');
    $s = $summary;

    // Grafik batang: uang masuk ke atas, refund ke bawah garis nol.
    $points = $trend['points'];
    $count = max(1, count($points));
    $maxIn = max(1, collect($points)->max('money_in'));
    $maxOut = collect($points)->map(fn ($p) => abs($p['refunds']))->max() ?: 0;
    $chartW = 760; $chartH = 220; $padL = 8; $padB = 22;
    $plotH = $chartH - $padB - 6;
    $upH = $maxOut > 0 ? $plotH * ($maxIn / ($maxIn + $maxOut)) : $plotH;
    $baseY = 6 + $upH;
    $slot = ($chartW - $padL) / $count;
    $barW = max(2, min(28, $slot * 0.62));
    $labelEvery = (int) ceil($count / 12);
    $hasTrendData = collect($points)->contains(fn ($p) => $p['money_in'] != 0 || $p['refunds'] != 0);

    $tables = [
        ['title' => 'Per Kategori', 'sub' => 'Sewa lapangan, add-on, F&B, membership', 'rows' => $byCategory, 'param' => 'kategori'],
        ['title' => 'Per Sumber / POS', 'sub' => 'Kasir walk-in, online, pelunasan selisih, F&B', 'rows' => $bySource, 'param' => 'sumber'],
        ['title' => 'Per Metode Bayar', 'sub' => 'Untuk mencocokkan mutasi bank & settlement EDC', 'rows' => $byMethod, 'param' => 'metode'],
    ];
@endphp

<div class="adm-wrap">
    <style>
        .an-presets { display:flex; flex-wrap:wrap; gap:0.35rem; padding:0.25rem; background:#FAF5E8; border:1px solid #DFC387; border-radius:14px; }
        .an-presets .adm-tab-btn { font-size:0.75rem; padding:0.4rem 0.75rem; }
        .an-dates { display:flex; flex-wrap:wrap; align-items:center; gap:0.5rem; font-size:0.75rem; color:#5C410F; font-weight:700; }
        .an-dates input { border:1px solid #DFC387; border-radius:10px; padding:0.35rem 0.55rem; font-size:0.75rem; background:#FFFFFF; color:#1F170D; }
        .an-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 340px), 1fr)); gap:1.25rem; margin-bottom:1.5rem; }
        .an-table { width:100%; font-size:0.78rem; border-collapse:collapse; }
        .an-table th { text-align:left; color:#8C6418; font-weight:800; font-size:0.66rem; text-transform:uppercase; letter-spacing:0.05em; padding:0.45rem 0.5rem; border-bottom:1.5px solid #DFC387; }
        .an-table td { padding:0.5rem; border-bottom:1px solid #FAF2DE; color:#1F170D; }
        .an-table .num { text-align:right; font-variant-numeric:tabular-nums; white-space:nowrap; }
        .an-table tr.link-row { cursor:pointer; }
        .an-table tr.link-row:hover td { background:#FFFBF0; }
        .an-table a { color:inherit; text-decoration:none; }
        .an-note { font-size:0.75rem; color:#7A643E; line-height:1.5; }
        .an-legend { display:flex; gap:1rem; font-size:0.7rem; color:#5C410F; font-weight:700; }
        .an-legend i { display:inline-block; width:10px; height:10px; border-radius:3px; margin-right:0.3rem; vertical-align:-1px; }
    </style>

    <div class="adm-banner">
        <div>
            <div class="adm-pill adm-pill-gold">
                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background-color:#D4AF37;"></span>
                <span>Sumber angka: Buku Transaksi &bull; Club 61 Padel Court</span>
            </div>
            <div class="adm-banner-title">Laporan Uang Masuk &amp; Analisis Finansial</div>
            <div class="adm-banner-sub">
                Semua uang masuk (padel, add-on, membership, F&amp;B) dihitung dari tanggal uang diterima (WIB), setelah refund. Angkanya sama dengan Buku Transaksi.
            </div>
        </div>
        <a href="{{ $this->bukuUrl() }}" class="adm-pill adm-pill-gold" style="text-decoration:none;">Buka Buku Transaksi &rarr;</a>
    </div>

    <div style="display:flex; flex-wrap:wrap; align-items:center; gap:0.75rem; margin-bottom:1.25rem;">
        <div class="an-presets">
            @foreach (\App\Services\Finance\LedgerReport::PRESETS as $key => $label)
                <button type="button" wire:click="setPreset('{{ $key }}')" class="adm-tab-btn {{ $preset === $key ? 'active' : '' }}">
                    {{ $key === 'kustom' ? 'Pilih tanggal' : $label }}
                </button>
            @endforeach
        </div>
        @if ($preset === 'kustom')
            <div class="an-dates">
                <span>Dari</span><input type="date" wire:model.live="dari">
                <span>sampai</span><input type="date" wire:model.live="sampai">
            </div>
        @endif
        <span class="adm-pill adm-pill-gold" style="font-size:0.75rem;">{{ $periodLabel }}</span>
    </div>

    {{-- Kartu utama --}}
    <div class="adm-metrics-grid" style="margin-bottom:1.5rem;">
        <div class="adm-metric-card">
            <div class="adm-metric-label">Uang Diterima</div>
            <div class="adm-metric-val" style="color:#1F170D;">{{ $rp($s['money_in']) }}</div>
            <div class="adm-metric-foot">
                <span>{{ $num($s['payments_count']) }} pembayaran</span>
                @if ($s['overpayments'] > 0)
                    <span style="color:#B45309;">termasuk kelebihan bayar {{ $rp($s['overpayments']) }}</span>
                @endif
            </div>
        </div>

        <div class="adm-metric-card">
            <div class="adm-metric-label">Refund Dikembalikan</div>
            <div class="adm-metric-val" style="color:#DC2626;">{{ $rp($s['refunds']) }}</div>
            <div class="adm-metric-foot">
                <span>{{ $num($s['refunds_count']) }} refund diproses</span>
                <span>
                    Menunggu: {{ $s['pending_refund_count'] }} ({{ $rp($s['pending_refund_amount']) }})
                    @if ($canSeeRefundQueue && $s['pending_refund_count'] > 0)
                        &middot; <a href="{{ \App\Filament\Pages\AntrianRefund::getUrl() }}" style="color:#8C6418; font-weight:800;">Antrian</a>
                    @endif
                </span>
            </div>
        </div>

        <div class="adm-metric-card" style="border:2px solid #D4AF37; background:linear-gradient(135deg, #FFFFFF 0%, #FFFDF7 100%);">
            <div class="adm-metric-label" style="color:#8C6418; font-weight:800;">Total Uang Masuk (Bersih)</div>
            <div class="adm-metric-val" style="color:#8C6418;">{{ $rp($s['money_net']) }}</div>
            <div class="adm-metric-foot">
                <span>Uang diterima &minus; refund</span>
                <span>= penjualan + layanan + pajak</span>
            </div>
        </div>

        <div class="adm-metric-card">
            <div class="adm-metric-label">Penjualan Bersih (Pendapatan)</div>
            <div class="adm-metric-val" style="color:#1F170D;">{{ $rp($s['net']) }}</div>
            <div class="adm-metric-foot">
                <span>Pajak {{ $rp($s['tax']) }}</span>
                <span>Layanan {{ $rp($s['service']) }}</span>
            </div>
        </div>

        <div class="adm-metric-card">
            <div class="adm-metric-label">Okupansi Lapangan</div>
            <div class="adm-metric-val" style="color:#1F170D;">{{ $occupancy['rate'] }}%</div>
            <div class="adm-metric-foot">
                <span>{{ number_format($occupancy['hours_booked'], 1, ',', '.') }} dari {{ $num($occupancy['capacity_hours']) }} jam</span>
                <span>{{ $occupancy['courts'] }} lapangan aktif</span>
            </div>
        </div>
    </div>

    {{-- Grafik tren --}}
    <div class="adm-card" style="padding:1.25rem; margin-bottom:1.5rem;">
        <div class="adm-card-head" style="margin-bottom:0.75rem;">
            <div>
                <div class="adm-card-title">Tren Uang Masuk {{ $trend['unit'] === 'day' ? 'Harian' : 'Bulanan' }}</div>
                <div class="adm-card-sub">Arahkan kursor ke batang untuk melihat angkanya</div>
            </div>
            <div class="an-legend">
                <span><i style="background:#D4AF37;"></i>Uang diterima</span>
                <span><i style="background:#F87171;"></i>Refund</span>
            </div>
        </div>

        @if ($hasTrendData)
            <svg viewBox="0 0 {{ $chartW }} {{ $chartH }}" style="width:100%; height:auto; display:block;" role="img" aria-label="Grafik tren uang masuk">
                <line x1="{{ $padL }}" y1="{{ $baseY }}" x2="{{ $chartW }}" y2="{{ $baseY }}" stroke="#DFC387" stroke-width="1" />
                @foreach ($points as $i => $p)
                    @php
                        $x = $padL + $slot * $i + ($slot - $barW) / 2;
                        $hIn = $p['money_in'] > 0 ? max(1, $upH * ($p['money_in'] / $maxIn)) : 0;
                        $hOut = ($maxOut > 0 && $p['refunds'] != 0) ? max(1, ($plotH - $upH) * (abs($p['refunds']) / $maxOut)) : 0;
                        $tip = $p['label'].' — diterima '.$rp($p['money_in']).($p['refunds'] != 0 ? ', refund '.$rp($p['refunds']) : '').', bersih '.$rp($p['money_net']);
                    @endphp
                    <g>
                        <title>{{ $tip }}</title>
                        <rect x="{{ $x }}" y="6" width="{{ $barW }}" height="{{ $chartH - $padB - 6 }}" fill="transparent" />
                        @if ($hIn > 0)
                            <rect x="{{ $x }}" y="{{ $baseY - $hIn }}" width="{{ $barW }}" height="{{ $hIn }}" rx="2" fill="#D4AF37" />
                        @endif
                        @if ($hOut > 0)
                            <rect x="{{ $x }}" y="{{ $baseY }}" width="{{ $barW }}" height="{{ $hOut }}" rx="2" fill="#F87171" />
                        @endif
                        @if ($i % $labelEvery === 0)
                            <text x="{{ $x + $barW / 2 }}" y="{{ $chartH - 6 }}" text-anchor="middle" font-size="10" fill="#8C754E">{{ $p['label'] }}</text>
                        @endif
                    </g>
                @endforeach
            </svg>
        @else
            <div class="an-note" style="padding:2rem; text-align:center;">Belum ada uang masuk pada periode ini.</div>
        @endif
    </div>

    {{-- Rincian per kategori / sumber / metode --}}
    <div class="an-grid">
        @foreach ($tables as $t)
            <div class="adm-card" style="padding:1.25rem;">
                <div class="adm-card-head" style="margin-bottom:0.75rem;">
                    <div>
                        <div class="adm-card-title">{{ $t['title'] }}</div>
                        <div class="adm-card-sub">{{ $t['sub'] }} &middot; klik baris untuk lihat transaksinya</div>
                    </div>
                </div>
                <div style="overflow-x:auto;">
                    <table class="an-table">
                        <thead>
                            <tr>
                                <th>{{ $t['param'] === 'metode' ? 'Metode' : ($t['param'] === 'sumber' ? 'Sumber' : 'Kategori') }}</th>
                                <th class="num">Trx</th>
                                <th class="num">Diterima</th>
                                <th class="num">Refund</th>
                                <th class="num">Bersih</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($t['rows'] as $row)
                                @php
                                    $value = $t['param'] === 'metode' ? $row['method_code'] : $row['key'];
                                    $url = $value ? $this->bukuUrl([$t['param'] => [$value]]) : $this->bukuUrl();
                                @endphp
                                <tr class="link-row" onclick="window.location.href='{{ $url }}'">
                                    <td><a href="{{ $url }}" style="font-weight:800;">{{ $row['label'] }}</a></td>
                                    <td class="num">{{ $num($row['transactions']) }}</td>
                                    <td class="num">{{ $rp($row['money_in']) }}</td>
                                    <td class="num" style="color:{{ $row['refunds'] != 0 ? '#DC2626' : '#A68F63' }};">{{ $row['refunds'] != 0 ? $rp($row['refunds']) : '-' }}</td>
                                    <td class="num" style="font-weight:900;">{{ $rp($row['money_net']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="an-note" style="text-align:center; padding:1.25rem;">Belum ada transaksi.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        {{-- Benefit membership: informasi, bukan uang masuk --}}
        <div class="adm-card" style="padding:1.25rem; background:#FAFAFA;">
            <div class="adm-card-head" style="margin-bottom:0.75rem;">
                <div>
                    <div class="adm-card-title">Benefit Member &amp; Voucher Terpakai</div>
                    <div class="adm-card-sub">Bukan uang masuk baru &mdash; paketnya sudah dibayar saat dibeli</div>
                </div>
                <span class="adm-pill" style="background:#E5E7EB; color:#374151; border:1px solid #D1D5DB; font-weight:700;">Bukan Omzet</span>
            </div>
            <table class="an-table">
                <tbody>
                    <tr><td>Nilai kuota member / voucher sponsor dipakai (Buku Transaksi)</td><td class="num" style="font-weight:900;">{{ $rp($s['benefit']) }}</td></tr>
                    <tr><td>Jam padel dibayar pakai kuota member</td><td class="num" style="font-weight:900;">{{ number_format($memberUsage['hours'], 1, ',', '.') }} jam</td></tr>
                    <tr><td>Booking yang memakai benefit membership</td><td class="num" style="font-weight:900;">{{ $num($memberUsage['bookings']) }}</td></tr>
                    <tr><td>Selisih reschedule hangus (sudah tercatat saat bayar awal)</td><td class="num" style="font-weight:900;">{{ $rp($s['forfeited']) }}</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    {{-- Transaksi terbaru --}}
    <div class="adm-card" style="padding:1.25rem;">
        <div class="adm-card-head" style="margin-bottom:0.75rem;">
            <div>
                <div class="adm-card-title">10 Transaksi Terbaru</div>
                <div class="adm-card-sub">Pembayaran &amp; refund dari semua POS dan online pada periode ini</div>
            </div>
            <a href="{{ $this->bukuUrl() }}" class="adm-pill adm-pill-gold" style="text-decoration:none;">Lihat semua &rarr;</a>
        </div>
        <div style="overflow-x:auto;">
            <table class="an-table">
                <thead>
                    <tr>
                        <th>Waktu (WIB)</th>
                        <th>No. Order</th>
                        <th>Sumber</th>
                        <th>Customer</th>
                        <th>Metode</th>
                        <th>Status</th>
                        <th class="num">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($latest as $row)
                        @php $status = \App\Services\Finance\LedgerReport::statusLabel($row); @endphp
                        <tr>
                            <td style="white-space:nowrap; color:#6B7280;">{{ $row->occurred_at ? \Carbon\Carbon::parse($row->occurred_at)->timezone(\App\Services\Finance\LedgerReport::TIMEZONE)->format('d M Y H:i') : '-' }}</td>
                            <td style="font-family:monospace; font-weight:700; color:#8C6418;">{{ $row->order_number ?? '-' }}</td>
                            <td>{{ \App\Models\Finance\LedgerEntry::sourceLabel($row->source) }}</td>
                            <td>{{ $row->customer_name ?? '-' }}</td>
                            <td>{{ $row->payment_method_label ?? $row->payment_method ?? '-' }}</td>
                            <td>{{ $status }}</td>
                            <td class="num" style="font-weight:900; color:{{ (float) $row->total_amount < 0 ? '#DC2626' : '#047857' }};">{{ $rp($row->total_amount) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="an-note" style="text-align:center; padding:1.5rem;">Belum ada transaksi pada periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
