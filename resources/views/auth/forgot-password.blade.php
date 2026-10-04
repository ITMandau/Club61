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
                <h1 class="font-serif text-2xl font-black text-[#1F170D] tracking-wide">Lupa Kata Sandi</h1>
                <p class="text-xs sm:text-sm text-[#7A643E] mt-1.5 font-medium leading-relaxed">
                    Masukkan email atau nomor HP akun Anda. Kami kirim link untuk membuat kata sandi baru ke email akun tersebut.
                </p>
            </div>

            <x-auth-session-status class="mb-4 text-[#7A5818] bg-[#FFF9E6] p-3 rounded-xl border border-[#D4AF37] text-xs font-medium shadow-sm" :status="session('status')" />

            <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
                @csrf

                <div>
                    <label for="email" class="block text-xs font-bold uppercase tracking-wider text-[#5C410F] mb-1.5">
                        Email atau Nomor HP
                    </label>
                    <div class="relative rounded-xl shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#8C6418]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <input id="email" type="text" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                               placeholder="nama@email.com atau 0812xxxxxxxx"
                               class="w-full pl-10 pr-4 py-3 rounded-xl text-sm font-semibold text-[#1C150B] placeholder-[#9E8555] transition-all duration-200 outline-none"
                               style="background: rgba(255, 255, 255, 0.9); border: 1.5px solid #DFC387; box-shadow: inset 0 2px 4px rgba(0,0,0,0.03);"
                               onfocus="this.style.borderColor='#B8860B'; this.style.boxShadow='0 0 0 3px rgba(212,175,55,0.25)';"
                               onblur="this.style.borderColor='#DFC387'; this.style.boxShadow='none';" />
                    </div>
                    <x-input-error :messages="$errors->get('email')" class="mt-1.5 text-xs text-rose-600 font-medium" />
                </div>

                <div class="pt-1">
                    <button type="submit"
                            class="w-full py-3.5 px-6 rounded-xl text-sm font-black uppercase tracking-wider transition-all duration-200 transform active:scale-95 hover:brightness-105 shadow-xl flex items-center justify-center gap-2 cursor-pointer"
                            style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%);
                                   border: 1.5px solid #FBF0CE;
                                   box-shadow: 0 8px 25px -4px rgba(184, 134, 11, 0.5), inset 0 1px 2px rgba(255, 255, 255, 0.6);
                                   color: #241604;">
                        <span>Kirim Link Reset</span>
                        <svg class="w-4 h-4 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </div>
            </form>

            <div class="mt-5 p-3 rounded-xl text-[11px] leading-relaxed text-[#6B5738]" style="background: #FAF2DE; border: 1px solid #E5D0A1;">
                Akun dibuat di kasir dan belum pernah mengisi email? Link tidak bisa dikirim. Minta bantuan frontdesk Club 61 untuk menambahkan email atau mengganti kata sandi Anda.
            </div>

            <div class="mt-5 pt-5 border-t border-[#DFC387]/60 text-center">
                <a href="{{ route('login') }}" class="text-xs font-bold text-[#8C6418] hover:text-[#5C410F] underline">
                    Kembali ke halaman masuk
                </a>
            </div>
        </div>
    </div>
</x-guest-layout>
