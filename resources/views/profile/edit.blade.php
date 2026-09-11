<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl flex items-center justify-center shadow-md font-bold text-lg"
                     style="background: linear-gradient(135deg, #2D210F 0%, #171006 100%); border: 1.5px solid #E5C378; color: #E5C378;">
                    👤
                </div>
                <div>
                    <h2 class="font-serif font-extrabold text-xl text-[#1F170D] tracking-wide">
                        Pengaturan Profil Member
                    </h2>
                    <p class="text-xs text-[#7A643E]">Kelola data akun, keamanan password, dan preferensi akun Anda.</p>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-8 min-h-screen text-[#1F170D]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="p-6 sm:p-8"
                 style="background: rgba(255, 255, 255, 0.92); border: 1.5px solid #DFC387; border-radius: 24px; box-shadow: 0 10px 30px -10px rgba(160, 120, 30, 0.15); backdrop-filter: blur(16px);">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="p-6 sm:p-8"
                 style="background: rgba(255, 255, 255, 0.92); border: 1.5px solid #DFC387; border-radius: 24px; box-shadow: 0 10px 30px -10px rgba(160, 120, 30, 0.15); backdrop-filter: blur(16px);">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="p-6 sm:p-8"
                 style="background: rgba(255, 255, 255, 0.92); border: 1.5px solid #DFC387; border-radius: 24px; box-shadow: 0 10px 30px -10px rgba(160, 120, 30, 0.15); backdrop-filter: blur(16px);">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
