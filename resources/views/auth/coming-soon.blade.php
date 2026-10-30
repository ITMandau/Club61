<x-guest-layout theme="terracotta">
    {{-- Mode Coming Soon (App\Support\ComingSoon, COMING_SOON_UNTIL). Gaya sama dengan login.blade.php.
         Customer: hitung mundur ke grand opening. Staf: tombol "Login Staf" membuka form login biasa (POST /login yang sama;
         akun customer ditolak di AuthenticatedSessionController). Hitung mundur habis → halaman dimuat ulang jadi login normal. --}}
    @php($loginError = $errors->first('email') ?: $errors->first('password'))
    @php($loginStatus = session('status'))
    @php($formOpen = $errors->any() || old('email'))
    {{-- Tanggal mengikuti bahasa yang dipilih (ComingSoon::dateLabel() selalu Bahasa Indonesia untuk pesan server). --}}
    @php($dateLabel = \App\Support\ComingSoon::until()?->locale(app()->getLocale())->translatedFormat('l, j F Y') ?? '')

    <div class="w-full max-w-6xl mx-auto my-auto">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 lg:gap-6 items-stretch">

            {{-- ============ PANEL VENUE + HITUNG MUNDUR ============ --}}
            <div class="lg:col-span-7 relative overflow-hidden rounded-xl bg-club-terra-dark text-club-cream min-h-[520px] lg:min-h-[600px] flex flex-col">
                <img src="{{ asset('images/club-hero.jpg') }}" alt="Club 61 Padel &amp; Wellness" class="absolute inset-0 w-full h-full object-cover">
                <div class="absolute inset-0" style="background: linear-gradient(180deg, rgba(30,18,15,0.62) 0%, rgba(30,18,15,0.4) 40%, rgba(30,18,15,0.92) 100%);"></div>

                <div class="relative z-10 p-6 sm:p-8 flex flex-wrap items-start justify-between gap-4">
                    <img src="{{ asset('images/identity/logo-cream.png').'?v=2' }}" alt="Club 61 Padel & Wellness" class="h-[80px] sm:h-[88px] w-auto">
                    <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full border border-club-cream/30 bg-[rgba(30,18,15,0.55)] text-[11px] font-bold uppercase tracking-wider text-club-cream">
                        <span class="w-2 h-2 rounded-full bg-[#E3B873]"></span>
                        {{ __('account.coming.grand_opening') }} &bull; {{ \App\Support\ComingSoon::until()?->locale(app()->getLocale())->translatedFormat('j M Y') }}
                    </span>
                </div>

                <div class="relative z-10 px-6 sm:px-8 mt-auto pb-7">
                    <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-club-cream/80 mb-3">{{ __('account.coming.countdown_kicker') }}</div>
                    <h2 class="font-display font-semibold text-3xl sm:text-4xl lg:text-[2.6rem] leading-[1.1]">
                        {{ __('account.coming.headline') }} <span class="italic text-club-cream/80">{{ __('account.coming.headline_em') }}</span>
                    </h2>

                    <div id="cs-countdown" data-seconds="{{ \App\Support\ComingSoon::secondsLeft() }}" role="timer" aria-label="{{ __('account.coming.countdown_aria') }}"
                         class="grid grid-cols-4 gap-2 sm:gap-2.5 mt-6">
                        @foreach(['days' => __('account.coming.days'), 'hours' => __('account.coming.hours'), 'minutes' => __('account.coming.minutes'), 'seconds' => __('account.coming.seconds')] as $unit => $label)
                            <div class="rounded-lg bg-[rgba(30,18,15,0.62)] border border-club-cream/20 text-center px-1 pt-4 pb-3">
                                <div data-unit="{{ $unit }}" class="font-display font-semibold text-4xl sm:text-5xl leading-none tabular-nums">00</div>
                                <div class="mt-2 text-[11px] font-bold uppercase tracking-[0.14em] text-club-cream/80">{{ $label }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="relative z-10 bg-club-terra px-6 sm:px-8 py-4 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-1.5 text-xs">
                    <span class="text-club-cream/90">{{ $dateLabel }}</span>
                    <span class="font-semibold tracking-wide text-club-cream">Club 61 Padel &amp; Wellness</span>
                </div>
            </div>

            {{-- ============ KARTU INFO + LOGIN STAF ============ --}}
            <div class="lg:col-span-5 rounded-xl bg-club-paper border border-club-line shadow-[0_24px_60px_-28px_rgba(79,47,42,0.45)] p-6 sm:p-9 flex flex-col">
                {{-- Baris atas kartu: kembali ke beranda (kiri) + tombol bahasa ID / EN (kanan). --}}
                <div class="flex items-center justify-between gap-3 -mt-1 mb-5">
                    <a href="{{ url('/') }}" class="inline-flex items-center gap-1 py-1 text-xs font-semibold text-club-muted hover:text-club-terra focus-visible:outline-none focus-visible:underline transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        {{ __('account.login.back_home') }}
                    </a>
                    <x-lang-switch variant="light" />
                </div>

                <img src="{{ asset('images/identity/monogram-terracotta.png') }}" alt="" class="h-14 w-auto self-start mb-5">
                <div class="text-[11px] font-bold uppercase tracking-[0.18em] text-club-terra mb-2">{{ __('account.coming.kicker') }}</div>
                <h1 class="font-display font-semibold text-3xl sm:text-[2.1rem] leading-tight text-club-brown">{{ __('account.coming.title') }}</h1>
                <p class="text-[15px] text-club-muted mt-2.5 leading-relaxed">
                    {{ __('account.coming.body_before') }} <strong class="text-club-brown">{{ $dateLabel }}</strong>.
                </p>

                @if($loginError || $loginStatus)
                    <div role="alert" class="mt-6 flex items-start gap-3 p-3.5 rounded-lg border {{ $loginError ? 'bg-rose-50 border-rose-200 text-rose-800' : 'bg-club-cream border-club-line text-club-terra' }}">
                        <svg class="w-5 h-5 shrink-0 mt-px" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                        <p class="flex-1 text-sm font-medium leading-snug">{{ $loginError ?: $loginStatus }}</p>
                    </div>
                @endif

                <div id="cs-info" class="mt-6 flex items-start gap-3 p-4 rounded-lg bg-club-cream border border-club-line {{ $formOpen ? 'hidden' : '' }}">
                    <svg class="w-5 h-5 shrink-0 mt-px text-club-terra" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><path stroke-linecap="round" stroke-linejoin="round" d="M16 2v4M8 2v4M3 10h18"/></svg>
                    <p class="text-sm leading-relaxed text-club-brown">{{ __('account.coming.info') }}</p>
                </div>

                <form id="staff-login" method="POST" action="{{ route('login') }}" class="mt-6 space-y-5 {{ $formOpen ? '' : 'hidden' }}">
                    @csrf

                    <div class="flex items-center justify-between gap-3">
                        <span class="text-xs font-bold uppercase tracking-[0.12em] text-club-terra">{{ __('account.coming.staff_login') }}</span>
                        <button type="button" data-staff-toggle="close" class="py-2 text-[13px] font-semibold text-club-muted hover:text-club-terra cursor-pointer">{{ __('account.coming.close') }}</button>
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-semibold text-club-brown mb-1.5">{{ __('account.login.identifier') }}</label>
                        <input id="email" type="text" name="email" value="{{ old('email') }}" required autocomplete="username"
                               placeholder="{{ __('account.login.identifier_placeholder') }}"
                               @if($errors->has('email')) aria-invalid="true" @endif
                               class="w-full h-12 px-4 rounded-md bg-white border {{ $errors->has('email') ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-200' : 'border-club-line focus:border-club-terra focus:ring-club-terra/20' }} text-club-brown placeholder-club-muted/70 text-[15px] focus:ring-2 outline-none transition">
                    </div>

                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-sm font-semibold text-club-brown">{{ __('account.login.password') }}</label>
                            @if (Route::has('password.request'))
                                <a href="{{ route('password.request') }}" class="text-sm font-semibold text-club-terra hover:text-club-brown underline-offset-4 hover:underline">{{ __('account.login.forgot') }}</a>
                            @endif
                        </div>
                        <input id="password" type="password" name="password" required autocomplete="current-password" placeholder="••••••••••"
                               @if($errors->has('password')) aria-invalid="true" @endif
                               class="w-full h-12 px-4 rounded-md bg-white border {{ $errors->has('password') ? 'border-rose-400 focus:border-rose-500 focus:ring-rose-200' : 'border-club-line focus:border-club-terra focus:ring-club-terra/20' }} text-club-brown placeholder-club-muted/70 text-[15px] focus:ring-2 outline-none transition">
                    </div>

                    <label for="remember_me" class="inline-flex items-center gap-2.5 cursor-pointer select-none">
                        <input id="remember_me" type="checkbox" name="remember"
                               class="w-[18px] h-[18px] rounded border-club-line text-club-terra focus:ring-club-terra/30">
                        <span class="text-sm text-club-brown">{{ __('account.login.remember') }}</span>
                    </label>

                    <button type="submit"
                            class="w-full h-12 rounded-md bg-club-terra text-club-cream font-bold text-[15px] tracking-wide hover:bg-club-terra-dark active:scale-[0.99] transition flex items-center justify-center gap-2 cursor-pointer">
                        <span>{{ __('account.coming.submit') }}</span>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.25" d="M14 5l7 7m0 0l-7 7m7-7H3" /></svg>
                    </button>
                    <p class="text-xs text-club-muted">{{ __('account.coming.staff_only') }}</p>
                </form>

                <div class="mt-auto pt-7">
                    <div id="staff-cta" class="flex flex-wrap items-center justify-between gap-2 border-t border-club-line text-sm {{ $formOpen ? 'hidden' : '' }}">
                        <span class="text-club-muted">{{ __('account.coming.staff_question') }}</span>
                        <button type="button" data-staff-toggle="open" aria-controls="staff-login"
                                class="inline-flex items-center gap-1 py-3 font-bold text-club-terra hover:text-club-brown underline-offset-4 hover:underline cursor-pointer">
                            {{ __('account.coming.staff_login_button') }}
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </button>
                    </div>
                    <div class="flex items-center justify-between pt-3 border-t border-club-line text-xs text-club-muted">
                        <span>Play. Compete. Connect.</span>
                        <span>&copy; {{ date('Y') }} Club 61</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            // Hitung mundur dari sisa detik versi server (bukan jam perangkat). Habis → muat ulang: server sudah lewat
            // COMING_SOON_UNTIL sehingga yang tampil halaman login normal.
            var box = document.getElementById('cs-countdown');
            var end = Date.now() + Number(box.dataset.seconds || 0) * 1000;
            var cells = {};
            box.querySelectorAll('[data-unit]').forEach(function (el) { cells[el.dataset.unit] = el; });
            var pad = function (n) { return String(n).padStart(2, '0'); };
            var timer = null;

            function tick() {
                var left = Math.max(0, Math.floor((end - Date.now()) / 1000));
                cells.days.textContent = pad(Math.floor(left / 86400));
                cells.hours.textContent = pad(Math.floor(left % 86400 / 3600));
                cells.minutes.textContent = pad(Math.floor(left % 3600 / 60));
                cells.seconds.textContent = pad(left % 60);
                if (left === 0 && timer !== null) {
                    clearInterval(timer);
                    setTimeout(function () { window.location.reload(); }, 1500);
                }
            }

            tick();
            timer = setInterval(tick, 1000);

            var form = document.getElementById('staff-login');
            var info = document.getElementById('cs-info');
            var cta = document.getElementById('staff-cta');
            document.querySelectorAll('[data-staff-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var open = btn.dataset.staffToggle === 'open';
                    form.classList.toggle('hidden', ! open);
                    info.classList.toggle('hidden', open);
                    cta.classList.toggle('hidden', open);
                    if (open) { document.getElementById('email').focus(); }
                });
            });
        })();
    </script>
</x-guest-layout>
