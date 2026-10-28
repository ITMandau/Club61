{{-- Modal "Log akses" di Karyawan & Akses → Panduan: 100 pembukaan terakhir (sekali per IP per 10 menit). --}}
@if ($logs->isEmpty())
    <p class="py-6 text-center text-sm text-gray-500">Link panduan ini belum pernah dibuka.</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500">
                    <th class="py-2 pr-3 font-semibold">Waktu</th>
                    <th class="py-2 pr-3 font-semibold">IP</th>
                    <th class="py-2 font-semibold">Perangkat</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($logs as $log)
                    <tr class="border-b border-gray-100 align-top">
                        <td class="whitespace-nowrap py-2 pr-3 text-gray-800">{{ $log->viewed_at->timezone('Asia/Jakarta')->locale('id')->translatedFormat('d M Y, H:i') }}</td>
                        <td class="whitespace-nowrap py-2 pr-3 font-mono text-xs text-gray-700">{{ $log->ip ?? '—' }}</td>
                        <td class="py-2 text-xs text-gray-500">{{ \Illuminate\Support\Str::limit($log->user_agent ?? '—', 80) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-3 text-xs text-gray-400">Dicatat sekali per IP per 10 menit · disimpan {{ \App\Models\Staff\GuideAccessLog::KEEP_DAYS }} hari.</p>
@endif
