@php
    $inputClass = 'w-full pl-10 pr-11 py-3 rounded-xl text-sm font-semibold text-[#4F2F2A] placeholder-[#7A5A52] transition-all duration-200 outline-none';
    $inputStyle = 'background: rgba(255, 255, 255, 0.9); border: 1.5px solid #E6DAC0; box-shadow: inset 0 2px 4px rgba(0,0,0,0.03);';
    $focus = "this.style.borderColor='#511D18'; this.style.boxShadow='0 0 0 3px rgba(102,39,33,0.25)';";
    $blur = "this.style.borderColor='#E6DAC0'; this.style.boxShadow='none';";
@endphp
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
                <h1 class="font-display text-2xl font-black text-[#4F2F2A] tracking-wide">{{ __('account.reset.title') }}</h1>
                <p class="text-xs sm:text-sm text-[#7A5A52] mt-1.5 font-medium">{{ __('account.reset.subtitle') }}</p>
            </div>

            <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <div>
                    <label for="email" class="block text-xs font-bold uppercase tracking-wider text-[#662721] mb-1.5">{{ __('account.reset.email') }}</label>
                    <div class="relative rounded-xl shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#662721]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="username"
                               class="{{ $inputClass }}" style="{{ $inputStyle }}" onfocus="{{ $focus }}" onblur="{{ $blur }}" />
                    </div>
                    <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-xs text-rose-600 font-medium" />
                </div>

                @foreach (['password' => [__('account.reset.password'), __('account.reset.password_placeholder')], 'password_confirmation' => [__('account.reset.password_confirmation'), __('account.reset.password_confirmation_placeholder')]] as $field => [$label, $placeholder])
                    <div>
                        <label for="{{ $field }}" class="block text-xs font-bold uppercase tracking-wider text-[#662721] mb-1.5">{{ $label }}</label>
                        <div class="relative rounded-xl shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#662721]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input id="{{ $field }}" type="password" name="{{ $field }}" required autocomplete="new-password"
                                   @if ($field === 'password') autofocus @endif
                                   placeholder="{{ $placeholder }}"
                                   class="{{ $inputClass }}" style="{{ $inputStyle }}" onfocus="{{ $focus }}" onblur="{{ $blur }}" />
                            <button type="button" onclick="togglePwd('{{ $field }}', this)" title="{{ __('account.reset.toggle') }}"
                                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-[#662721] hover:text-[#662721] cursor-pointer">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get($field)" class="mt-1.5 text-xs text-rose-600 font-medium" />
                    </div>
                @endforeach

                <div class="pt-1">
                    <button type="submit"
                            class="w-full py-3.5 px-6 rounded-xl text-sm font-black uppercase tracking-wider transition-all duration-200 transform active:scale-95 hover:brightness-105 shadow-xl flex items-center justify-center gap-2 cursor-pointer"
                            style="background: #662721; border: 1.5px solid #662721; color: #F7F0DB; box-shadow: 0 10px 24px -12px rgba(79,47,42,0.55);">
                        <span>{{ __('account.reset.submit') }}</span>
                    </button>
                </div>
            </form>

            <div class="mt-5 pt-5 border-t border-[#E6DAC0]/60 text-center text-xs text-[#7A5A52]">
                {{ __('account.reset.expired') }}
                <a href="{{ route('password.request') }}" class="font-bold text-[#662721] hover:text-[#662721] underline ml-1">{{ __('account.reset.request_new') }}</a>
            </div>
        </div>
    </div>

    <script>
        function togglePwd(id, btn) {
            const input = document.getElementById(id);
            input.type = input.type === 'password' ? 'text' : 'password';
            btn.style.opacity = input.type === 'text' ? '0.55' : '1';
        }
    </script>
</x-guest-layout>
