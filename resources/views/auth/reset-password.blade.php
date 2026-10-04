@php
    $inputClass = 'w-full pl-10 pr-11 py-3 rounded-xl text-sm font-semibold text-[#1C150B] placeholder-[#9E8555] transition-all duration-200 outline-none';
    $inputStyle = 'background: rgba(255, 255, 255, 0.9); border: 1.5px solid #DFC387; box-shadow: inset 0 2px 4px rgba(0,0,0,0.03);';
    $focus = "this.style.borderColor='#B8860B'; this.style.boxShadow='0 0 0 3px rgba(212,175,55,0.25)';";
    $blur = "this.style.borderColor='#DFC387'; this.style.boxShadow='none';";
@endphp
<x-guest-layout>
    <div class="w-full max-w-md mx-auto my-auto">
        <div class="p-6 sm:p-9 relative overflow-hidden"
             style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(253, 249, 240, 0.92) 100%);
                    border: 2.5px solid #D4AF37;
                    border-radius: 28px;
                    box-shadow: 0 25px 60px -15px rgba(180, 130, 20, 0.25), 0 0 25px rgba(212, 175, 55, 0.15), inset 0 1px 2px rgba(255, 255, 255, 0.95);
                    backdrop-filter: blur(20px);">

            <div class="text-center mb-6">
                <img src="{{ asset('images/club61-logo.png') }}" alt="Club 61 Padel Court" class="w-14 h-14 object-contain rounded-2xl shadow-md border border-[#E5C378] bg-white p-1 mb-2 mx-auto">
                <h1 class="font-serif text-2xl font-black text-[#1F170D] tracking-wide">Buat Kata Sandi Baru</h1>
                <p class="text-xs sm:text-sm text-[#7A643E] mt-1.5 font-medium">Minimal 8 karakter. Setelah disimpan, Anda bisa langsung masuk.</p>
            </div>

            <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="token" value="{{ $request->route('token') }}">

                <div>
                    <label for="email" class="block text-xs font-bold uppercase tracking-wider text-[#5C410F] mb-1.5">Email Akun</label>
                    <div class="relative rounded-xl shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#8C6418]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autocomplete="username"
                               class="{{ $inputClass }}" style="{{ $inputStyle }}" onfocus="{{ $focus }}" onblur="{{ $blur }}" />
                    </div>
                    <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-xs text-rose-600 font-medium" />
                </div>

                @foreach (['password' => ['Kata Sandi Baru', 'Minimal 8 karakter...'], 'password_confirmation' => ['Ulangi Kata Sandi Baru', 'Ketik ulang kata sandi...']] as $field => [$label, $placeholder])
                    <div>
                        <label for="{{ $field }}" class="block text-xs font-bold uppercase tracking-wider text-[#5C410F] mb-1.5">{{ $label }}</label>
                        <div class="relative rounded-xl shadow-sm">
                            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#8C6418]">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                </svg>
                            </div>
                            <input id="{{ $field }}" type="password" name="{{ $field }}" required autocomplete="new-password"
                                   @if ($field === 'password') autofocus @endif
                                   placeholder="{{ $placeholder }}"
                                   class="{{ $inputClass }}" style="{{ $inputStyle }}" onfocus="{{ $focus }}" onblur="{{ $blur }}" />
                            <button type="button" onclick="togglePwd('{{ $field }}', this)" title="Lihat / sembunyikan"
                                    class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-[#AA771C] hover:text-[#634812] cursor-pointer">
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
                            style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%);
                                   border: 1.5px solid #FBF0CE;
                                   box-shadow: 0 8px 25px -4px rgba(184, 134, 11, 0.5), inset 0 1px 2px rgba(255, 255, 255, 0.6);
                                   color: #241604;">
                        <span>Simpan Kata Sandi</span>
                    </button>
                </div>
            </form>

            <div class="mt-5 pt-5 border-t border-[#DFC387]/60 text-center text-xs text-[#7A643E]">
                Link kedaluwarsa?
                <a href="{{ route('password.request') }}" class="font-bold text-[#8C6418] hover:text-[#5C410F] underline ml-1">Minta link baru</a>
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
