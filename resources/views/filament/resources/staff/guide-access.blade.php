{{-- Modal "Log akses" di Karyawan & Akses → Panduan: ringkasan + 100 pembukaan terakhir (sekali per IP per 10 menit).
     Gaya inline (sama dengan Riwayat Jadwal Tetap): kelas Tailwind bebas tidak ikut ter-build di tema panel. --}}
@php
    $th = 'padding:0.5rem 0.6rem; border-bottom:1.5px solid #E6DAC0; text-align:left; font-size:0.68rem; font-weight:700; letter-spacing:0.06em; text-transform:uppercase; color:#662721;';
    $td = 'padding:0.6rem; vertical-align:top;';
    $muted = 'color:#8A7A72; font-size:0.75rem; margin-top:0.1rem;';
    $chip = 'display:inline-flex; align-items:baseline; gap:0.3rem; padding:0.35rem 0.7rem; border-radius:999px; background:#F7F1E9; border:1px solid #EADFD2;';
    $last = $logs->first()?->viewed_at?->timezone('Asia/Jakarta')->locale('id');
@endphp
<div style="font-size:0.85rem; color:#4F2F2A;">
    @if ($logs->isEmpty())
        <div style="padding:1.75rem 0; text-align:center; color:#8A7A72;">Link panduan ini belum pernah dibuka.</div>
    @else
        <div style="display:flex; flex-wrap:wrap; gap:0.5rem; margin-bottom:1rem;">
            <span style="{{ $chip }}"><b style="font-size:0.95rem;">{{ $total }}×</b> dibuka</span>
            <span style="{{ $chip }}"><b style="font-size:0.95rem;">{{ $ips }}</b> IP berbeda</span>
            <span style="{{ $chip }}">Terakhir <b>{{ $last?->translatedFormat('d M Y, H:i') }} WIB</b></span>
        </div>

        <div style="overflow-x:auto; max-height:26rem; overflow-y:auto; border:1px solid #F1E8D5; border-radius:0.6rem;">
            <table style="width:100%; border-collapse:collapse; min-width:460px;">
                <thead style="position:sticky; top:0; background:#FFFFFF;">
                    <tr>
                        <th style="{{ $th }} width:30%;">Waktu</th>
                        <th style="{{ $th }}">Perangkat</th>
                        <th style="{{ $th }} width:28%;">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        @php
                            $at = $log->viewed_at->timezone('Asia/Jakarta')->locale('id');
                            [$type, $device] = $log->device();
                        @endphp
                        <tr style="border-bottom:1px solid #F1E8D5; {{ $loop->odd ? '' : 'background:#FCFAF7;' }}">
                            <td style="{{ $td }} white-space:nowrap;">
                                <div style="font-weight:600;">{{ $at->translatedFormat('d M Y') }}</div>
                                <div style="{{ $muted }}">{{ $at->format('H:i') }} WIB</div>
                            </td>
                            <td style="{{ $td }}" title="{{ $log->user_agent }}">
                                <div style="font-weight:600;">{{ $device }}</div>
                                <div style="{{ $muted }}">{{ $type }}</div>
                            </td>
                            <td style="{{ $td }} white-space:nowrap; font-family:ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size:0.8rem;">{{ $log->ip ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:0.75rem; font-size:0.75rem; color:#8A7A72;">
            Dicatat sekali per IP per 10 menit · disimpan {{ \App\Models\Staff\GuideAccessLog::KEEP_DAYS }} hari{{ $total > $logs->count() ? ' · menampilkan '.$logs->count().' terakhir' : '' }}. Arahkan kursor ke perangkat untuk melihat detail aslinya.
        </div>
    @endif
</div>
