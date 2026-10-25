{{-- Riwayat Jadwal Tetap Mingguan: per tanggal asli × lapangan (dibuat / dilewati / gagal). --}}
@php
    $statusLabel = [
        'BOOKED' => ['Dibuat', '#047857'],
        'SKIPPED' => ['Dilewati', '#6B7280'],
        'FAILED' => ['Gagal', '#B91C1C'],
        'PENDING' => ['Diproses', '#B45309'],
    ];
    $bookingStatus = [
        'PAID' => 'Lunas (kuota)', 'CHECKED_IN' => 'Check-in', 'COMPLETED' => 'Selesai',
        'CANCELLED' => 'Dibatalkan', 'REFUND_PENDING' => 'Refund diajukan', 'REFUNDED' => 'Refund', 'FORFEITED' => 'Hangus',
    ];
@endphp
<div style="font-size:0.8rem; color:#4F2F2A;">
    <div style="display:flex; flex-wrap:wrap; gap:1rem; margin-bottom:0.75rem;">
        <span>Pemilik: <b>{{ $schedule->ownerLabel() }}</b></span>
        <span>{{ $schedule->scheduleLabel() }} · <b>{{ $schedule->hoursPerWeek() }} jam/minggu</b></span>
    </div>

    @if ($rows->isEmpty())
        <p style="color:#6B7280;">Belum ada booking yang dibuat.</p>
    @else
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; min-width:520px;">
                <thead>
                    <tr style="text-align:left; color:#662721; font-size:0.7rem; text-transform:uppercase;">
                        <th style="padding:0.4rem; border-bottom:1.5px solid #E6DAC0;">Tanggal</th>
                        <th style="padding:0.4rem; border-bottom:1.5px solid #E6DAC0;">Lapangan</th>
                        <th style="padding:0.4rem; border-bottom:1.5px solid #E6DAC0;">Status</th>
                        <th style="padding:0.4rem; border-bottom:1.5px solid #E6DAC0;">Booking</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $date => $items)
                        @foreach ($items as $i => $o)
                            @php [$label, $color] = $statusLabel[$o->status] ?? [$o->status, '#4F2F2A']; @endphp
                            <tr style="border-bottom:1px solid #F1E8D5; vertical-align:top;">
                                <td style="padding:0.4rem; white-space:nowrap;">{{ $i === 0 ? $o->occurrence_date->translatedFormat('D, d M Y') : '' }}</td>
                                <td style="padding:0.4rem;">{{ $o->court?->name ?? '-' }}</td>
                                <td style="padding:0.4rem;">
                                    <b style="color:{{ $color }};">{{ $label }}</b>
                                    @if ($o->note)
                                        <div style="color:#6B7280; overflow-wrap:anywhere;">{{ $o->note }}</div>
                                    @endif
                                </td>
                                <td style="padding:0.4rem;">
                                    @if ($o->booking)
                                        <span style="font-family:monospace;">{{ $o->booking->booking_code }}</span>
                                        <div style="color:#6B7280;">
                                            {{ $bookingStatus[$o->booking->status] ?? $o->booking->status }}
                                            @if ($o->booking->booking_date->toDateString() !== $o->occurrence_date->toDateString())
                                                · dipindah ke {{ $o->booking->booking_date->translatedFormat('d M') }} {{ $o->booking->start_time->format('H:i') }}
                                            @endif
                                        </div>
                                    @else
                                        <span style="color:#9CA3AF;">-</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
