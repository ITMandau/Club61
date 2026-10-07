{{-- Dashboard admin — semua dari data asli (App\Filament\Pages\Dashboard). Angka uang = Buku Transaksi, hanya untuk yang boleh membuka Analytics. --}}
@php
    $rp = fn ($v) => \App\Filament\Pages\Dashboard::rupiah($v);
    $num = fn ($v) => number_format((float) $v, 0, ',', '.');
    $pillStyle = [
        'green' => 'background:#ECFDF5; color:#047857; border:1px solid #A7F3D0;',
        'gold' => 'background:#FAF2DE; color:#7A5818; border:1px solid #DFC387;',
        'amber' => 'background:#FEF3C7; color:#92400E; border:1px solid #FDE68A;',
        'red' => 'background:#FEE2E2; color:#B42318; border:1px solid #FECACA;',
        'gray' => 'background:#F3F4F6; color:#374151; border:1px solid #D1D5DB;',
    ];
    $change = function (?float $pct) {
        if ($pct === null) {
            return null;
        }

        return [($pct >= 0 ? '↗ +' : '↘ ').number_format($pct, 1, ',', '.').'%', $pct >= 0 ? 'adm-pill-green' : ''];
    };

    // Grafik garis uang masuk bersih per hari.
    if ($trend) {
        $pts = $trend['points'];
        $n = max(1, count($pts) - 1);
        $values = array_column($pts, 'money_net');
        $maxV = max(1, max($values ?: [0]));
        $minV = min(0, min($values ?: [0]));
        $W = 1000; $H = 220; $L = 70; $R = 985; $T = 15; $B = 190;
        $x = fn ($i) => $L + ($R - $L) * ($n ? $i / $n : 0);
        $y = fn ($v) => $B - ($B - $T) * (($v - $minV) / ($maxV - $minV ?: 1));
        $line = collect($pts)->map(fn ($p, $i) => round($x($i), 1).','.round($y($p['money_net']), 1))->implode(' ');
        $area = 'M '.round($x(0), 1).','.$B.' L '.str_replace(' ', ' L ', $line).' L '.round($x(count($pts) - 1), 1).','.$B.' Z';
        $labelEvery = (int) ceil(count($pts) / 10);
        $hasTrend = collect($pts)->contains(fn ($p) => $p['money_in'] != 0 || $p['refunds'] != 0);
    }
@endphp

<div class="adm-wrap">
    {{-- 1. Banner --}}
    <div class="adm-banner">
        <div>
            <div class="adm-pill adm-pill-gold">
                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background-color:#D4AF37;"></span>
                <span>Club 61 Padel Court &bull; {{ $now->translatedFormat('l, d F Y') }}</span>
            </div>
            <div class="adm-banner-title">Selamat datang, {{ auth()->user()?->name }}</div>
            <div class="adm-banner-sub">Ringkasan operasional hari ini. Data diperbarui setiap halaman dibuka.</div>
        </div>

        <div style="background:rgba(255,255,255,0.85); border:1.5px solid #DFC387; border-radius:14px; padding:0.5rem 0.85rem; display:flex; align-items:center; gap:0.5rem;">
            <div style="width:10px; height:10px; border-radius:50%; background:{{ $occupancy['courts'] > 0 ? '#10B981' : '#9CA3AF' }};"></div>
            <div>
                <div style="font-size:0.625rem; font-weight:800; text-transform:uppercase; color:#8C6418;">Status Venue</div>
                <div style="font-size:0.75rem; font-weight:800; color:#1F170D;">
                    {{ $occupancy['courts'] }} lapangan aktif &bull; {{ $playingNow }} sedang dipakai
                </div>
            </div>
        </div>
    </div>

    {{-- 2. Kartu ringkasan --}}
    <div class="adm-metrics-grid">
        @if ($canSeeMoney)
            @php $c = $change($money['change']); @endphp
            <div class="adm-metric-card">
                <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:0.5rem;">
                    <div>
                        <div class="adm-metric-label">Uang Masuk Hari Ini</div>
                        <div class="adm-metric-val">{{ $rp($money['today']) }}</div>
                    </div>
                    @if ($c)
                        <span class="adm-pill {{ $c[1] }}" @if (! $c[1]) style="{{ $pillStyle['red'] }}" @endif title="Dibanding kemarin">{{ $c[0] }}</span>
                    @endif
                </div>
                <div class="adm-metric-foot">
                    <span>{{ $num($money['today_count']) }} pembayaran &bull; kemarin {{ $rp($money['yesterday']) }}</span>
                    <span style="color:#A68F63;">Bulan ini {{ $rp($money['month']) }}</span>
                </div>
            </div>
        @endif

        <div class="adm-metric-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:0.5rem;">
                <div>
                    <div class="adm-metric-label">Booking Lapangan Hari Ini</div>
                    <div class="adm-metric-val">{{ $num($bookingsToday) }} booking</div>
                </div>
                <span class="adm-pill adm-pill-gold">{{ $num($checkedInToday) }} check-in</span>
            </div>
            <div class="adm-metric-foot">
                <span>Okupansi {{ $occupancy['rate'] }}%</span>
                <span style="color:#A68F63;">{{ number_format($occupancy['hours_booked'], 1, ',', '.') }} dari {{ $num($occupancy['capacity_hours']) }} jam</span>
            </div>
        </div>

        @php $cc = $change($customers['change']); @endphp
        <div class="adm-metric-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:0.5rem;">
                <div>
                    <div class="adm-metric-label">Customer Baru Bulan Ini</div>
                    <div class="adm-metric-val">{{ $num($customers['this_month']) }} akun</div>
                </div>
                @if ($cc)
                    <span class="adm-pill {{ $cc[1] }}" @if (! $cc[1]) style="{{ $pillStyle['red'] }}" @endif title="Dibanding periode yang sama bulan lalu">{{ $cc[0] }}</span>
                @endif
            </div>
            <div class="adm-metric-foot">
                <span>Periode sama bulan lalu: {{ $num($customers['last_month']) }}</span>
            </div>
        </div>

        <div class="adm-metric-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; gap:0.5rem;">
                <div>
                    <div class="adm-metric-label">Member Aktif</div>
                    <div class="adm-metric-val" style="color:#B8860B;">{{ $num($activeMembers) }} member</div>
                </div>
            </div>
            <div class="adm-metric-foot">
                <span>Kartu membership berstatus aktif</span>
            </div>
        </div>
    </div>

    {{-- 3. Perlu tindakan --}}
    @if ($attention !== [])
        <div class="adm-card" style="padding:1rem 1.25rem; border:1.5px dashed #DFC387; background:#FFFDF7;">
            <div class="adm-card-title" style="margin-bottom:0.5rem;">Perlu Ditindaklanjuti</div>
            <div style="display:flex; flex-wrap:wrap; gap:0.5rem 1.5rem; font-size:0.8125rem; color:#5C410F;">
                @foreach ($attention as $item)
                    <span>
                        &bull; <b style="color:#1F170D;">{{ $item['label'] }}</b>
                        @if ($canSeeMoney && $item['amount'] !== null) ({{ $rp($item['amount']) }}) @endif
                        @if ($item['url'])
                            &middot; <a href="{{ $item['url'] }}" style="color:#8C6418; font-weight:800; text-decoration:underline;">Buka</a>
                        @endif
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    {{-- 4. Grafik uang masuk --}}
    @if ($canSeeMoney)
        <div class="adm-card">
            <div class="adm-card-head">
                <div>
                    <div class="adm-card-title">Uang Masuk Harian (Bersih)</div>
                    <div class="adm-card-sub">
                        <strong style="color:#1F170D;">{{ $num($trend['payments']) }} pembayaran</strong> &bull;
                        <span style="color:#8C6418; font-weight:700;">{{ $rp($trend['total']) }} dalam {{ $trendDays }} hari terakhir</span>
                        &bull; <a href="{{ \App\Filament\Pages\Analytics::getUrl() }}" style="color:#8C6418; font-weight:700; text-decoration:underline;">Analytics lengkap</a>
                    </div>
                </div>
                <div class="adm-tabs">
                    @foreach (\App\Filament\Pages\Dashboard::TREND_OPTIONS as $days => $label)
                        <button type="button" wire:click="setTrendDays({{ $days }})" class="adm-tab-btn {{ $trendDays === $days ? 'active' : '' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>

            @if ($hasTrend)
                <div style="margin-top:1rem; width:100%;">
                    <svg viewBox="0 0 {{ $W }} {{ $H }}" style="width:100%; height:auto; display:block; overflow:visible;" role="img" aria-label="Grafik uang masuk harian">
                        <defs>
                            <linearGradient id="dashGoldArea" x1="0" y1="0" x2="0" y2="1">
                                <stop offset="0%" stop-color="#D4AF37" stop-opacity="0.35" />
                                <stop offset="100%" stop-color="#FAF2DE" stop-opacity="0" />
                            </linearGradient>
                        </defs>
                        @foreach ([0, 0.5, 1] as $f)
                            @php $gv = $minV + ($maxV - $minV) * $f; $gy = $y($gv); @endphp
                            <line x1="{{ $L }}" y1="{{ $gy }}" x2="{{ $R }}" y2="{{ $gy }}" stroke="#EEDBB0" stroke-width="1" stroke-dasharray="4 4" />
                            <text x="{{ $L - 8 }}" y="{{ $gy + 4 }}" text-anchor="end" font-size="11" fill="#9E8555" font-family="monospace">{{ $gv >= 1000000 ? number_format($gv / 1000000, 1, ',', '.').' jt' : ($gv >= 1000 ? number_format($gv / 1000, 0, ',', '.').' rb' : $num($gv)) }}</text>
                        @endforeach
                        <path d="{{ $area }}" fill="url(#dashGoldArea)" />
                        <polyline points="{{ $line }}" fill="none" stroke="#B8860B" stroke-width="3" stroke-linejoin="round" stroke-linecap="round" />
                        @foreach ($pts as $i => $p)
                            <circle cx="{{ round($x($i), 1) }}" cy="{{ round($y($p['money_net']), 1) }}" r="{{ count($pts) > 40 ? 2.5 : 4 }}" fill="#FFFFFF" stroke="#B8860B" stroke-width="2">
                                <title>{{ $p['label'] }}: {{ $rp($p['money_net']) }}{{ $p['refunds'] != 0 ? ' (refund '.$rp($p['refunds']).')' : '' }}</title>
                            </circle>
                            @if ($i % $labelEvery === 0 || $i === count($pts) - 1)
                                <text x="{{ round($x($i), 1) }}" y="{{ $H - 4 }}" text-anchor="middle" font-size="11" fill="#8C754E" font-family="monospace">{{ $p['label'] }}</text>
                            @endif
                        @endforeach
                    </svg>
                </div>
            @else
                <div style="padding:2rem; text-align:center; font-size:0.8125rem; color:#8C7A58;">Belum ada uang masuk dalam {{ $trendDays }} hari terakhir.</div>
            @endif
        </div>
    @endif

    {{-- 5. Jadwal lapangan hari ini --}}
    <div class="adm-card">
        <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.75rem; margin-bottom:1rem;">
            <div>
                <div class="adm-card-title">Jadwal Lapangan Hari Ini</div>
                <div class="adm-card-sub">{{ $schedule->count() }} booking &bull; urut jam mulai</div>
            </div>
            <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                <div style="position:relative; width:100%; max-width:280px;">
                    <div style="position:absolute; top:0; bottom:0; left:0.75rem; display:flex; align-items:center; pointer-events:none; color:#8C6418;">
                        <svg class="adm-svg-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                    </div>
                    <input type="text" wire:model.live.debounce.400ms="search" placeholder="Cari kode booking, nama, HP..." class="adm-search-input" />
                </div>
                @if (\App\Filament\Pages\KelolaPemesanan::canAccess())
                    <a href="{{ \App\Filament\Pages\KelolaPemesanan::getUrl() }}" class="adm-btn-sec" style="text-decoration:none;">Kelola Pemesanan &rarr;</a>
                @endif
            </div>
        </div>

        <div class="adm-table-wrap">
            <table class="adm-table">
                <thead>
                    <tr>
                        <th>Kode Booking</th>
                        <th>Customer</th>
                        <th>Lapangan</th>
                        <th>Jam (WIB)</th>
                        <th>Status</th>
                        <th style="text-align:right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($schedule as $b)
                        @php
                            [$label, $color] = \App\Filament\Pages\Dashboard::statusLabel($b->status);
                            $start = $b->start_time?->copy()->timezone(\App\Services\Finance\LedgerReport::TIMEZONE);
                            $end = $b->end_time?->copy()->timezone(\App\Services\Finance\LedgerReport::TIMEZONE);
                            $isNow = $b->status === 'CHECKED_IN' && $b->start_time?->lte(now()) && $b->end_time?->gt(now());
                        @endphp
                        <tr wire:key="dash-booking-{{ $b->id }}">
                            <td style="font-family:var(--font-mono); font-weight:700; color:#8C6418;">{{ $b->booking_code }}</td>
                            <td>
                                <div style="font-weight:800; color:#1F170D;">{{ $b->user?->name ?? 'Walk-in' }}</div>
                                <div style="font-size:0.625rem; color:#8C7A58;">{{ $b->user?->phone ?? '-' }}</div>
                            </td>
                            <td style="font-weight:700; color:#1F170D;">{{ $b->court?->name ?? '-' }}</td>
                            <td>
                                <div style="font-weight:700; color:#1F170D;">{{ $start?->format('H:i') }} &ndash; {{ $end?->format('H:i') }}</div>
                                @if ($isNow)
                                    <div style="font-size:0.5625rem; color:#047857; font-weight:800; margin-top:2px;">Sedang main</div>
                                @elseif ($start && $start->isFuture())
                                    <div style="font-size:0.5625rem; color:#A68F63; margin-top:2px;">Mulai {{ $start->diffForHumans() }}</div>
                                @endif
                            </td>
                            <td><span class="adm-pill" style="{{ $pillStyle[$color] }} font-weight:700;">{{ $label }}</span></td>
                            <td style="text-align:right; font-family:var(--font-mono); font-weight:800; color:#1F170D;">
                                @if ($canSeeMoney)
                                    {{ $rp($b->total_amount) }}
                                    @if ((float) $b->member_hours_consumed > 0)
                                        <div style="font-size:0.625rem; font-weight:500; color:#8C7A58;">kuota member</div>
                                    @endif
                                @else
                                    <span style="color:#A68F63;">&mdash;</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding:2rem; text-align:center; color:#8C7A58;">
                                {{ trim($search) !== '' ? 'Tidak ada booking hari ini yang cocok dengan pencarian.' : 'Belum ada booking lapangan untuk hari ini.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
