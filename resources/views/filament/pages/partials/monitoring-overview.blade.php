{{-- Monitoring Lapangan → tampilan Mingguan / Bulanan + Rekap Pemain (Modul 28 Batch 3). Hanya baca; klik booking membuka
     panel detail yang sama dengan tampilan harian. Data dari App\Services\Padel\ScheduleOverview. --}}
@php
    $typeLabels = \App\Services\Padel\ScheduleOverview::TYPES;
@endphp

@if ($viewMode === 'week')
    <div class="c61-card">
        <div class="c61-card-head">
            <div>
                <div class="c61-card-title">Jadwal Mingguan · {{ $periodLabel }}</div>
                <div class="c61-card-sub">Siapa booking di jam berapa selama seminggu. Klik nama untuk detail, klik tanggal untuk tampilan harian.</div>
            </div>
        </div>

        @if ($bookings->isEmpty())
            <div class="c61-empty" style="padding: 2rem 1rem;">Tidak ada booking pada minggu ini{{ $search !== '' || $typeFilter !== 'all' ? ' untuk filter yang dipilih' : '' }}.</div>
        @else
            <div class="cmd-matrix-scroll">
                <table class="cmd-week">
                    <thead>
                        <tr>
                            <th class="cmd-week-hr">Jam</th>
                            @foreach ($days as $day)
                                <th class="cmd-week-day {{ $day['is_today'] ? 'is-today' : '' }}">
                                    <button type="button" wire:click="openDay('{{ $day['date'] }}')" title="Buka tampilan harian">{{ $day['label'] }}</button>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($hours as $hour)
                            <tr>
                                <td class="cmd-week-hr">{{ sprintf('%02d:00', $hour) }}</td>
                                @foreach ($days as $day)
                                    <td class="cmd-week-cell {{ $day['is_today'] ? 'is-today' : '' }}">
                                        @foreach ($cells[$day['date']][$hour] ?? [] as $chip)
                                            <button type="button" wire:click="inspectBooking('{{ $chip['id'] }}')"
                                                class="cmd-chip t-{{ $chip['type'] }} {{ $chip['continued'] ? 'is-cont' : '' }} {{ $chip['pending'] ? 'is-pending' : '' }} {{ $chip['completed'] ? 'is-done' : '' }}"
                                                title="{{ $chip['name'] }} · {{ $chip['court'] }} · {{ $chip['type_label'] }}{{ $chip['pending'] ? ' · menunggu bayar' : '' }}">
                                                <small>{{ $chip['court'] }}</small> {{ $chip['name'] }}
                                            </button>
                                        @endforeach
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@else
    <div class="c61-card">
        <div class="c61-card-head">
            <div>
                <div class="c61-card-title">Kalender Bulanan · {{ $periodLabel }}</div>
                <div class="c61-card-sub">Jumlah booking & tingkat keterisian lapangan per hari. Klik tanggal untuk melihat daftar booking.</div>
            </div>
        </div>

        <div class="c61-card-body">
            <div class="cmd-month">
                @foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $dow)
                    <div class="cmd-month-dow">{{ $dow }}</div>
                @endforeach
                @foreach ($weeks as $week)
                    @foreach ($week as $cell)
                        <button type="button" wire:click="selectMonthDay('{{ $cell['date'] }}')"
                            class="cmd-mday {{ $cell['in_month'] ? '' : 'is-out' }} {{ $cell['is_today'] ? 'is-today' : '' }} {{ $monthDay === $cell['date'] ? 'is-selected' : '' }}">
                            <span class="cmd-mday-num">{{ $cell['day'] }}</span>
                            @if ($cell['count'] > 0)
                                <span class="cmd-mday-count">{{ $cell['count'] }} booking</span>
                                <span class="cmd-mday-bar"><i style="width: {{ $cell['rate'] }}%;"></i></span>
                                <span class="cmd-mday-rate">{{ $cell['rate'] }}% terisi</span>
                            @else
                                <span class="cmd-mday-empty">Kosong</span>
                            @endif
                        </button>
                    @endforeach
                @endforeach
            </div>

            @if ($monthDay && $dayList !== null)
                <div class="cmd-daylist">
                    <div class="cmd-daylist-head">
                        <b>{{ \Carbon\Carbon::parse($monthDay)->translatedFormat('l, d F Y') }}</b>
                        <span>{{ count($dayList) }} booking</span>
                        <button type="button" wire:click="openDay('{{ $monthDay }}')" class="c61-btn c61-btn-ghost c61-btn-sm">Buka Harian</button>
                    </div>
                    @forelse ($dayList as $row)
                        <button type="button" wire:click="inspectBooking('{{ $row['id'] }}')" class="cmd-daylist-row">
                            <span class="c61-mono">{{ $row['time'] }}</span>
                            <span>{{ $row['court'] }}</span>
                            <span class="cmd-daylist-name">{{ $row['name'] }}</span>
                            <span class="cmd-chip t-{{ $row['type'] }}" style="width: auto; margin: 0;">{{ $row['type_label'] }}</span>
                            <span class="cmd-daylist-status">{{ $row['pending'] ? 'Menunggu bayar' : ($row['completed'] ? 'Selesai' : ($row['status'] === 'CHECKED_IN' ? 'Sedang main' : 'Lunas')) }}</span>
                        </button>
                    @empty
                        <div class="c61-empty" style="padding: 1rem;">Tidak ada booking pada tanggal ini.</div>
                    @endforelse
                </div>
            @endif
        </div>
    </div>
@endif

{{-- Rekap Pemain: siapa saja yang main dalam periode ini. --}}
<div class="c61-card">
    <div class="c61-card-head">
        <div>
            <div class="c61-card-title">Rekap Pemain · {{ $periodLabel }}</div>
            <div class="c61-card-sub">{{ count($players) }} pemain · urut dari jam main terbanyak.</div>
        </div>
    </div>
    @if ($players === [])
        <div class="c61-empty" style="padding: 1.5rem 1rem;">Belum ada pemain pada periode ini.</div>
    @else
        <div class="c61-table-wrap">
            <table class="c61-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Jenis</th>
                        <th style="text-align: right;">Booking</th>
                        <th style="text-align: right;">Total Jam</th>
                        <th>Terakhir Main</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($players as $player)
                        <tr>
                            <td>
                                <div style="font-weight: 700;">{{ $player['name'] }}</div>
                                @if ($player['phone'])
                                    <div class="c61-mono" style="font-size: 0.6875rem; color: var(--c-muted);">{{ $player['phone'] }}</div>
                                @endif
                            </td>
                            <td>{{ $player['types'] }}</td>
                            <td style="text-align: right;">{{ $player['count'] }}</td>
                            <td style="text-align: right; font-weight: 700;">{{ rtrim(rtrim(number_format($player['hours'], 1, ',', '.'), '0'), ',') }} jam</td>
                            <td>{{ $player['last'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
