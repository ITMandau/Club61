<div class="adm-wrap">
    <!-- Header Banner -->
    <div class="adm-banner">
        <div>
            <div class="adm-pill adm-pill-gold">
                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background-color:#D4AF37;"></span>
                <span>Modul Booking System &bull; Live Court Management &bull; APEX Padel Arena</span>
            </div>
            <div class="adm-banner-title">
                Booking System &amp; Monitoring Lapangan
            </div>
            <div class="adm-banner-sub">
                Pantau status 4 Lapangan Padel secara live, scan QR check-in pemain di gate, serah-terima alat sewa, dan konfirmasi sesi selesai.
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
            <button type="button" 
                    wire:click="openCheckInModal()" 
                    class="adm-btn-sec" 
                    style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05; border: 1px solid #FBF0CE; font-weight: 800; cursor: pointer; box-shadow: 0 4px 14px rgba(184, 134, 11, 0.35);">
                <span>📷 Scan QR / Check-In Gate</span>
            </button>
            <a href="/admin/kelola-pemesanan" class="adm-btn-sec">
                <span>📋 Kelola Semua Tiket</span>
            </a>
        </div>
    </div>

    <!-- 4 Courts Live Status Grid -->
    <div style="margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
        <div style="font-size: 0.875rem; font-weight: 800; color: #1F170D; text-transform: uppercase; letter-spacing: 0.05em;">
            🏟️ Status Realtime Lapangan ({{ now()->translatedFormat('l, d F Y') }})
        </div>
        <div style="font-size: 0.75rem; color: #8C6418; font-weight: 600;">
            Jam Operasional: 06:00 - 24:00 WIB
        </div>
    </div>

    <div class="adm-metrics-grid" style="margin-bottom: 2rem;">
        @foreach($courtStatuses as $item)
            @php
                $court = $item['court'];
                $active = $item['active_booking'];
                $upcoming = $item['upcoming_booking'];
            @endphp
            <div class="adm-card" style="padding: 1.25rem; display: flex; flex-direction: column; justify-content: space-between;">
                <div>
                    <!-- Header Kartu Lapangan -->
                    <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                        <div>
                            @if($active)
                                <span class="adm-pill adm-pill-green" style="font-weight: 800; animation: pulse 2s infinite;">
                                    🎾 Sedang Main
                                </span>
                            @elseif($upcoming)
                                <span class="adm-pill adm-pill-gold" style="font-weight: 800;">
                                    ⏰ Terjadwal ({{ $upcoming->start_time->format('H:i') }})
                                </span>
                            @else
                                <span class="adm-pill" style="background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; font-weight: 700;">
                                    ✨ Siap Digunakan
                                </span>
                            @endif

                            <div style="font-weight: 900; font-size: 1.125rem; color: #1F170D; margin-top: 0.5rem;">
                                {{ $court->name }}
                            </div>
                            <div style="font-size: 0.6875rem; color: #8C6418; font-weight: 600;">
                                {{ $court->type ?? 'Indoor Panoramic' }} &bull; Tournament Grade
                            </div>
                        </div>
                        <span style="font-size: 1.75rem;">🎾</span>
                    </div>

                    <!-- Detail Isi Sesi -->
                    @if($active)
                        <div style="margin-top: 1rem; padding: 0.85rem; background: #FAF5E8; border: 1.5px solid #DFC387; border-radius: 14px; font-size: 0.75rem;">
                            <div style="font-weight: 800; font-size: 0.875rem; color: #1F170D;">
                                Pemain: {{ $active->user?->name ?? 'Guest' }}
                            </div>
                            <div style="font-size: 0.71875rem; color: #7A643E; margin-top: 0.2rem;">
                                ⏱️ Jadwal: <strong>{{ $active->start_time->format('H:i') }} - {{ $active->end_time->format('H:i') }} WIB</strong>
                            </div>
                            <div style="font-size: 0.6875rem; color: #8C7A58; font-family: var(--font-mono, monospace); margin-top: 0.2rem;">
                                Tiket: {{ $active->booking_code }}
                            </div>

                            @if($active->equipments && $active->equipments->isNotEmpty())
                                <div style="margin-top: 0.5rem; padding-top: 0.5rem; border-top: 1px dashed #DFC387; font-size: 0.6875rem; color: #1F170D;">
                                    🎒 <strong>Alat Sewa:</strong>
                                    @foreach($active->equipments as $eq)
                                        <span class="adm-pill" style="font-size: 0.625rem; padding: 0.1rem 0.4rem; background: #FFFFFF; border: 1px solid #D4AF37;">
                                            {{ $eq->quantity }}x {{ $eq->equipment?->name ?? 'Raket' }}
                                        </span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @elseif($upcoming)
                        <div style="margin-top: 1rem; padding: 0.85rem; background: #FFFDF5; border: 1px solid #F0DB9D; border-radius: 14px; font-size: 0.75rem;">
                            <div style="font-weight: 800; font-size: 0.875rem; color: #1F170D;">
                                Pemain: {{ $upcoming->user?->name ?? 'Guest' }}
                            </div>
                            <div style="font-size: 0.71875rem; color: #7A643E; margin-top: 0.2rem;">
                                ⏱️ Mulai Pukul: <strong>{{ $upcoming->start_time->format('H:i') }} WIB</strong> (Durasi {{ (int) $upcoming->start_time->diffInHours($upcoming->end_time) }} Jam)
                            </div>
                            <div style="font-size: 0.6875rem; color: #8C7A58; font-family: var(--font-mono, monospace); margin-top: 0.2rem;">
                                Tiket: {{ $upcoming->booking_code }}
                            </div>
                        </div>
                    @else
                        <div style="margin-top: 1rem; padding: 0.85rem; background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 14px; font-size: 0.75rem;">
                            <div style="font-weight: 800; color: #166534;">Lapangan Kosong (Available)</div>
                            <div style="font-size: 0.6875rem; color: #15803D; margin-top: 0.2rem;">
                                Siap menerima pemain walk-in atau reservasi baru.
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Tombol Aksi Lapangan -->
                <div style="margin-top: 1rem; padding-top: 0.75rem; border-top: 1px solid #F3E8CE;">
                    @if($active)
                        <button type="button" 
                                wire:click="executeComplete('{{ $active->id }}')" 
                                wire:confirm="Konfirmasi sesi bermain untuk {{ $court->name }} telah selesai dan raket telah dikembalikan?"
                                wire:loading.attr="disabled"
                                class="adm-btn-sec" 
                                style="width: 100%; justify-content: center; background: #FEF3C7; border: 1px solid #FDE68A; color: #92400E; font-weight: 800; font-size: 0.8125rem;">
                            <span wire:loading.remove wire:target="executeComplete('{{ $active->id }}')">⏱️ Tandai Selesai Main</span>
                            <span wire:loading wire:target="executeComplete('{{ $active->id }}')">⏳ Menyimpan...</span>
                        </button>
                    @elseif($upcoming)
                        <button type="button" 
                                wire:click="openCheckInModal('{{ $upcoming->booking_code }}')" 
                                wire:loading.attr="disabled"
                                class="adm-btn-sec" 
                                style="width: 100%; justify-content: center; background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); border: 1px solid #FBF0CE; color: #281A05; font-weight: 800; font-size: 0.8125rem;">
                            <span wire:loading.remove wire:target="openCheckInModal('{{ $upcoming->booking_code }}')">🎟️ Check-In Pemain</span>
                            <span wire:loading wire:target="openCheckInModal('{{ $upcoming->booking_code }}')">⏳ Membuka...</span>
                        </button>
                    @else
                        <button type="button" 
                                wire:click="openCheckInModal()" 
                                class="adm-btn-sec" 
                                style="width: 100%; justify-content: center; background: #FFFFFF; font-size: 0.75rem; color: #6B7280;">
                            <span>Scan QR Masuk</span>
                        </button>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <!-- Tabel 10 Jadwal Bermain Hari Ini -->
    <div class="adm-card" style="padding: 1.5rem;">
        <div class="adm-card-head" style="margin-bottom: 1rem;">
            <div>
                <div class="adm-card-title">Jadwal Sesi Bertanding Hari Ini</div>
                <div class="adm-card-sub">Daftar pemain dan status tiket untuk hari {{ now()->translatedFormat('l, d F Y') }}</div>
            </div>
            <a href="/admin/kelola-pemesanan" class="adm-pill adm-pill-gold" style="text-decoration: none;">
                Lihat Seluruh Reservasi &rarr;
            </a>
        </div>

        @if($todayBookings->isEmpty())
            <div style="text-align: center; padding: 2.5rem; color: #8C7A58; font-size: 0.875rem;">
                Belum ada jadwal sesi bertanding yang aktif untuk hari ini.
            </div>
        @else
            <div style="overflow-x: auto;">
                <table class="adm-table-static" style="width: 100%; font-size: 0.8125rem;">
                    <thead>
                        <tr style="border-bottom: 1.5px solid #DFC387; text-align: left; color: #8C6418; font-weight: 800;">
                            <th style="padding: 0.75rem;">KODE TIKET</th>
                            <th style="padding: 0.75rem;">MEMBER</th>
                            <th style="padding: 0.75rem;">LAPANGAN</th>
                            <th style="padding: 0.75rem;">JAM MAIN</th>
                            <th style="padding: 0.75rem;">STATUS</th>
                            <th style="padding: 0.75rem; text-align: center;">AKSI KASIR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($todayBookings as $tb)
                            <tr style="border-bottom: 1px solid #FAF2DE;">
                                <td style="padding: 0.75rem; font-family: var(--font-mono, monospace); font-weight: 700; color: #8C6418;">
                                    {{ $tb->booking_code }}
                                </td>
                                <td style="padding: 0.75rem; font-weight: 800; color: #1F170D;">
                                    {{ $tb->user?->name ?? 'Guest' }}
                                </td>
                                <td style="padding: 0.75rem; color: #4B5563;">
                                    {{ $tb->court?->name ?? '-' }}
                                </td>
                                <td style="padding: 0.75rem; font-weight: 700; color: #1F170D;">
                                    {{ $tb->start_time->format('H:i') }} - {{ $tb->end_time->format('H:i') }} WIB
                                </td>
                                <td style="padding: 0.75rem;">
                                    @if($tb->status === 'PAID')
                                        <span class="adm-pill adm-pill-green">Lunas</span>
                                    @elseif($tb->status === 'CHECKED_IN')
                                        <span class="adm-pill adm-pill-gold">🎾 Sedang Main</span>
                                    @elseif($tb->status === 'COMPLETED')
                                        <span class="adm-pill" style="background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; font-weight: 700;">✅ Selesai</span>
                                    @else
                                        <span class="adm-pill">{{ $tb->status }}</span>
                                    @endif
                                </td>
                                <td style="padding: 0.75rem; text-align: center;">
                                    @if($tb->status === 'PAID')
                                        <button type="button" 
                                                wire:click="openCheckInModal('{{ $tb->booking_code }}')" 
                                                class="adm-btn-sec" 
                                                style="padding: 0.35rem 0.75rem; font-size: 0.75rem; background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; font-weight: 800;">
                                            🎟️ Check-In
                                        </button>
                                    @elseif($tb->status === 'CHECKED_IN')
                                        <button type="button" 
                                                wire:click="executeComplete('{{ $tb->id }}')" 
                                                wire:confirm="Tandai sesi bermain ini telah selesai?"
                                                class="adm-btn-sec" 
                                                style="padding: 0.35rem 0.75rem; font-size: 0.75rem; background: #FEF3C7; border: 1px solid #FDE68A; color: #92400E; font-weight: 800;">
                                            ⏱️ Selesai
                                        </button>
                                    @else
                                        <span style="color: #9CA3AF; font-size: 0.75rem;">Selesai</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- MODAL CHECK-IN GATE & HANDOVER ALAT -->
    @if($showCheckInModal)
        <div style="position: fixed; inset: 0; z-index: 9999; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; padding: 1rem;">
            <div style="background: #FFFFFF; border: 1.5px solid #DFC387; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(184, 134, 11, 0.35); width: 100%; max-width: 540px; overflow: hidden;">
                <!-- Header -->
                <div style="background: linear-gradient(135deg, #FAF5E8 0%, #F5E8C7 100%); border-bottom: 1.5px solid #DFC387; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div class="adm-pill adm-pill-gold" style="font-size: 0.625rem; padding: 0.2rem 0.6rem;">GATE ACCESS &bull; APEX PADEL ARENA</div>
                        <div style="color: #1F170D; font-size: 1.25rem; font-weight: 900; margin-top: 0.25rem;">📷 Check-In Gate &amp; Scanner</div>
                    </div>
                    <button type="button" wire:click="closeCheckInModal" style="background: none; border: none; color: #78350F; font-size: 1.75rem; cursor: pointer; line-height: 1;">&times;</button>
                </div>

                <!-- Body -->
                <div style="padding: 1.5rem;">
                    <!-- Input Scan / Tiket -->
                    <div style="margin-bottom: 1.25rem;">
                        <label style="display: block; font-size: 0.8125rem; font-weight: 800; color: #1F170D; margin-bottom: 0.4rem;">
                            Scan Barcode Gun / Input Kode Tiket:
                        </label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" 
                                   wire:model="checkInQuery" 
                                   wire:keydown.enter="executeCheckIn"
                                   placeholder="Scan QR atau ketik BK-PAD-XXXX..." 
                                   autofocus
                                   style="flex: 1; border: 1.5px solid #D4AF37; border-radius: 12px; padding: 0.65rem 0.85rem; font-size: 0.875rem; font-family: var(--font-mono, monospace); font-weight: 700; background: #FFFDF5; outline: none;">
                            <button type="button" 
                                    wire:click="executeCheckIn" 
                                    wire:loading.attr="disabled"
                                    class="adm-btn-sec" 
                                    style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05; border: 1px solid #FBF0CE; font-weight: 800; padding: 0.65rem 1rem;">
                                <span wire:loading.remove wire:target="executeCheckIn">Verifikasi ⚡</span>
                                <span wire:loading wire:target="executeCheckIn">⏳...</span>
                            </button>
                        </div>
                        <div style="font-size: 0.6875rem; color: #8C7A58; margin-top: 0.35rem;">
                            Mendukung tembakan Barcode Scanner Gun USB, QR Code Hash, atau input manual kode booking.
                        </div>
                    </div>

                    <!-- HASIL VERIFIKASI & HANDOVER ALAT -->
                    @if($checkInResult)
                        <div style="border-radius: 16px; border: 1.5px solid {{ $checkInResult['already_checked_in'] ? '#FDE68A' : '#A7F3D0' }}; background: {{ $checkInResult['already_checked_in'] ? '#FFFBEB' : '#F0FDF4' }}; padding: 1.25rem; margin-bottom: 1rem;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                                <span class="adm-pill" style="background: {{ $checkInResult['already_checked_in'] ? '#FEF3C7' : '#DCFCE7' }}; color: {{ $checkInResult['already_checked_in'] ? '#92400E' : '#166534' }}; font-weight: 800;">
                                    {{ $checkInResult['already_checked_in'] ? '⚠️ SUDAH PERNAH CHECK-IN' : '✅ CHECK-IN BERHASIL' }}
                                </span>
                                <span style="font-size: 0.6875rem; color: #6B7280; font-family: var(--font-mono, monospace);">
                                    Gate Staff: {{ $checkInResult['gate_marshall'] ?? 'Kasir' }}
                                </span>
                            </div>

                            <div style="font-size: 1.125rem; font-weight: 900; color: #1F170D;">
                                {{ $checkInResult['player_name'] }}
                            </div>
                            <div style="font-size: 0.8125rem; color: #374151; font-weight: 600; margin-top: 0.2rem;">
                                🎾 {{ $checkInResult['court_name'] }} &bull; {{ $checkInResult['schedule'] }}
                            </div>
                            <div style="font-size: 0.75rem; color: #6B7280; margin-top: 0.2rem; font-family: var(--font-mono, monospace);">
                                Tiket: <strong>{{ $checkInResult['booking_code'] }}</strong>
                            </div>

                            <!-- EQUIPMENT HANDOVER CHECKLIST -->
                            <div style="margin-top: 1rem; border-top: 1px dashed {{ $checkInResult['already_checked_in'] ? '#FCD34D' : '#86EFAC' }}; padding-top: 0.75rem;">
                                <div style="font-size: 0.75rem; font-weight: 800; color: #1F170D; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.4rem;">
                                    🎒 Serah-Terima Peralatan (Equipment Handover):
                                </div>
                                @if(!empty($checkInResult['equipments']))
                                    <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                        @foreach($checkInResult['equipments'] as $eq)
                                            <div style="background: #FFFFFF; border: 1px solid #D1D5DB; border-radius: 8px; padding: 0.5rem 0.75rem; display: flex; align-items: center; justify-content: space-between; font-size: 0.8125rem;">
                                                <span style="font-weight: 700; color: #1F170D;">🎾 {{ $eq['name'] }}</span>
                                                <span style="background: #FAF5E8; border: 1px solid #DFC387; color: #8C6418; font-weight: 800; padding: 0.15rem 0.5rem; border-radius: 6px; font-size: 0.75rem;">
                                                    {{ $eq['quantity'] }} Pcs
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div style="margin-top: 0.5rem; font-size: 0.6875rem; color: #047857; font-weight: 700;">
                                        👉 Harap serahkan raket &amp; bola di atas kepada pemain sebelum memasuki lapangan.
                                    </div>
                                @else
                                    <div style="font-size: 0.75rem; color: #6B7280; font-style: italic;">
                                        Tidak ada tambahan sewa raket atau bola pada tiket ini.
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Footer -->
                <div style="background: #FAF5E8; border-top: 1px solid #DFC387; padding: 1rem 1.5rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
                    <button type="button" wire:click="closeCheckInModal" class="adm-btn-sec" style="background: #FFFFFF;">
                        Tutup
                    </button>
                    @if($checkInResult)
                        <button type="button" wire:click="closeCheckInModal" class="adm-btn-sec" style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05; border: 1px solid #FBF0CE; font-weight: 800;">
                            Selesai &amp; Buka Akses Gate ✅
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
