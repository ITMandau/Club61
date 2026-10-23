<x-app-layout>
    {{-- Profil coach (Modul 24 Opsi B Tahap 2) — informasi saja. Customer tidak booking coach dari sini: tombolnya membuka
         WhatsApp admin coaching, lalu resepsionis yang membuat booking di POS. Data dari App\Services\Coaching\CoachDirectory
         (hanya kolom publik: no. WA / email / catatan coach tidak pernah ikut). --}}
    <div class="py-6 sm:py-8 text-[#4F2F2A]">
        <div class="w-full px-4 sm:px-8 lg:px-12 2xl:px-16 space-y-6">

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white/95 p-5 rounded-xl border border-[#E6DAC0] shadow-sm">
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <h1 class="font-display font-black text-xl sm:text-2xl text-[#4F2F2A]">Coaching at Club 61</h1>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-[#F7F0DB] text-[#662721] border border-[#E6DAC0]">Padel</span>
                    </div>
                    <p class="text-xs sm:text-sm text-[#7A5A52] mt-1 max-w-2xl">Meet our coaches. To book a session or ask about coaching packages, chat with our coaching admin &mdash; we will arrange the court and schedule for you.</p>
                </div>
                @if ($adminWhatsappUrl)
                    <a href="{{ $adminWhatsappUrl }}" target="_blank" rel="noopener noreferrer" data-coaching-admin-wa
                       class="shrink-0 inline-flex items-center justify-center gap-2 px-5 py-3 rounded-lg bg-[#662721] hover:bg-[#511D18] text-[#F7F0DB] font-black text-xs uppercase tracking-wider shadow-md transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
                        Chat Admin Coaching
                    </a>
                @endif
            </div>

            @if (empty($coaches))
                <div class="bg-white/95 p-8 rounded-xl border border-[#E6DAC0] shadow-sm text-center space-y-2">
                    <h2 class="font-display font-black text-lg text-[#4F2F2A]">Coach profiles are coming soon</h2>
                    <p class="text-sm text-[#7A5A52]">{{ $adminWhatsappUrl ? 'In the meantime, chat with our coaching admin for schedules and packages.' : 'Please ask our frontdesk for coaching schedules and packages.' }}</p>
                </div>
            @else
                <div class="grid gap-4 sm:gap-5" style="grid-template-columns: repeat(auto-fill, minmax(min(100%, 250px), 1fr));">
                    @foreach ($coaches as $coach)
                        @php($coachWa = $directory->adminWhatsappUrl($coach['name']))
                        <article data-coach-card class="min-w-0 flex flex-col bg-white rounded-xl border border-[#E6DAC0] shadow-sm overflow-hidden">
                            {{-- Foto selalu utuh (object-contain, tidak terpotong apa pun rasionya); sisa bingkai diisi versi blur
                                 foto yang sama. Tanpa foto: area avatar lebih pendek supaya kartu tidak kosong memanjang di HP. --}}
                            <div class="relative overflow-hidden bg-[#F7F0DB] {{ $coach['photo_url'] ? 'h-80 sm:h-72' : 'h-32 sm:h-40' }}">
                                @if ($coach['photo_url'])
                                    <img src="{{ $coach['photo_url'] }}" alt="" aria-hidden="true" loading="lazy" class="absolute inset-0 w-full h-full object-cover scale-110 blur-xl opacity-60">
                                    <div class="absolute inset-0 bg-gradient-to-b from-[#4F2F2A]/10 to-[#4F2F2A]/35"></div>
                                    <img src="{{ $coach['photo_url'] }}" alt="{{ $coach['name'] }}" loading="lazy" class="relative w-full h-full object-contain drop-shadow-[0_12px_20px_rgba(30,18,15,0.35)]">
                                @else
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <span class="w-20 h-20 rounded-full bg-[#662721] text-[#F7F0DB] font-display font-black text-2xl flex items-center justify-center">{{ $coach['initials'] }}</span>
                                    </div>
                                @endif
                                <span class="absolute top-3 left-3 px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase tracking-wider bg-[#662721] text-[#F7F0DB]">{{ $coach['title_label'] }}</span>
                            </div>

                            {{-- overflow-wrap:anywhere: teks panjang tanpa spasi (mis. "aaaa…") dipatahkan, tidak keluar kartu. --}}
                            <div class="flex-1 flex flex-col min-w-0 p-4 sm:p-5 [overflow-wrap:anywhere]">
                                <h2 class="font-display font-black text-lg leading-tight text-[#4F2F2A]">{{ $coach['name'] }}</h2>
                                <p class="mt-1 text-xs font-bold text-[#7A5A52]">{{ $coach['sport'] }}{{ $coach['academy'] ? ' · '.$coach['academy'] : '' }}</p>

                                @if ($coach['motto'])
                                    <blockquote data-coach-motto class="mt-3 pl-3 border-l-[3px] border-[#662721] font-display italic text-[15px] leading-snug text-[#662721] whitespace-pre-line">&ldquo;{{ $coach['motto'] }}&rdquo;</blockquote>
                                @endif

                                @if (! empty($coach['highlights']))
                                    <ul class="mt-3 space-y-1.5">
                                        @foreach ($coach['highlights'] as $point)
                                            <li class="flex items-start gap-2 text-sm leading-snug">
                                                <svg class="w-4 h-4 mt-0.5 shrink-0 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                                                <span class="min-w-0">{{ $point }}</span>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif

                                @if ($coachWa)
                                    <a href="{{ $coachWa }}" target="_blank" rel="noopener noreferrer"
                                       class="mt-auto pt-4">
                                        <span class="flex items-center justify-center gap-2 w-full px-4 py-2.5 rounded-lg bg-[#F7F0DB] hover:bg-[#662721] hover:text-[#F7F0DB] border border-[#E6DAC0] text-[#662721] font-bold text-xs transition-colors">
                                            Ask about {{ $coach['name'] }}
                                        </span>
                                    </a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
