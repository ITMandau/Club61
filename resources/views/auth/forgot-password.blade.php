<x-guest-layout theme="terracotta">
    <div class="w-full max-w-md mx-auto my-auto">
        <div class="p-6 sm:p-9 relative overflow-hidden"
             style="background: #FCF8EE; border: 1px solid #E6DAC0; border-radius: 12px; box-shadow: 0 24px 60px -28px rgba(79,47,42,0.45);">

            {{-- Tombol bahasa ID / EN di pojok kanan atas kartu. --}}
            <div style="position: absolute; top: 16px; right: 16px; z-index: 1;">
                <x-lang-switch variant="light" />
            </div>

            <div class="text-center mb-6">
                <img src="{{ asset('images/identity/monogram-terracotta.png') }}" alt="Club 61 Padel &amp; Wellness" class="h-14 w-auto mx-auto mb-3">
                <h1 class="font-display text-2xl font-black text-[#4F2F2A] tracking-wide">{{ __('account.forgot.title') }}</h1>
                <p class="text-xs sm:text-sm text-[#7A5A52] mt-1.5 font-medium leading-relaxed">
                    {{ __('account.forgot.body') }}
                </p>
            </div>

            <x-auth-session-status class="mb-4 text-[#662721] bg-[#F7F0DB] p-3 rounded-xl border border-[#662721] text-xs font-medium shadow-sm" :status="session('status')" />

            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-xs font-bold uppercase tracking-wider text-[#662721] mb-1.5">
                        {{ __('account.forgot.identifier') }}
                    </label>
                    <div class="relative rounded-xl shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#662721]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <input id="email" type="text" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                               placeholder="{{ __('account.login.identifier_placeholder') }}"
                               class="w-full pl-10 pr-4 py-3 rounded-xl text-sm font-semibold text-[#4F2F2A] placeholder-[#7A5A52] transition-all duration-200 outline-none"
                               style="background: rgba(255, 255, 255, 0.9); border: 1.5px solid #E6DAC0; box-shadow: inset 0 2px 4px rgba(0,0,0,0.03);"
                               onfocus="this.style.borderColor='#511D18'; this.style.boxShadow='0 0 0 3px rgba(102,39,33,0.25)';"
                               onblur="this.style.borderColor='#E6DAC0'; this.style.boxShadow='none';" />
                    </div>
                    <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-xs text-rose-600 font-medium" />
                </div>

                <div class="pt-1">
                    <button type="submit"
                            class="w-full py-3.5 px-6 rounded-xl text-sm font-black uppercase tracking-wider transition-all duration-200 transform active:scale-95 hover:brightness-105 shadow-xl flex items-center justify-center gap-2 cursor-pointer"
                            style="background: #662721; border: 1.5px solid #662721; color: #F7F0DB; box-shadow: 0 10px 24px -12px rgba(79,47,42,0.55);">
                        <span>{{ __('account.forgot.submit') }}</span>
                        <svg class="w-4 h-4 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </div>
            </form>

            <div class="mt-5 p-3 rounded-xl text-[11px] leading-relaxed text-[#7A5A52]" style="background: #F7F0DB; border: 1px solid #E6DAC0;">
                {{ __('account.forgot.cashier_note') }}
            </div>

            <div class="mt-5 pt-5 border-t border-[#E6DAC0]/60 text-center">
                <a href="{{ route('login') }}" class="text-xs font-bold text-[#662721] hover:text-[#662721] underline">
                    {{ __('account.forgot.back_login') }}
                </a>
            </div>
        </div>
    </div>
</x-guest-layout>
