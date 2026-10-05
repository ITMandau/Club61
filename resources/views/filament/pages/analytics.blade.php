{{-- Modul 17: semua angka uang dari Buku Transaksi (ledger_entries) — lihat App\Filament\Pages\Analytics. --}}
@php
    $rp = fn ($v) => \App\Filament\Pages\Analytics::rupiah($v);
    $num = fn ($v) => number_format((float) $v, 0, ',', '.');
    $s = $summary;
    $refundTotal = abs($s['refunds']);
@endphp

<div class="adm-wrap">
    <style>
        .an-presets { display:flex; flex-wrap:wrap; gap:0.25rem; padding:0.25rem; background:#FAF5E8; border:1px solid #DFC387; border-radius:14px; }
        .an-presets .adm-tab-btn { font-size:0.75rem; padding:0.4rem 0.8rem; }
        .an-dates { display:flex; flex-wrap:wrap; align-items:center; gap:0.5rem; font-size:0.75rem; color:#5C410F; font-weight:700; }
        .an-dates input { border:1px solid #DFC387; border-radius:10px; padding:0.35rem 0.55rem; font-size:0.75rem; background:#FFFFFF; color:#1F170D; }
        .an-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(min(100%, 360px), 1fr)); gap:1.25rem; margin-bottom:1.5rem; }
        .an-list { display:flex; flex-direction:column; gap:0.75rem; }
        .an-line { display:flex; align-items:center; justify-content:space-between; gap:1rem; padding:0.75rem 1rem; background:#FFFDF5; border-radius:12px; border:1px solid #DFC387; color:inherit; text-decoration:none; }
        .an-line:first-child { background:#FAF5E8; }
        a.an-line:hover { border-color:#D4AF37; background:#FFF8E6; }
        .an-line-title { font-weight:800; font-size:0.875rem; color:#1F170D; display:flex; align-items:center; gap:0.4rem; flex-wrap:wrap; }
        .an-line-sub { font-size:0.6875rem; color:#7A643E; }
        .an-line-val { font-family:var(--font-mono, monospace); font-weight:900; font-size:1rem; color:#8C6418; white-space:nowrap; text-align:right; }
        .an-line-val small { display:block; font-size:0.6875rem; font-weight:700; color:#A68F63; }
        .an-line.muted { background:#FFFFFF; border-color:#E5E7EB; }
        .an-line.muted .an-line-val { color:#6B7280; }
        .an-soon { font-size:0.625rem; font-weight:800; text-transform:uppercase; letter-spacing:0.04em; padding:0.1rem 0.45rem; border-radius:999px; background:#E5E7EB; color:#4B5563; border:1px solid #D1D5DB; }
        .an-methods { margin-top:0.25rem; border-top:1px dashed #DFC387; padding-top:0.75rem; }
        .an-methods-title { font-size:0.6875rem; font-weight:800; text-transform:uppercase; letter-spacing:0.05em; color:#8C6418; margin-bottom:0.35rem; }
        .an-method { display:flex; justify-content:space-between; gap:1rem; padding:0.35rem 0.25rem; font-size:0.78rem; color:#1F170D; text-decoration:none; border-bottom:1px solid #FAF2DE; }
        .an-method:hover { background:#FFFBF0; }
        .an-method span:last-child { font-family:var(--font-mono, monospace); font-weight:800; white-space:nowrap; }
        .an-table { width:100%; font-size:0.8125rem; border-collapse:collapse; }
        .an-table th { text-align:left; color:#8C6418; font-weight:800; padding:0.75rem; border-bottom:1.5px solid #DFC387; white-space:nowrap; }
        .an-table td { padding:0.75rem; border-bottom:1px solid #FAF2DE; }
        .an-table .num { text-align:right; font-family:var(--font-mono, monospace); font-weight:900; white-space:nowrap; }
        .an-status { display:inline-block; font-size:0.6875rem; font-weight:700; padding:0.15rem 0.55rem; border-radius:999px; white-space:nowrap; }
        .an-status.success { background:#ECFDF5; color:#065F46; border:1px solid #A7F3D0; }
        .an-status.warning { background:#FEF3C7; color:#92400E; border:1px solid #FDE68A; }
        .an-status.danger { background:#FEE2E2; color:#991B1B; border:1px solid #FECACA; }
        .an-status.gray { background:#F3F4F6; color:#374151; border:1px solid #D1D5DB; }
    </style>

    {{-- Header --}}
    <div class="adm-banner">
        <div>
            <div class="adm-pill adm-pill-gold">
                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background-color:#D4AF37;"></span>
                <span>Financial Business Intelligence &bull; Club 61 Padel Court</span>
            </div>
            <div class="adm-banner-title">Laporan Uang Masuk &amp; Analisis Finansial</div>
            <div class="adm-banner-sub">
                Rekapitulasi uang masuk dari kasir &amp; Midtrans, refund, dan pendapatan bersih semua lini (padel, add-on, F&amp;B, membership). Angkanya sama dengan Buku Transaksi.
            </div>
        </div>

        <div class="an-presets">
            @foreach (\App\Services\Finance\LedgerReport::PRESETS as $key => $label)
                <button type="button" wire:click="setPreset('{{ $key }}')" class="adm-tab-btn {{ $preset === $key ? 'active' : '' }}">
                    {{ $key === 'kustom' ? 'Pilih Tanggal' : $label }}
                </button>
            @endforeach
        </div>
    </div>

    {{-- Periode aktif --}}
    <div style="margin-bottom:1.25rem; font-size:0.8125rem; color:#7A643E; font-weight:700; display:flex; flex-wrap:wrap; align-items:center; gap:0.5rem;">
        <span>Menampilkan data periode:</span>
        <span class="adm-pill adm-pill-gold" style="font-size:0.75rem;">{{ $periodLabel }}</span>
        @if ($preset === 'kustom')
            <div class="an-dates">
                <span>Dari</span><input type="date" wire:model.live="dari">
                <span>sampai</span><input type="date" wire:model.live="sampai">
            </div>
        @endif
    </div>

    {{-- 4 KPI utama --}}
    <div class="adm-metrics-grid" style="margin-bottom:1.5rem;">
        <div class="adm-metric-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <div class="adm-metric-label">Total Uang Masuk Kotor (Gross)</div>
                    <div class="adm-metric-val" style="color:#1F170D;">{{ $rp($s['money_in']) }}</div>
                </div>
                <span class="adm-pill adm-pill-green">Cash In</span>
            </div>
            <div class="adm-metric-foot">
                <span>{{ $num($s['payments_count']) }} Transaksi Lunas / Settled</span>
                @if ($s['overpayments'] > 0)
                    <span style="color:#B45309;">Termasuk lebih bayar {{ $rp($s['overpayments']) }}</span>
                @else
                    <span style="color:#A68F63;">Sesuai Buku Transaksi</span>
                @endif
            </div>
        </div>

        <div class="adm-metric-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <div class="adm-metric-label">Total Refund Dikeluarkan</div>
                    <div class="adm-metric-val" style="color:#DC2626;">{{ $rp($refundTotal) }}</div>
                </div>
                <span class="adm-pill" style="background:#FEE2E2; color:#DC2626; border:1px solid #FECACA; font-weight:700;">Refund</span>
            </div>
            <div class="adm-metric-foot">
                <span>{{ $num($s['refunds_count']) }} refund diproses</span>
                @if ($s['pending_refund_count'] > 0)
                    <span style="color:#DC2626; font-weight:700;">
                        @if ($canSeeRefundQueue)
                            <a href="{{ \App\Filament\Pages\AntrianRefund::getUrl() }}" style="color:inherit;">{{ $s['pending_refund_count'] }} menunggu ({{ $rp($s['pending_refund_amount']) }})</a>
                        @else
                            {{ $s['pending_refund_count'] }} menunggu ({{ $rp($s['pending_refund_amount']) }})
                        @endif
                    </span>
                @else
                    <span style="color:#A68F63;">Tidak ada antrian</span>
                @endif
            </div>
        </div>

        <div class="adm-metric-card" style="border:2px solid #D4AF37; background:linear-gradient(135deg, #FFFFFF 0%, #FFFDF7 100%);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <div class="adm-metric-label" style="color:#8C6418; font-weight:800;">Pendapatan Bersih (Net Revenue)</div>
                    <div class="adm-metric-val" style="color:#8C6418;">{{ $rp($s['money_net']) }}</div>
                </div>
                <span class="adm-pill adm-pill-gold">Net Income</span>
            </div>
            <div class="adm-metric-foot">
                <span>Gross dikurangi Total Refund</span>
                @if ($s['money_net'] >= 0)
                    <span style="color:#047857; font-weight:800;">Arus Kas Positif</span>
                @else
                    <span style="color:#DC2626; font-weight:800;">Arus Kas Negatif</span>
                @endif
            </div>
        </div>

        <div class="adm-metric-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <div class="adm-metric-label">Tingkat Okupansi Lapangan</div>
                    <div class="adm-metric-val" style="color:#1F170D;">{{ $occupancy['rate'] }}%</div>
                </div>
                <span class="adm-pill adm-pill-green">Lapangan</span>
            </div>
            <div class="adm-metric-foot">
                <span>{{ number_format($occupancy['hours_booked'], 1, ',', '.') }} jam sewa terpakai</span>
                <span style="color:#A68F63;">Kapasitas {{ $occupancy['courts'] }} Court</span>
            </div>
        </div>
    </div>

    {{-- Kanal pembayaran & lini layanan --}}
    <div class="an-grid">
        <div class="adm-card" style="padding:1.25rem;">
            <div class="adm-card-head" style="margin-bottom:1rem;">
                <div>
                    <div class="adm-card-title">Distribusi Kanal Pembayaran</div>
                    <div class="adm-card-sub">Rekap uang masuk bersih berdasarkan saluran transaksi</div>
                </div>
                <span class="adm-pill adm-pill-gold">Payment Channel</span>
            </div>

            <div class="an-list">
                <div class="an-line">
                    <div>
                        <div class="an-line-title">Kasir Frontdesk (POS)</div>
                        <div class="an-line-sub">Tunai, EDC, QRIS &amp; transfer di POS Walk-In, F&amp;B, membership</div>
                    </div>
                    <div class="an-line-val" style="color:#1F170D;">{{ $rp($channels['cashier']['money_net']) }}<small>{{ $num($channels['cashier']['transactions']) }} transaksi</small></div>
                </div>
                <div class="an-line">
                    <div>
                        <div class="an-line-title">Midtrans Gateway (QRIS, GoPay, VA)</div>
                        <div class="an-line-sub">Pembayaran online booking &amp; membership dari aplikasi</div>
                    </div>
                    <div class="an-line-val">{{ $rp($channels['online']['money_net']) }}<small>{{ $num($channels['online']['transactions']) }} transaksi</small></div>
                </div>

                @if (count($byMethod) > 0)
                    <div class="an-methods">
                        <div class="an-methods-title">Rincian per metode bayar</div>
                        @foreach ($byMethod as $m)
                            <a class="an-method" href="{{ $m['method_code'] ? $this->bukuUrl(['metode' => [$m['method_code']]]) : $this->bukuUrl() }}">
                                <span>{{ $m['label'] }} <span style="color:#A68F63; font-family:inherit; font-weight:600;">&middot; {{ $num($m['transactions']) }} trx</span></span>
                                <span>{{ $rp($m['money_net']) }}</span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        <div class="adm-card" style="padding:1.25rem;">
            <div class="adm-card-head" style="margin-bottom:1rem;">
                <div>
                    <div class="adm-card-title">Rincian Pendapatan per Lini Layanan</div>
                    <div class="adm-card-sub">Kontribusi sewa lapangan, add-on, F&amp;B, wellness, dan coaching</div>
                </div>
                <span class="adm-pill adm-pill-gold">Revenue Mix</span>
            </div>

            <div class="an-list">
                @foreach ($serviceLines as $line)
                    @php $tag = $line['filter'] ? 'a' : 'div'; @endphp
                    <{{ $tag }} class="an-line {{ $line['soon'] ? 'muted' : '' }}" @if ($line['filter']) href="{{ $this->bukuUrl(['kategori' => $line['filter']]) }}" @endif>
                        <div>
                            <div class="an-line-title">
                                {{ $line['label'] }}
                                @if ($line['soon'])
                                    <span class="an-soon">Menyusul</span>
                                @endif
                            </div>
                            <div class="an-line-sub">{{ $line['sub'] }}</div>
                        </div>
                        <div class="an-line-val" @if ($loop->first) style="color:#1F170D;" @endif>
                            {{ $rp($line['money_net']) }}
                            @unless ($line['soon'])
                                <small>{{ $num($line['transactions']) }} transaksi</small>
                            @endunless
                        </div>
                    </{{ $tag }}>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Membership: uang riil penjualan paket vs nilai benefit yang dipakai (dua hal berbeda) --}}
    <div class="an-grid">
        <div class="adm-card" style="padding:1.25rem; border:2px solid #D4AF37;">
            <div class="adm-card-head" style="margin-bottom:1rem;">
                <div>
                    <div class="adm-card-title">Pemasukan Penjualan Paket Membership</div>
                    <div class="adm-card-sub">Uang riil diterima saat paket dibeli (Padel/Gym/Sauna) &mdash; kanal terpisah dari sewa lapangan</div>
                </div>
                <span class="adm-pill adm-pill-gold">Cash In</span>
            </div>

            <div class="an-list">
                <a class="an-line" href="{{ $this->bukuUrl(['kategori' => ['MEMBERSHIP']]) }}">
                    <div>
                        <div class="an-line-title">Omzet Penjualan Membership</div>
                        <div class="an-line-sub">Paket membership yang lunas (kasir &amp; online), setelah refund</div>
                    </div>
                    <div class="an-line-val" style="color:#1F170D;">{{ $rp($membership['sales']) }}</div>
                </a>
                <div class="an-line">
                    <div>
                        <div class="an-line-title">Omzet Gabungan Venue</div>
                        <div class="an-line-sub">Lini layanan {{ $rp($membership['others']) }} + membership {{ $rp($membership['sales']) }} &middot; termasuk pajak {{ $rp($s['tax']) }}</div>
                    </div>
                    <div class="an-line-val">{{ $rp($s['money_net']) }}</div>
                </div>
            </div>
        </div>

        <div class="adm-card" style="padding:1.25rem; background:#FAFAFA;">
            <div class="adm-card-head" style="margin-bottom:1rem;">
                <div>
                    <div class="adm-card-title">Nilai Benefit Member Terpakai (Informasional)</div>
                    <div class="adm-card-sub">Bukan pendapatan baru &mdash; uangnya sudah diakui saat paket dibeli. Ini cuma indikator utilisasi.</div>
                </div>
                <span class="adm-pill" style="background:#E5E7EB; color:#374151; border:1px solid #D1D5DB; font-weight:700;">Bukan Omzet</span>
            </div>

            <div class="an-list">
                <div class="an-line muted">
                    <div>
                        <div class="an-line-title">Nilai Diskon/Kuota yang Dipakai</div>
                        <div class="an-line-sub">Setara tarif reguler yang "dibayar" pakai kuota member / voucher sponsor</div>
                    </div>
                    <div class="an-line-val">{{ $rp($s['benefit']) }}</div>
                </div>
                <div class="an-line muted">
                    <div>
                        <div class="an-line-title">Jam Padel Terpakai via Kuota</div>
                        <div class="an-line-sub">{{ $num($memberUsage['bookings']) }} booking menggunakan benefit membership</div>
                    </div>
                    <div class="an-line-val">{{ number_format($memberUsage['hours'], 1, ',', '.') }} Jam</div>
                </div>
                @if ($s['forfeited'] > 0)
                    <div class="an-line muted">
                        <div>
                            <div class="an-line-title">Selisih Reschedule Hangus</div>
                            <div class="an-line-sub">Sudah tercatat saat pembayaran awal</div>
                        </div>
                        <div class="an-line-val">{{ $rp($s['forfeited']) }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- 10 mutasi terbaru --}}
    <div class="adm-card" style="padding:1.5rem;">
        <div class="adm-card-head" style="margin-bottom:1rem;">
            <div>
                <div class="adm-card-title">Riwayat Mutasi Uang Masuk Terkini</div>
                <div class="adm-card-sub">10 pembayaran &amp; refund terbaru dari semua kasir dan online pada periode ini</div>
            </div>
            <a href="{{ $this->bukuUrl() }}" class="adm-pill adm-pill-gold" style="text-decoration:none;">Buka Buku Transaksi &rarr;</a>
        </div>

        <div style="overflow-x:auto;">
            <table class="an-table">
                <thead>
                    <tr>
                        <th>WAKTU TRANSAKSI</th>
                        <th>NO. ORDER</th>
                        <th>MEMBER / CUSTOMER</th>
                        <th>LAYANAN &amp; SUMBER</th>
                        <th>METODE</th>
                        <th>STATUS</th>
                        <th style="text-align:right;">UANG MASUK</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($latest as $row)
                        @php
                            $status = \App\Services\Finance\LedgerReport::statusLabel($row);
                            $services = collect(explode(',', (string) $row->categories))->filter()->unique()
                                ->map(fn ($c) => \App\Models\Finance\LedgerEntry::categoryLabel($c))->implode(', ');
                            $amount = (float) $row->total_amount;
                        @endphp
                        <tr>
                            <td style="color:#6B7280; font-size:0.75rem; white-space:nowrap;">{{ $row->occurred_at ? \Carbon\Carbon::parse($row->occurred_at)->timezone(\App\Services\Finance\LedgerReport::TIMEZONE)->format('d M Y, H:i') : '-' }} WIB</td>
                            <td style="font-family:var(--font-mono, monospace); font-weight:700; color:#8C6418;">{{ $row->order_number ?? '-' }}</td>
                            <td style="font-weight:800; color:#1F170D;">{{ $row->customer_name ?? 'Customer' }}</td>
                            <td style="color:#4B5563;">
                                {{ $services ?: '-' }}
                                <div style="font-size:0.6875rem; color:#A68F63;">{{ \App\Models\Finance\LedgerEntry::sourceLabel($row->source) }}</div>
                            </td>
                            <td style="color:#4B5563;">{{ $row->payment_method_label ?? $row->payment_method ?? '-' }}</td>
                            <td><span class="an-status {{ \App\Services\Finance\LedgerReport::statusColor($status) }}">{{ $status }}</span></td>
                            <td class="num" style="color:{{ $amount < 0 ? '#DC2626' : '#047857' }};">{{ $amount < 0 ? '- '.$rp(abs($amount)) : '+ '.$rp($amount) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" style="padding:2rem; text-align:center; color:#8C7A58;">Belum ada transaksi masuk pada periode ini.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
