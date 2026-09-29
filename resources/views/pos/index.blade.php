<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>CLUB 61 POS - Frontdesk & Cashier Terminal</title>
    <link rel="icon" type="image/png" href="{{ asset('images/club61-logo.png') }}">
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Alex+Brush&family=Cinzel:wght@600;700;800;900&family=Plus+Jakarta+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;0,800;1,400&family=JetBrains+Mono:wght@500;700;800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full font-sans antialiased text-[#1F170D] bg-[#FBF9F5] selection:bg-[#D4AF37] selection:text-[#1E160A] flex flex-col overflow-hidden"
      style="background-image: url('{{ asset('images/white-gold-marble.jpg') }}'); background-size: cover; background-position: center;">

    <!-- Top Ambient Lighting -->
    <div class="fixed inset-0 pointer-events-none z-0 overflow-hidden">
        <div class="absolute -top-40 left-1/4 w-[800px] h-[400px] bg-gradient-to-b from-amber-200/20 via-[#D4AF37]/10 to-transparent blur-3xl rounded-full"></div>
    </div>

    <!-- Top POS Navigation Header -->
    <header class="relative z-20 bg-white/90 backdrop-blur-xl border-b border-[#D4AF37]/50 px-5 py-3 flex items-center justify-between shadow-sm shrink-0">
        <div class="flex items-center gap-4">
            <div class="flex items-center gap-2.5">
                <img src="{{ asset('images/club61-logo.png') }}" alt="Club 61 POS" class="w-10 h-10 object-contain rounded-xl shadow-sm border border-[#E5C378] bg-white p-0.5">
                <div>
                    <div class="font-serif font-black text-[#1F170D] text-base tracking-wider flex items-center gap-2">
                        <span>CLUB 61 POS</span>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-widest shadow-sm"
                              style="background: #FAF2DE; border: 1px solid #D9BE84; color: #7A5818;">
                            Terminal 01
                        </span>
                    </div>
                    <div class="text-[11px] text-[#7A643E] font-medium -mt-0.5">Club 61 Padel Court &bull; Gedung Indosat Medan</div>
                </div>
            </div>

            <!-- Quick Status Badge -->
            <div class="hidden md:flex items-center gap-2 pl-4 border-l border-[#DFC387]/60 text-xs font-semibold text-[#5C410F]">
                <span class="w-2 h-2 rounded-full animate-pulse" style="background-color: #D4AF37; box-shadow: 0 0 8px #D4AF37;"></span>
                <span>Kasir Siap &bull; Printer Kasir Terhubung</span>
            </div>
        </div>

        <!-- Cashier Profile & Logout -->
        <div class="flex items-center gap-3">
            <button type="button" 
                    onclick="openPosCheckInModal()" 
                    class="px-3.5 py-1.5 rounded-xl font-bold text-xs flex items-center gap-1.5 transition-all cursor-pointer shadow-sm active:scale-95"
                    style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05; border: 1px solid #FBF0CE; box-shadow: 0 4px 12px rgba(184, 134, 11, 0.25);">
                <span>Check-In Tiket</span>
            </button>

            <div class="text-right hidden sm:block">
                <div class="text-xs font-bold text-[#1F170D]">{{ Auth::user()->name ?? 'Kasir Frontdesk POS' }}</div>
                <div class="text-[10px] font-mono font-bold uppercase tracking-wider px-2 py-0.5 rounded-full inline-block mt-0.5 shadow-sm"
                     style="background: #FAF2DE; border: 1px solid #D9BE84; color: #7A5818;">
                    Peran: {{ Auth::user()->role ?? 'CASHIER' }}
                </div>
            </div>

            <!-- Switch / Logout Form -->
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" 
                        class="p-2 rounded-xl bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-600 text-xs font-bold transition-all flex items-center gap-1.5 cursor-pointer shadow-sm" 
                        title="Keluar">
                    <svg class="w-4 h-4 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span class="hidden sm:inline font-bold">Keluar</span>
                </button>
            </form>
        </div>
    </header>

    <!-- Main POS Interface (Split-screen) — konten interaktif (katalog menu F&B, keranjang,
         pembayaran, shift kasir) sekarang komponen Livewire nyata (App\Livewire\Pos\FnbCashierTerminal),
         bukan lagi HTML/JS statis. Checkout beneran menyimpan Order/OrderItem/Payment lewat
         PaymentOrchestratorService — skema sama persis dengan walk-in booking BookOfflineCourt. -->
    {{-- z-30 (di atas header z-20): notifikasi & modal fixed di dalam komponen Livewire
         ikut stacking context <main>, jadi kalau <main> lebih rendah dari header, banner
         error kepotong/ketutup header dan kasir tidak pernah melihat pesannya. --}}
    <main class="relative z-30 flex-1 flex overflow-hidden">
        @livewire('pos.fnb-cashier-terminal')
    </main>

    <!-- Quick Cart Script -->
    <script>
        function openPosCheckInModal() {
            document.getElementById('pos-checkin-modal').classList.remove('hidden');
            document.getElementById('pos-checkin-result').classList.add('hidden');
            const input = document.getElementById('pos-ticket-input');
            input.value = '';
            setTimeout(() => input.focus(), 100);
        }

        function closePosCheckInModal() {
            document.getElementById('pos-checkin-modal').classList.add('hidden');
        }

        async function submitPosCheckIn() {
            const input = document.getElementById('pos-ticket-input');
            const code = input.value.trim();
            if (!code) {
                alert('Silakan scan barcode atau masukkan kode tiket.');
                return;
            }

            const resultContainer = document.getElementById('pos-checkin-result');
            resultContainer.classList.remove('hidden');
            resultContainer.className = 'rounded-2xl p-4 border text-xs space-y-2 bg-amber-50 border-amber-200 text-amber-900';
            resultContainer.innerHTML = 'Memverifikasi tiket ke server...';

            try {
                const csrfMeta = document.querySelector('meta[name="csrf-token"]');
                const response = await fetch('/pos/check-in', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfMeta ? csrfMeta.content : '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ code: code })
                });

                const data = await response.json();
                if (data.success) {
                    const res = data.data;
                    let equipmentsHtml = '';
                    if (res.equipments && res.equipments.length > 0) {
                        equipmentsHtml = '<div class="mt-2 pt-2 border-t border-emerald-200"><div class="font-bold text-emerald-900 mb-1">Serah-Terima Alat:</div>' +
                            res.equipments.map(e => `<div class="flex justify-between py-0.5"><span>${e.name}</span><span class="font-bold font-mono">${e.quantity} Pcs</span></div>`).join('') +
                            '<div class="mt-1 text-[11px] text-emerald-700 font-semibold">Wajib serahkan raket &amp; bola ke pemain.</div></div>';
                    } else {
                        equipmentsHtml = '<div class="text-[11px] text-gray-500 italic mt-1">Tidak ada sewa raket/bola tambahan.</div>';
                    }

                    resultContainer.className = 'rounded-2xl p-4 border text-xs space-y-2 bg-emerald-50 border-emerald-300 text-emerald-900';
                    resultContainer.innerHTML = `
                        <div class="flex justify-between items-center">
                            <span class="px-2 py-0.5 rounded-md font-bold text-[10px] bg-emerald-200 text-emerald-900 uppercase">
                                ${res.already_checked_in ? 'Sudah Pernah Check-In' : 'Check-In Berhasil'}
                            </span>
                            <span class="font-mono text-[10px] text-emerald-700">${res.booking_code}</span>
                        </div>
                        <div class="font-bold text-sm text-gray-900">${res.player_name}</div>
                        <div class="text-xs text-gray-700"><strong>${res.court_name}</strong> &bull; ${res.schedule}</div>
                        ${equipmentsHtml}
                    `;
                } else {
                    resultContainer.className = 'rounded-2xl p-4 border text-xs space-y-2 bg-rose-50 border-rose-300 text-rose-900';
                    resultContainer.innerHTML = `<strong>Gagal Check-In:</strong><br>${data.message || 'Tiket tidak ditemukan.'}`;
                }
            } catch (err) {
                resultContainer.className = 'rounded-2xl p-4 border text-xs space-y-2 bg-rose-50 border-rose-300 text-rose-900';
                resultContainer.innerHTML = `<strong>Terjadi Kesalahan:</strong><br>${err.message}`;
            }
        }
    </script>

    <!-- MODAL CHECK-IN GATE CLUB 61 POS -->
    <div id="pos-checkin-modal" class="hidden fixed inset-0 z-50 bg-black/60 backdrop-blur-sm flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl border border-[#DFC387] shadow-2xl w-full max-w-lg overflow-hidden animate-fadeIn">
            <!-- Header -->
            <div class="p-5 border-b border-[#DFC387] flex items-center justify-between"
                 style="background: linear-gradient(135deg, #FAF5E8 0%, #F5E8C7 100%);">
                <div>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-amber-100 text-amber-900 border border-amber-300">
                        Frontdesk Scanner &bull; Club 61 Medan
                    </span>
                    <div class="font-serif font-black text-lg text-[#1F170D] mt-1">Check-In Tiket Lapangan</div>
                </div>
                <button type="button" onclick="closePosCheckInModal()" class="text-2xl text-[#78350F] hover:text-black leading-none cursor-pointer">&times;</button>
            </div>

            <!-- Body -->
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-[#1F170D] mb-1.5">Scan Barcode / Input Kode Tiket (BK-PAD-XXXX):</label>
                    <div class="flex gap-2">
                        <input type="text" id="pos-ticket-input" 
                               placeholder="Tembak barcode gun atau ketik kode tiket..." 
                               class="flex-1 px-3.5 py-2.5 rounded-xl border border-[#D4AF37] text-sm font-mono font-bold text-[#1F170D] bg-[#FFFDF5] outline-none"
                               onkeydown="if(event.key === 'Enter') submitPosCheckIn();" />
                        <button type="button" onclick="submitPosCheckIn()" 
                                 class="px-4 py-2.5 rounded-xl text-xs font-bold uppercase tracking-wider text-[#281A05] cursor-pointer active:scale-95 transition-all shadow-md"
                                 style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); border: 1px solid #FBF0CE;">
                            Check-In
                        </button>
                    </div>
                </div>

                <!-- Alert Result Container -->
                <div id="pos-checkin-result" class="hidden rounded-2xl p-4 border text-xs space-y-2"></div>
            </div>

            <!-- Footer -->
            <div class="p-4 bg-[#FAF5E8] border-t border-[#DFC387] flex justify-end gap-2">
                <button type="button" onclick="closePosCheckInModal()" class="px-4 py-2 rounded-xl text-xs font-bold text-[#5C410F] bg-white border border-[#DFC387]">
                    Tutup
                </button>
            </div>
        </div>
    </div>

    @livewireScripts
</body>
</html>