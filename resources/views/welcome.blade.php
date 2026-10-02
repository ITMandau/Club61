<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CLUB 61 - Padel Court Medan</title>
    <link rel="icon" type="image/png" href="{{ asset('images/club61-logo.png') }}">

    <!-- Social share preview (WhatsApp/Facebook/Twitter) -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="Club 61 Padel Court - Medan">
    <meta property="og:description" content="{{ $companyProfile->renderedHeroSubtitle() }}">
    <meta property="og:image" content="{{ asset('images/club61-logo-with-text.png') }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Club 61 Padel Court - Medan">
    <meta name="twitter:description" content="{{ $companyProfile->renderedHeroSubtitle() }}">
    <meta name="twitter:image" content="{{ asset('images/club61-logo-with-text.png') }}">

    <!-- Luxury Typography -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Alex+Brush&family=Cinzel:wght@600;700;800;900&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full font-sans antialiased text-[#1F170D] bg-[#FBF9F5] selection:bg-[#D4AF37] selection:text-[#1E160A] relative overflow-x-hidden flex flex-col justify-between"
      style="background-image: url('{{ asset('images/white-gold-marble.jpg') }}'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed;">

    <!-- Ambient Warm Gold Luxury Lighting -->
    <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
        <div class="absolute -top-32 left-1/4 w-[850px] h-[550px] bg-gradient-to-b from-amber-300/20 via-yellow-500/10 to-transparent blur-3xl rounded-full"></div>
        <div class="absolute -bottom-32 right-1/4 w-[700px] h-[500px] bg-[#D4AF37]/15 blur-3xl rounded-full"></div>
    </div>

    <!-- Top Navigation Header -->
    <header class="sticky top-0 z-50 bg-white/85 backdrop-blur-xl border-b border-[#D4AF37]/40 px-6 sm:px-10 py-4 flex items-center justify-between shadow-sm">
        <div class="flex items-center gap-3">
            <img src="{{ asset('images/club61-logo.png') }}" alt="Club 61 Padel Court" class="w-11 h-11 object-contain rounded-2xl shadow-sm border border-[#E5C378] bg-white p-0.5">
            <div>
                <div class="font-serif text-xl sm:text-2xl font-black tracking-[0.25em] text-[#1F170D] uppercase">
                    CLUB 61
                </div>
                <div class="text-[9px] tracking-[0.35em] text-[#8C6418] font-bold uppercase -mt-0.5">
                    PADEL COURT
                </div>
            </div>
        </div>

        <nav class="hidden lg:flex items-center gap-8 text-xs font-bold uppercase tracking-wider text-[#5A4523]">
            <a href="#facilities" class="hover:text-[#8C6418] transition-colors">{{ __('site.nav_facilities') }}</a>
            <a href="#why-us" class="hover:text-[#8C6418] transition-colors">{{ __('site.nav_why_us') }}</a>
            <a href="#membership" class="hover:text-[#8C6418] transition-colors">{{ __('site.nav_membership') }}</a>
            <a href="#location" class="hover:text-[#8C6418] transition-colors">{{ __('site.nav_location') }}</a>
        </nav>

        <nav class="flex items-center gap-2 sm:gap-4">
            <!-- Toggle Bahasa ID/EN — pilihan disimpan di session (lihat SetLocale middleware
                 & route "lang.switch"), redirect balik ke halaman yang sama supaya posisi
                 tetap. Konten dari CMS ikut berubah lewat localized()/localizedTitle() dkk
                 di model, teks tetap (menu, tombol, judul section) lewat lang/id|en/site.php. -->
            <div class="flex items-center gap-0.5 rounded-full p-1 text-[10px] font-black uppercase tracking-wider"
                 style="background: rgba(0,0,0,0.06); border: 1px solid #D9BE84;">
                <a href="{{ route('lang.switch', 'id') }}"
                   class="px-2.5 py-1 rounded-full transition-all"
                   style="{{ app()->getLocale() === 'id' ? 'background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05;' : 'color: #8C6418;' }}">ID</a>
                <a href="{{ route('lang.switch', 'en') }}"
                   class="px-2.5 py-1 rounded-full transition-all"
                   style="{{ app()->getLocale() === 'en' ? 'background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05;' : 'color: #8C6418;' }}">EN</a>
            </div>

            @auth
                @php
                    $loggedUser = auth()->user();
                    $homeRoute = $loggedUser->roles()->first()?->home_route ?? ($loggedUser->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')) ? '/admin' : '/dashboard');
                    $buttonLabel = match ($homeRoute) {
                        '/admin' => __('site.btn_admin_panel'),
                        '/pos' => __('site.btn_pos_screen'),
                        '/kitchen' => __('site.btn_kitchen_screen'),
                        default => __('site.btn_open_dashboard'),
                    };
                @endphp
                <a href="{{ url($homeRoute) }}"
                   class="px-5 py-2.5 rounded-full text-xs font-black uppercase tracking-wider transition-all shadow-md transform active:scale-95 flex items-center gap-1.5"
                   style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); border: 1.5px solid #FBF0CE; color: #281A05; box-shadow: 0 4px 15px rgba(184, 134, 11, 0.35);">
                    <span>{{ $buttonLabel }}</span>
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </a>
            @else
                <a href="{{ route('login') }}"
                   class="px-5 py-2.5 rounded-full text-xs font-black uppercase tracking-wider transition-all shadow-md transform active:scale-95"
                   style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); border: 1.5px solid #FBF0CE; color: #281A05; box-shadow: 0 4px 15px rgba(184, 134, 11, 0.35);">
                    {{ __('site.btn_login') }}
                </a>
                <a href="{{ route('register') }}"
                   class="hidden sm:inline-flex px-5 py-2.5 rounded-full text-xs font-bold uppercase tracking-wider transition-all shadow-sm"
                   style="background: rgba(255, 255, 255, 0.9); border: 1.5px solid #C59B46; color: #5C410F;">
                    {{ __('site.btn_register') }}
                </a>
            @endauth
        </nav>
    </header>

    <!-- Main Hero Landing — foto venue asli (bukan cuma tekstur marmer polos), overlay gelap
         biar teks putih tetap kebaca, mirip treatment yang sudah dipakai panel kiri halaman
         login (auth/login.blade.php) supaya konsisten satu identitas -->
    <main class="relative z-10 overflow-hidden"
          style="background-image: linear-gradient(180deg, rgba(14,10,4,0.80) 0%, rgba(14,10,4,0.55) 45%, rgba(14,10,4,0.90) 100%), url('{{ asset('images/club-hero.jpg') }}'); background-size: cover; background-position: center;">
        <div class="px-6 sm:px-10 py-16 lg:py-24">
        <div class="max-w-5xl mx-auto text-center space-y-6">
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full text-xs font-bold uppercase tracking-widest shadow-sm backdrop-blur-md"
                 style="background: rgba(0,0,0,0.55); border: 1.5px solid #D4AF37; color: #FFFFFF;">
                <span class="w-2 h-2 rounded-full animate-pulse" style="background-color: #D4AF37; box-shadow: 0 0 8px #D4AF37;"></span>
                <span>{{ $companyProfile->localized('hero_badge_text') }}</span>
            </div>

            <h1 class="text-4xl sm:text-6xl lg:text-7xl font-extrabold tracking-tight leading-[1.1] font-serif" style="color: #FFFFFF; text-shadow: 0 2px 20px rgba(0,0,0,0.9);">
                {{ $companyProfile->localized('hero_headline_line1') }} <br />
                <span class="italic font-normal" style="color: #F0DB9D; text-shadow: 0 0 25px rgba(212,175,55,0.6);">{{ $companyProfile->localized('hero_headline_highlight') }}</span> {{ $companyProfile->localized('hero_headline_line2') }}
            </h1>

            <p class="text-sm sm:text-lg max-w-2xl mx-auto leading-relaxed font-medium" style="color: #EDE1C9; text-shadow: 0 1px 8px rgba(0,0,0,0.7);">
                {{ $companyProfile->renderedHeroSubtitle() }}
            </p>

            <!-- CTA Action Buttons -->
            <div class="flex flex-col sm:flex-row items-center justify-center gap-4 pt-4">
                @auth
                    <a href="{{ route('dashboard') }}" 
                       class="w-full sm:w-auto px-8 py-4 rounded-full font-black text-sm uppercase tracking-wider transition-all transform active:scale-95 shadow-xl flex items-center justify-center gap-2 cursor-pointer"
                       style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); border: 1.5px solid #FBF0CE; color: #281A05; box-shadow: 0 10px 30px rgba(184, 134, 11, 0.4);">
                        <span>{{ __('site.btn_open_member_dashboard') }}</span>
                        <svg class="w-4 h-4 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>

                    <form method="POST" action="{{ route('logout') }}" class="w-full sm:w-auto">
                        @csrf
                        <button type="submit" 
                                class="w-full sm:w-auto px-8 py-4 rounded-full font-bold text-sm uppercase tracking-wider transition-all shadow-md backdrop-blur-md cursor-pointer text-rose-700 bg-rose-50/80 hover:bg-rose-100 border border-rose-200">
                            {{ __('site.btn_logout') }}
                        </button>
                    </form>
                @else
                    <a href="{{ route('login') }}"
                       class="w-full sm:w-auto px-8 py-4 rounded-full font-black text-sm uppercase tracking-wider transition-all transform active:scale-95 shadow-xl flex items-center justify-center gap-2 cursor-pointer"
                       style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); border: 1.5px solid #FBF0CE; color: #281A05; box-shadow: 0 10px 30px rgba(184, 134, 11, 0.4);">
                        <span>{{ __('site.btn_login_portal') }}</span>
                        <svg class="w-4 h-4 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </a>

                    <a href="{{ route('register') }}"
                       class="w-full sm:w-auto px-8 py-4 rounded-full font-bold text-sm uppercase tracking-wider transition-all shadow-md backdrop-blur-md cursor-pointer"
                       style="background: rgba(255, 255, 255, 0.92); border: 1.5px solid #C59B46; color: #5C410F;">
                        {{ __('site.btn_join_vip') }}
                    </a>
                @endauth
            </div>

            <!-- Facility Highlights Grid — sumber datanya sama dengan panel login, diedit lewat
                 Filament menu "Konten Website" (app/Filament/Pages/KelolaKontenWebsite.php).
                 Badge ikon lingkaran ber-gradasi + garis aksen tipis di kiri, biar beda dari
                 kartu kotak generik dan kelihatan sengaja dirancang, bukan template lurus. -->
            @php
                // Foto kartu diambil dari Facilities Showcase dengan judul (ID) yang sama,
                // jadi cukup upload sekali di panel admin, tidak perlu field foto terpisah.
                $cardPhotos = $companyFacilities->mapWithKeys(fn ($f) => [mb_strtolower($f->title) => $f->photoUrl()]);
            @endphp
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-8 max-w-4xl mx-auto text-left">
                @foreach($companyProfile->localizedFacilityCards() as $rawIndex => $card)
                    @php $cardPhoto = $cardPhotos[mb_strtolower($companyProfile->facility_cards[$rawIndex]['title'] ?? '')] ?? null; @endphp
                    <div class="group relative rounded-xl overflow-hidden transition-all hover:-translate-y-0.5"
                         style="background: rgba(255, 255, 255, 0.94); box-shadow: 0 12px 28px -12px rgba(0,0,0,0.45);">
                        @if($cardPhoto)
                            <div class="relative h-20 sm:h-24 overflow-hidden">
                                <img src="{{ $cardPhoto }}" alt="{{ $card['title'] }}" class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105">
                                <div class="absolute inset-0" style="background: linear-gradient(180deg, transparent 40%, rgba(20,14,5,0.45) 100%);"></div>
                            </div>
                        @endif
                        <div class="relative px-4 pb-4 {{ $cardPhoto ? 'pt-6' : 'pt-4' }}">
                            <div class="inline-flex items-center justify-center w-9 h-9 rounded-full {{ $cardPhoto ? 'absolute -top-[18px] left-4' : 'mb-2.5' }}"
                                 style="background: linear-gradient(145deg, #FBF0CE 0%, #D4AF37 100%); box-shadow: 0 3px 10px -2px rgba(184,134,11,0.6); border: 2px solid #FFFFFF;">
                                <x-company-profile.icon :icon-key="$card['icon_key']" class="w-4 h-4" style="color: #3B2B11;" />
                            </div>
                            <div class="font-extrabold text-xs text-[#1F170D] leading-tight">{{ $card['title'] }}</div>
                            <div class="text-[10px] text-[#7A5818] font-medium mt-0.5">{{ $card['subtitle'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Stats Bar — angka nyata dari database, disatukan jadi 1 bar gelap dengan
                 pemisah garis vertikal (bukan 4 kotak terpisah) supaya kontras dengan kartu
                 fasilitas di atasnya & terasa seperti 1 komponen dashboard, bukan kartu yang
                 diulang-ulang -->
            <div class="pt-6 max-w-4xl mx-auto">
                <div class="flex flex-wrap sm:flex-nowrap items-stretch rounded-2xl overflow-hidden backdrop-blur-md"
                     style="background: rgba(10, 7, 3, 0.55); border: 1px solid rgba(212,175,55,0.4);">
                    <div class="flex-1 min-w-[45%] sm:min-w-0 px-5 py-4 text-center sm:border-r" style="border-color: rgba(212,175,55,0.25);">
                        <div class="font-serif font-black text-2xl" style="color: #F0DB9D;">{{ $companyProfile->court_count }}</div>
                        <div class="text-[10px] font-bold uppercase tracking-wider mt-1" style="color: #D9C89E;">{{ __('site.stats_courts') }}</div>
                    </div>
                    <div class="flex-1 min-w-[45%] sm:min-w-0 px-5 py-4 text-center sm:border-r" style="border-color: rgba(212,175,55,0.25);">
                        <div class="font-serif font-black text-2xl" style="color: #F0DB9D;">{{ $venueStats['active_members'] }}+</div>
                        <div class="text-[10px] font-bold uppercase tracking-wider mt-1" style="color: #D9C89E;">{{ __('site.stats_active_members') }}</div>
                    </div>
                    <div class="flex-1 min-w-[45%] sm:min-w-0 px-5 py-4 text-center sm:border-r" style="border-color: rgba(212,175,55,0.25);">
                        <div class="font-serif font-black text-2xl" style="color: #F0DB9D;">{{ $venueStats['sponsor_partners'] }}</div>
                        <div class="text-[10px] font-bold uppercase tracking-wider mt-1" style="color: #D9C89E;">{{ __('site.stats_corporate_partners') }}</div>
                    </div>
                    <div class="flex-1 min-w-[45%] sm:min-w-0 px-5 py-4 text-center">
                        <div class="font-serif font-black text-lg" style="color: #F0DB9D;">{{ $companyProfile->localized('operating_hours_text') }}</div>
                        <div class="text-[10px] font-bold uppercase tracking-wider mt-1" style="color: #D9C89E;">{{ __('site.stats_open_daily') }}</div>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </main>

    @if($companyFacilities->isNotEmpty())
    <!-- Facilities Showcase -->
    <section id="facilities" class="relative z-10 px-6 sm:px-10 py-14 sm:py-20 max-w-[1600px] mx-auto w-full">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <div class="text-[11px] font-black tracking-widest text-[#8C6418] uppercase mb-2">{{ __('site.facilities_eyebrow') }}</div>
            <h2 class="text-2xl sm:text-4xl font-extrabold text-[#1F170D] font-serif">{{ __('site.facilities_headline') }}</h2>
        </div>

        <!-- Layout bento: fasilitas pertama jadi kartu besar (2 baris) di kiri, sisanya bertumpuk
             di kanan. Foto jadi background penuh + teks di atas overlay gelap — sengaja beda dari
             section lain yang pakai kartu putih, supaya halaman tidak terasa satu pola berulang. -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 auto-rows-[20rem] sm:auto-rows-[22rem]">
            @foreach($companyFacilities as $index => $facility)
                <article class="group relative rounded-2xl overflow-hidden shadow-lg {{ $index === 0 ? 'lg:row-span-2' : '' }}"
                         style="border: 1.5px solid #DFC387; background: #1F170D;">
                    @if($facility->photoUrl())
                        <img src="{{ $facility->photoUrl() }}" alt="{{ $facility->localizedTitle() }}"
                             class="absolute inset-0 w-full h-full object-cover transition-transform duration-700 group-hover:scale-105">
                    @else
                        <div class="absolute inset-0" style="background-image: url('{{ asset('images/club61-venue-texture.jpg') }}'); background-size: cover; background-position: center; opacity: 0.35;"></div>
                    @endif
                    <div class="absolute inset-0" style="background: linear-gradient(180deg, rgba(14,10,4,0.05) 0%, rgba(14,10,4,0.35) 45%, rgba(14,10,4,0.92) 100%);"></div>

                    <div class="absolute top-4 left-5 font-serif font-black text-sm tracking-widest" style="color: #F0DB9D; text-shadow: 0 1px 6px rgba(0,0,0,0.6);">
                        {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}
                    </div>

                    <div class="absolute inset-x-0 bottom-0 p-5 sm:p-7">
                        <h3 class="font-serif font-extrabold text-white {{ $index === 0 ? 'text-2xl sm:text-4xl' : 'text-xl sm:text-2xl' }} mb-2">{{ $facility->localizedTitle() }}</h3>
                        <span class="block w-10 h-0.5 mb-3" style="background: linear-gradient(90deg, #F0DB9D, #B38622);"></span>
                        @if($facility->description)
                            <p class="text-sm leading-relaxed mb-4 max-w-md {{ $index === 0 ? '' : 'line-clamp-2' }}" style="color: #EDE1C9;">{{ $facility->localizedDescription() }}</p>
                        @endif
                        @if(!empty($facility->amenities))
                            <div class="flex flex-wrap gap-2">
                                @foreach($facility->localizedAmenities() as $amenity)
                                    <span class="px-3 py-1 rounded-full text-[10px] sm:text-[11px] font-bold backdrop-blur-md"
                                          style="background: rgba(255,255,255,0.12); border: 1px solid rgba(240,219,157,0.45); color: #FBF0CE;">{{ $amenity }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </section>
    @endif

    @if($companyValueProps->isNotEmpty())
    <!-- Kenapa Pilih Club61 -->
    <section id="why-us" class="relative z-10 px-6 sm:px-10 py-14 sm:py-20 max-w-[1600px] mx-auto w-full">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <div class="text-[11px] font-black tracking-widest text-[#8C6418] uppercase mb-2">{{ __('site.why_us_eyebrow') }}</div>
            <h2 class="text-2xl sm:text-4xl font-extrabold text-[#1F170D] font-serif">{{ __('site.why_us_headline') }}</h2>
        </div>

        <div class="flex flex-col lg:flex-row gap-6 lg:gap-8 items-stretch">
            <!-- Panel foto asli venue Club61 (foto entrance/emblem, bukan stock/dummy) supaya
                 section ini ga cuma kotak ikon polos berjajar -->
            <div class="w-full lg:w-[36%] flex-shrink-0 relative rounded-2xl overflow-hidden shadow-lg min-h-[16rem] lg:min-h-0"
                 style="border: 1.5px solid #DFC387;">
                <img src="{{ asset('images/club61-reception.jpg') }}" alt="Resepsionis Club61 Padel Court"
                     class="absolute inset-0 w-full h-full object-cover">
                <div class="absolute inset-0" style="background: linear-gradient(0deg, rgba(14,10,4,0.75) 0%, rgba(14,10,4,0.05) 55%, transparent 100%);"></div>
                <div class="absolute left-5 right-5 bottom-5">
                    <div class="text-[10px] font-black tracking-widest uppercase mb-1" style="color: #F0DB9D;">{{ __('site.why_us_photo_eyebrow') }}</div>
                    <div class="text-white font-serif font-bold text-lg leading-tight">{{ __('site.why_us_photo_caption') }}</div>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 w-full lg:w-[64%]">
                @foreach($companyValueProps as $prop)
                    <div class="p-5 rounded-2xl backdrop-blur-md shadow-md text-center sm:text-left"
                         style="background: rgba(255, 255, 255, 0.9); border: 1.5px solid #DFC387;">
                        <div class="inline-flex items-center justify-center w-9 h-9 rounded-xl mb-3" style="background: #FAF2DE; border: 1.5px solid #D9BE84; color: #8C6418;">
                            <x-company-profile.icon :icon-key="$prop->icon_key" class="w-4 h-4" />
                        </div>
                        <div class="font-extrabold text-xs sm:text-sm text-[#1F170D] mb-1">{{ $prop->localizedTitle() }}</div>
                        @if($prop->description)
                            <div class="text-[11px] text-[#7A5818] font-medium leading-relaxed">{{ $prop->localizedDescription() }}</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </section>
    @endif

    @if($membershipPlans->isNotEmpty())
    <!-- Membership Teaser -->
    <section id="membership" class="relative z-10 px-6 sm:px-10 py-14 sm:py-20 max-w-[1600px] mx-auto w-full">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <div class="text-[11px] font-black tracking-widest text-[#8C6418] uppercase mb-2">{{ __('site.membership_eyebrow') }}</div>
            <h2 class="text-2xl sm:text-4xl font-extrabold text-[#1F170D] font-serif">{{ __('site.membership_headline') }}</h2>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            {{-- Urutan & nama fasilitas dari Master Fasilitas; fasilitas nonaktif tidak ditampilkan. --}}
            @php $facilityCatalog = app(\App\Services\Membership\MembershipFacilityService::class)->all(); @endphp
            @foreach($membershipPlans as $planIndex => $plan)
                @php
                    $orderedBenefits = $plan->benefits
                        ->filter(fn ($b) => $facilityCatalog[$b->facility]['is_active'] ?? true)
                        ->sortBy(fn ($b) => $facilityCatalog[$b->facility]['sort_order'] ?? 999)->values();
                    $isFlagship = $planIndex === $membershipPlans->count() - 1 && $membershipPlans->count() > 1;
                @endphp
                <div class="group relative rounded-2xl overflow-hidden backdrop-blur-md shadow-md flex flex-col hover:-translate-y-1 transition-all"
                     style="background: rgba(255, 255, 255, 0.95); border: 1.5px solid {{ $isFlagship ? '#D4AF37' : '#DFC387' }}; {{ $isFlagship ? 'box-shadow: 0 16px 36px -14px rgba(184,134,11,0.45);' : '' }}">
                    <span class="absolute top-0 left-0 right-0 h-1.5" style="background: linear-gradient(90deg, #F0DB9D 0%, #D4AF37 50%, #B38622 100%);"></span>

                    <div class="p-6 flex flex-col flex-1">
                        <div class="inline-flex items-center justify-center w-10 h-10 rounded-xl mb-3 transition-transform group-hover:scale-105"
                             style="background: linear-gradient(145deg, #FBF0CE 0%, #D4AF37 100%); box-shadow: 0 3px 10px -2px rgba(184,134,11,0.5);">
                            <x-company-profile.icon icon-key="membership" class="w-5 h-5" style="color: #3B2B11;" />
                        </div>
                        <div class="font-extrabold text-lg text-[#1F170D] font-serif mb-1">{{ $plan->name }}</div>
                        <div class="font-serif font-black text-2xl mb-1" style="color: #8C6418;">
                            Rp{{ number_format((float) $plan->price, 0, ',', '.') }}
                        </div>
                        <div class="text-[11px] text-[#7A5818] font-medium mb-4 pb-4" style="border-bottom: 1px dashed #DFC387;">{{ __('site.membership_valid_days', ['days' => $plan->duration_days]) }}</div>

                        @if($orderedBenefits->isNotEmpty())
                            <ul class="space-y-2.5 mb-5">
                                @foreach($orderedBenefits as $benefit)
                                    <li class="text-xs text-[#5A4523] font-semibold flex items-center gap-2.5">
                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full flex-shrink-0" style="background: #FAF2DE; border: 1px solid #D9BE84;">
                                            <svg class="w-2.5 h-2.5" style="color: #8C6418;" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" /></svg>
                                        </span>
                                        {{ $benefit->describe() }}
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <a href="{{ route('register') }}"
                           class="mt-auto inline-flex items-center justify-center px-5 py-2.5 rounded-full font-black text-xs uppercase tracking-wider transition-all shadow-md"
                           style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); border: 1.5px solid #FBF0CE; color: #281A05;">
                            {{ __('site.membership_cta') }}
                        </a>
                    </div>
                </div>
            @endforeach
        </div>
    </section>
    @endif

    @if($companyProfile->whatsapp_number)
    <!-- Sponsor Corporate Callout — background foto asli interior venue (bukan gradient
         polos) supaya kesan premium, dipotong dari foto venue asli & sudah dibuang bagian
         "coming soon"-nya karena situs ini sudah live -->
    <section class="relative z-10 px-6 sm:px-10 max-w-[1600px] mx-auto w-full">
        <div class="relative rounded-2xl p-8 sm:p-10 flex flex-col sm:flex-row items-center justify-between gap-6 text-center sm:text-left overflow-hidden"
             style="background-image: linear-gradient(120deg, rgba(20,14,5,0.92) 0%, rgba(31,23,13,0.88) 55%, rgba(59,43,17,0.85) 100%), url('{{ asset('images/club61-venue-texture.jpg') }}'); background-size: cover; background-position: center; border: 1.5px solid #D4AF37;">
            <div>
                <div class="text-[11px] font-black tracking-widest uppercase mb-2" style="color: #E5C378;">{{ __('site.sponsor_eyebrow') }}</div>
                <h3 class="text-xl sm:text-2xl font-extrabold text-white font-serif mb-1">{{ __('site.sponsor_headline') }}</h3>
                <p class="text-sm max-w-lg" style="color: #D9C89E;">{{ __('site.sponsor_description') }}</p>
            </div>
            <a href="https://wa.me/{{ preg_replace('/\D/', '', $companyProfile->whatsapp_number) }}?text={{ urlencode(__('site.sponsor_whatsapp_message')) }}"
               target="_blank" rel="noopener"
               class="flex-shrink-0 inline-flex items-center gap-2 px-6 py-3 rounded-full font-black text-sm uppercase tracking-wider transition-all transform active:scale-95 shadow-xl"
               style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); border: 1.5px solid #FBF0CE; color: #281A05;">
                {{ __('site.sponsor_cta') }}
            </a>
        </div>
    </section>
    @endif

    <!-- Lokasi & Kontak — peta digenerate otomatis dari alamat (embed Google Maps tanpa API
         key), jadi tidak perlu staf cari & tempel URL embed manual di panel admin -->
    <section id="location" class="relative z-10 px-6 sm:px-10 py-14 sm:py-20 max-w-[1400px] mx-auto w-full">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <div class="text-[11px] font-black tracking-widest text-[#8C6418] uppercase mb-2">{{ __('site.location_eyebrow') }}</div>
            <h2 class="text-2xl sm:text-4xl font-extrabold text-[#1F170D] font-serif">{{ __('site.location_headline') }}</h2>
        </div>

        @php
            // Jaga-jaga kedua: kalau maps_embed_url ternyata bukan URL valid (misalnya
            // ke-isi teks alamat biasa, bukan link — pernah kejadian dan bikin iframe gagal
            // dimuat "refused to connect"), abaikan dan pakai peta hasil generate dari alamat.
            $mapsUrl = $companyProfile->maps_embed_url && filter_var($companyProfile->maps_embed_url, FILTER_VALIDATE_URL)
                ? $companyProfile->maps_embed_url
                : 'https://www.google.com/maps?q='.urlencode($companyProfile->address_line).'&output=embed';
        @endphp

        <div class="text-center max-w-xl mx-auto mb-8">
            <p class="text-sm text-[#5A4523] mb-1">{{ $companyProfile->address_line }}</p>
            <p class="text-sm text-[#5A4523] mb-5">{{ $companyProfile->localized('operating_hours_text') }}</p>

            @if($companyProfile->whatsapp_number)
                <a href="https://wa.me/{{ preg_replace('/\D/', '', $companyProfile->whatsapp_number) }}" target="_blank" rel="noopener"
                   class="inline-flex items-center justify-center gap-2 px-6 py-3 rounded-full font-black text-xs uppercase tracking-wider transition-all transform active:scale-95 shadow-md"
                   style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); border: 1.5px solid #FBF0CE; color: #281A05;">
                    {{ __('site.location_whatsapp_cta') }}
                </a>
            @endif
        </div>

        <div class="max-w-4xl mx-auto rounded-2xl overflow-hidden shadow-xl" style="border: 1.5px solid #DFC387;">
            <iframe
                src="{{ $mapsUrl }}"
                width="100%" height="480" style="border:0; display:block;" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
        </div>
    </section>

    <!-- Bottom Footer -->
    <footer class="relative z-10 bg-white/75 backdrop-blur-md border-t border-[#D4AF37]/30 px-6 py-8 text-center text-xs text-[#7A643E]">
        <img src="{{ asset('images/club61-logo.png') }}" alt="Club 61 Padel Court" class="w-10 h-10 object-contain rounded-xl shadow-sm border border-[#E5C378] bg-white p-0.5 mx-auto mb-4">

        @if($companyProfile->footer_tagline)
            <p class="text-sm text-[#5A4523] font-medium mb-3 max-w-md mx-auto">{{ $companyProfile->localized('footer_tagline') }}</p>
        @endif

        @php $social = $companyProfile->footer_social_links ?: []; @endphp
        @if(!empty($social))
            <div class="flex items-center justify-center gap-4 mb-4">
                @foreach($social as $platform => $url)
                    <a href="{{ $url }}" target="_blank" rel="noopener" class="text-[#8C6418] hover:text-[#B8860B] font-bold text-[11px] uppercase tracking-wider">{{ $platform }}</a>
                @endforeach
            </div>
        @endif

        <p>&copy; {{ date('Y') }} Club 61 Padel Court. {{ $companyProfile->address_line }}</p>
    </footer>

</body>
</html>