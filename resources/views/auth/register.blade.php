<x-guest-layout>
    <div class="w-full max-w-xl mx-auto my-auto">
        <!-- Luxury Register Card: White Frosted Glass with 3px Polished Gold Bezel -->
        <div class="p-6 sm:p-10 relative overflow-hidden"
             style="background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(253, 249, 240, 0.92) 100%);
                    border: 2.5px solid #D4AF37;
                    border-radius: 28px;
                    box-shadow: 0 25px 60px -15px rgba(180, 130, 20, 0.25), 0 0 25px rgba(212, 175, 55, 0.15), inset 0 1px 2px rgba(255, 255, 255, 0.95);
                    backdrop-filter: blur(20px);">

            <!-- Top Header & Crest -->
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl mb-3 shadow-md"
                     style="background: linear-gradient(135deg, #2D210F 0%, #171006 100%); border: 1.5px solid #E5C378;">
                    <svg class="w-7 h-7 text-[#E5C378]" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <circle cx="12" cy="12" r="9" stroke-width="2" />
                        <path d="M12 3a9 9 0 0 1 9 9" stroke-width="2.5" stroke-linecap="round" />
                        <path d="M7 10l5 5 5-5" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>
                <div class="inline-block px-3 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider mb-2 shadow-sm"
                     style="background: #FAF2DE; border: 1px solid #D9BE84; color: #7A5818;">
                    VIP Membership Registration
                </div>
                <h1 class="font-serif text-2xl sm:text-3xl font-black text-[#1F170D] tracking-wide">
                    Bergabung ke Vantage Club
                </h1>
                <p class="text-xs sm:text-sm text-[#7A643E] mt-1 font-medium">
                    Daftarkan akun member eksklusif Anda untuk menikmati seluruh fasilitas venue.
                </p>
            </div>

            <!-- Form -->
            <form method="POST" action="{{ route('register') }}" class="space-y-4">
                @csrf

                <!-- Name Input -->
                <div>
                    <label for="name" class="block text-xs font-bold uppercase tracking-wider text-[#5C410F] mb-1.5">
                        Nama Lengkap
                    </label>
                    <div class="relative rounded-xl shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#8C6418]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            </svg>
                        </div>
                        <input id="name" type="text" name="name" value="{{ old('name') }}" required autofocus
                               placeholder="Nama lengkap member..."
                               class="w-full pl-10 pr-4 py-3 rounded-xl text-sm font-semibold text-[#1C150B] placeholder-[#9E8555] transition-all duration-200 outline-none"
                               style="background: rgba(255, 255, 255, 0.9); border: 1.5px solid #DFC387; box-shadow: inset 0 2px 4px rgba(0,0,0,0.03);"
                               onfocus="this.style.borderColor='#B8860B'; this.style.boxShadow='0 0 0 3px rgba(212,175,55,0.25)';"
                               onblur="this.style.borderColor='#DFC387'; this.style.boxShadow='none';" />
                    </div>
                    <x-input-error :messages="$errors->get('name')" class="mt-1 text-xs text-rose-600" />
                </div>

                <!-- Email Input -->
                <div>
                    <label for="email" class="block text-xs font-bold uppercase tracking-wider text-[#5C410F] mb-1.5">
                        Email Member
                    </label>
                    <div class="relative rounded-xl shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#8C6418]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 12a4 4 0 10-8 0 4 4 0 008 0zm0 0v1.5a2.5 2.5 0 005 0V12a9 9 0 10-9 9m4.5-1.206a8.959 8.959 0 01-4.5 1.207" />
                            </svg>
                        </div>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required
                               placeholder="nama@email.com"
                               class="w-full pl-10 pr-4 py-3 rounded-xl text-sm font-semibold text-[#1C150B] placeholder-[#9E8555] transition-all duration-200 outline-none"
                               style="background: rgba(255, 255, 255, 0.9); border: 1.5px solid #DFC387; box-shadow: inset 0 2px 4px rgba(0,0,0,0.03);"
                               onfocus="this.style.borderColor='#B8860B'; this.style.boxShadow='0 0 0 3px rgba(212,175,55,0.25)';"
                               onblur="this.style.borderColor='#DFC387'; this.style.boxShadow='none';" />
                    </div>
                    <x-input-error :messages="$errors->get('email')" class="mt-1 text-xs text-rose-600" />
                </div>

                <!-- Password Input -->
                <div>
                    <label for="password" class="block text-xs font-bold uppercase tracking-wider text-[#5C410F] mb-1.5">
                        Password
                    </label>
                    <div class="relative rounded-xl shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#8C6418]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                            </svg>
                        </div>
                        <input id="password" type="password" name="password" required autocomplete="new-password"
                               placeholder="Minimal 8 karakter..."
                               class="w-full pl-10 pr-4 py-3 rounded-xl text-sm font-semibold text-[#1C150B] placeholder-[#9E8555] transition-all duration-200 outline-none"
                               style="background: rgba(255, 255, 255, 0.9); border: 1.5px solid #DFC387; box-shadow: inset 0 2px 4px rgba(0,0,0,0.03);"
                               onfocus="this.style.borderColor='#B8860B'; this.style.boxShadow='0 0 0 3px rgba(212,175,55,0.25)';"
                               onblur="this.style.borderColor='#DFC387'; this.style.boxShadow='none';" />
                    </div>
                    <x-input-error :messages="$errors->get('password')" class="mt-1 text-xs text-rose-600" />
                </div>

                <!-- Confirm Password -->
                <div>
                    <label for="password_confirmation" class="block text-xs font-bold uppercase tracking-wider text-[#5C410F] mb-1.5">
                        Konfirmasi Password
                    </label>
                    <div class="relative rounded-xl shadow-sm">
                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#8C6418]">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                               placeholder="Ketik ulang password..."
                               class="w-full pl-10 pr-4 py-3 rounded-xl text-sm font-semibold text-[#1C150B] placeholder-[#9E8555] transition-all duration-200 outline-none"
                               style="background: rgba(255, 255, 255, 0.9); border: 1.5px solid #DFC387; box-shadow: inset 0 2px 4px rgba(0,0,0,0.03);"
                               onfocus="this.style.borderColor='#B8860B'; this.style.boxShadow='0 0 0 3px rgba(212,175,55,0.25)';"
                               onblur="this.style.borderColor='#DFC387'; this.style.boxShadow='none';" />
                    </div>
                    <x-input-error :messages="$errors->get('password_confirmation')" class="mt-1 text-xs text-rose-600" />
                </div>

                <!-- Submit Button -->
                <div class="pt-3">
                    <button type="submit"
                            class="w-full py-3.5 px-6 rounded-xl text-sm font-black uppercase tracking-wider transition-all duration-200 transform active:scale-95 hover:brightness-105 shadow-xl flex items-center justify-center gap-2 cursor-pointer"
                            style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%);
                                   border: 1.5px solid #FBF0CE;
                                   box-shadow: 0 8px 25px -4px rgba(184, 134, 11, 0.5), inset 0 1px 2px rgba(255, 255, 255, 0.6);
                                   color: #241604;">
                        <span>Daftar Membership Sekarang</span>
                        <svg class="w-4 h-4 stroke-[3]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                        </svg>
                    </button>
                </div>
            </form>

            <!-- Bottom Prompt -->
            <div class="mt-6 pt-6 border-t border-[#DFC387]/60 text-center">
                <p class="text-xs text-[#7A643E]">
                    Sudah memiliki akun member?
                    <a href="{{ route('login') }}" class="font-bold text-[#8C6418] hover:text-[#5C410F] underline ml-1">
                        Masuk di sini
                    </a>
                </p>
            </div>
        </div>
    </div>
</x-guest-layout>
