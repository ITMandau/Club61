<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-2xl bg-[#CCFF00] text-[#051811] flex items-center justify-center font-black text-xl shadow-lg shadow-lime-400/20">
                    🎾
                </div>
                <div>
                    <h2 class="font-extrabold text-xl text-white tracking-wide">
                        VANTAGE <span class="text-xs tracking-widest text-[#CCFF00] uppercase font-bold px-2 py-0.5 rounded-full bg-[#CCFF00]/15 border border-[#CCFF00]/30 ml-1">MEMBER PORTAL</span>
                    </h2>
                    <p class="text-xs text-emerald-300/70">Club 61 Sports &amp; Social Club &bull; Member Area</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <span class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-900/60 border border-emerald-600/40 text-xs font-semibold text-emerald-200">
                    <span class="w-2 h-2 rounded-full bg-[#CCFF00] animate-pulse"></span>
                    <span>Status: VIP Platinum Active</span>
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-8 bg-[#04160F] min-h-screen text-slate-100">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

            <!-- Welcome Hero Card -->
            <div class="rounded-3xl bg-gradient-to-r from-[#0F4738] via-[#0D3B2E] to-[#0A2B21] border border-emerald-500/30 p-6 sm:p-8 shadow-2xl relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="relative z-10 max-w-xl">
                    <div class="inline-block px-3 py-1 rounded-full bg-[#CCFF00]/20 text-[#CCFF00] border border-[#CCFF00]/30 text-xs font-bold uppercase tracking-wider mb-2">
                        Member Dashboard
                    </div>
                    <h1 class="text-2xl sm:text-3xl font-black text-white">
                        Selamat Datang, {{ Auth::user()->name }}! 👋
                    </h1>
                    <p class="text-xs sm:text-sm text-emerald-100/80 mt-1.5 leading-relaxed">
                        Jadwal pertandingan padel Anda berikutnya telah dikonfirmasi. Tunjukkan QR Code pada tiket saat check-in di frontdesk.
                    </p>

                    <!-- Next Match Info -->
                    <div class="mt-4 p-3.5 rounded-2xl bg-black/40 backdrop-blur-md border border-emerald-500/25 flex items-center gap-3 text-xs">
                        <div class="p-2 rounded-xl bg-[#CCFF00] text-[#051811] font-bold text-base">
                            🎾
                        </div>
                        <div>
                            <div class="font-bold text-white">Court 1 (Panoramic Arena) &bull; 19:00 - 20:30 WIB</div>
                            <div class="text-[11px] text-emerald-300/80">Kode Reservasi: #BK-PAD-8812 &bull; Status: CONFIRMED</div>
                        </div>
                    </div>
                </div>

                <!-- Quick Action Buttons -->
                <div class="relative z-10 flex flex-col sm:flex-row gap-3 shrink-0">
                    <button type="button" onclick="alert('Membuka modal booking lapangan padel...')" class="px-5 py-3 rounded-2xl bg-[#CCFF00] hover:bg-[#d8ff33] text-[#051811] font-black text-xs uppercase tracking-wider transition-all active:scale-95 shadow-lg shadow-lime-400/20 cursor-pointer">
                        + Pesan Lapangan
                    </button>
                    <button type="button" onclick="alert('Membuka sesi cold plunge & sauna...')" class="px-5 py-3 rounded-2xl bg-white/[0.08] hover:bg-white/[0.15] border border-emerald-500/40 text-white font-bold text-xs uppercase tracking-wider transition-all active:scale-95 cursor-pointer">
                        ❄️ Sesi Wellness
                    </button>
                </div>
            </div>

            <!-- Stats Overview 4 Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-5 rounded-3xl bg-[#07241A] border border-emerald-600/30 shadow-xl">
                    <div class="text-xs text-emerald-400 font-semibold mb-1">Total Pertandingan</div>
                    <div class="text-2xl font-black text-white">18 Match</div>
                    <div class="text-[11px] text-emerald-300/60 mt-1">+4 match bulan ini</div>
                </div>

                <div class="p-5 rounded-3xl bg-[#07241A] border border-emerald-600/30 shadow-xl">
                    <div class="text-xs text-emerald-400 font-semibold mb-1">Peringkat Club</div>
                    <div class="text-2xl font-black text-[#CCFF00]">Tier Gold III</div>
                    <div class="text-[11px] text-emerald-300/60 mt-1">Top 15% Leaderboard</div>
                </div>

                <div class="p-5 rounded-3xl bg-[#07241A] border border-emerald-600/30 shadow-xl">
                    <div class="text-xs text-emerald-400 font-semibold mb-1">Poin Reward</div>
                    <div class="text-2xl font-black text-white">2.450 Pts</div>
                    <div class="text-[11px] text-emerald-300/60 mt-1">Dapat ditukar F&amp;B / Sewa</div>
                </div>

                <div class="p-5 rounded-3xl bg-[#07241A] border border-emerald-600/30 shadow-xl">
                    <div class="text-xs text-emerald-400 font-semibold mb-1">Masa Berlaku VIP</div>
                    <div class="text-2xl font-black text-white">31 Des 2026</div>
                    <div class="text-[11px] text-emerald-300/60 mt-1">Perpanjangan otomatis</div>
                </div>
            </div>

            <!-- Recent Bookings Table -->
            <div class="rounded-3xl bg-[#07241A] border border-emerald-600/30 p-6 shadow-xl">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="font-extrabold text-base text-white">Riwayat Reservasi &amp; Aktivitas Member</h3>
                        <p class="text-xs text-emerald-300/70">Daftar booking lapangan padel dan fasilitas club terkini.</p>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-emerald-100">
                        <thead class="bg-[#051811] text-emerald-400 uppercase text-[10px] tracking-wider border-b border-emerald-700/40">
                            <tr>
                                <th class="p-3.5 rounded-l-xl">ID Booking</th>
                                <th class="p-3.5">Fasilitas</th>
                                <th class="p-3.5">Jadwal Main</th>
                                <th class="p-3.5">Total Biaya</th>
                                <th class="p-3.5 rounded-r-xl">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-emerald-800/30">
                            <tr>
                                <td class="p-3.5 font-mono text-[#CCFF00]">#BK-PAD-8812</td>
                                <td class="p-3.5 font-bold text-white">Court 1 (Panoramic Pro)</td>
                                <td class="p-3.5">Hari Ini, 19:00 - 20:30</td>
                                <td class="p-3.5 font-mono">Rp 350.000</td>
                                <td class="p-3.5">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                        CONFIRMED
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="p-3.5 font-mono text-emerald-400">#BK-WEL-2201</td>
                                <td class="p-3.5 font-bold text-white">Finnish Cedar Sauna &amp; Ice Bath</td>
                                <td class="p-3.5">Kemarin, 16:00 - 16:45</td>
                                <td class="p-3.5 font-mono">Rp 125.000</td>
                                <td class="p-3.5">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                        COMPLETED
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <td class="p-3.5 font-mono text-emerald-400">#BK-PAD-7734</td>
                                <td class="p-3.5 font-bold text-white">Court 3 (Tournament Ready)</td>
                                <td class="p-3.5">3 Sep 2026, 08:00 - 10:00</td>
                                <td class="p-3.5 font-mono">Rp 480.000</td>
                                <td class="p-3.5">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                        COMPLETED
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>