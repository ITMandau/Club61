<x-guest-layout theme="terracotta">
    {{-- Halaman link bio Instagram (Modul 24 Opsi B Tahap 2). Tombol & urutannya diatur dari admin Marketing & Event →
         Link Bio Instagram; tombol yang tujuannya belum diisi tidak tampil. Media sosial dari Konten Website. --}}
    @php
        $icons = [
            'BOOK_COURT' => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
            'LOCATION' => 'M17.657 16.657L13.414 20.9a2 2 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0zM15 11a3 3 0 11-6 0 3 3 0 016 0z',
            'COACHING_WA' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
            'CLUB_WA' => 'M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z',
            'COACHES' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z',
            'IMAGE' => 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',
            'URL' => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1',
        ];
        $venue = \App\Models\Setting\CompanyProfileSetting::venueInfo();
    @endphp

    <div class="w-full max-w-md mx-auto my-auto">
        <div class="rounded-2xl overflow-hidden bg-club-paper border border-club-line shadow-[0_24px_60px_-30px_rgba(79,47,42,0.6)]">
            <div class="relative bg-club-terra-dark text-club-cream px-6 pt-8 pb-7 text-center">
                <img src="{{ asset('images/club-hero.jpg') }}" alt="" class="absolute inset-0 w-full h-full object-cover opacity-30">
                <div class="absolute inset-0" style="background: linear-gradient(180deg, rgba(30,18,15,0.35) 0%, rgba(81,29,24,0.92) 100%);"></div>
                <div class="relative">
                    <img src="{{ asset('images/identity/logo-cream.png').'?v=2' }}" alt="Club 61 Padel &amp; Wellness" class="h-20 w-auto mx-auto">
                    <p class="mt-4 text-sm leading-snug text-club-cream/85">{{ $venue['address'] }}</p>
                    @if ($venue['hours'] !== '')
                        <p class="mt-1 text-xs font-bold uppercase tracking-wider text-club-cream/70">{{ $venue['hours'] }}</p>
                    @endif
                </div>
            </div>

            <div class="p-5 sm:p-6 space-y-3">
                @forelse ($links as $link)
                    <a href="{{ $link['href'] }}" @unless (in_array($link['type'], ['BOOK_COURT', 'COACHES'], true)) target="_blank" rel="noopener noreferrer" @endunless data-link-bio="{{ $link['type'] }}"
                       class="group flex items-center gap-3 w-full px-4 py-3.5 rounded-xl bg-white border border-club-line text-club-terra font-bold text-[15px] shadow-sm hover:bg-club-terra hover:text-club-cream hover:border-club-terra transition-colors">
                        <span class="w-9 h-9 shrink-0 rounded-lg bg-club-cream group-hover:bg-club-cream/15 flex items-center justify-center">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$link['type']] ?? $icons['URL'] }}"/>
                            </svg>
                        </span>
                        <span class="flex-1 text-center pr-9">{{ $link['label'] }}</span>
                    </a>
                @empty
                    <p class="text-center text-sm text-club-brown/70 py-6">Link belum tersedia.</p>
                @endforelse

                @if (! empty($socialLinks))
                    <div class="pt-3 flex items-center justify-center gap-3">
                        @foreach ($socialLinks as $platform => $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" aria-label="{{ ucfirst($platform) }}" class="hover:scale-105 transition-transform">
                                <x-social-icon :platform="$platform" />
                            </a>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
        <p class="mt-4 text-center text-[11px] text-club-brown/60">&copy; {{ date('Y') }} Club 61 Padel &amp; Wellness</p>
    </div>
</x-guest-layout>
