<div class="cmd-wrap" wire:poll.10s>
    <!-- Embedded Custom Style for Command Board -->
    <style>
        .cmd-wrap {
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            font-family: 'Outfit', 'Inter', -apple-system, sans-serif;
            color: #1A150B;
        }

        /* Top Header Banner */
        .cmd-header {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(253, 248, 237, 0.9) 100%);
            border: 1px solid rgba(212, 175, 55, 0.25);
            border-radius: 20px;
            padding: 1.25rem 1.5rem;
            box-shadow: 0 10px 30px -5px rgba(212, 175, 55, 0.08), 0 4px 12px rgba(0, 0, 0, 0.02);
            backdrop-filter: blur(12px);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .cmd-pill-gold {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.3rem 0.8rem;
            background: linear-gradient(180deg, #FBF4E2 0%, #F5E5BE 100%);
            border: 1px solid #D4AF37;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 800;
            color: #8C6418;
            letter-spacing: 0.04em;
            text-transform: uppercase;
        }

        .cmd-live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #10B981;
            box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            animation: cmd-pulse 2s infinite;
        }

        @keyframes cmd-pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
        }

        /* Pulse KPI Metrics Grid */
        .cmd-pulse-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
        }

        @media (max-width: 1024px) {
            .cmd-pulse-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media (max-width: 640px) {
            .cmd-pulse-grid { grid-template-columns: 1fr; }
        }

        .cmd-kpi-card {
            background: #FFFFFF;
            border: 1px solid #EADBBE;
            border-radius: 16px;
            padding: 1.1rem 1.25rem;
            box-shadow: 0 4px 16px rgba(212, 175, 55, 0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
        }

        .cmd-kpi-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(212, 175, 55, 0.12);
            border-color: #D4AF37;
        }

        .cmd-kpi-val {
            font-size: 1.5rem;
            font-weight: 900;
            color: #1A150B;
            line-height: 1.1;
        }

        .cmd-kpi-lbl {
            font-size: 0.71875rem;
            font-weight: 700;
            color: #7A6335;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-top: 0.25rem;
        }

        /* Filter & Date Controller Bar */
        .cmd-control-bar {
            background: #FFFFFF;
            border: 1px solid #EADBBE;
            border-radius: 16px;
            padding: 0.75rem 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.75rem;
        }

        .cmd-btn-nav {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.85rem;
            background: #FDFBF7;
            border: 1px solid #DEC898;
            border-radius: 10px;
            font-size: 0.8125rem;
            font-weight: 700;
            color: #4A3A18;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .cmd-btn-nav:hover {
            background: #FBF4E2;
            border-color: #D4AF37;
            color: #1A150B;
        }

        .cmd-btn-primary {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.55rem 1.15rem;
            background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%);
            border: 1px solid #FBF0CE;
            border-radius: 12px;
            font-size: 0.8125rem;
            font-weight: 800;
            color: #281A05;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(184, 134, 11, 0.25);
            transition: all 0.2s ease;
        }

        .cmd-btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(184, 134, 11, 0.35);
        }

        /* Matrix Schedule Container */
        .cmd-matrix-container {
            background: #FFFFFF;
            border: 1.5px solid #EADBBE;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.06);
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        .cmd-matrix-header {
            padding: 1rem 1.25rem;
            background: #FAF6ED;
            border-bottom: 1.5px solid #EADBBE;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cmd-matrix-scroll {
            overflow-x: auto;
            overflow-y: hidden;
            position: relative;
        }

        .cmd-table {
            table-layout: fixed;
            width: max-content;
            border-collapse: separate;
            border-spacing: 0;
        }

        .cmd-th-court {
            position: sticky;
            left: 0;
            z-index: 20;
            background: #FAF6ED;
            border-right: 2px solid #DEC898;
            border-bottom: 1.5px solid #EADBBE;
            padding: 0.85rem 1rem;
            font-size: 0.75rem;
            font-weight: 800;
            color: #7A6335;
            text-transform: uppercase;
            width: 200px;
            min-width: 200px;
            max-width: 200px;
            box-sizing: border-box;
        }

        .cmd-th-hour {
            border-bottom: 1.5px solid #EADBBE;
            border-right: 1px solid #F0E6D2;
            padding: 0.75rem 0.5rem;
            text-align: center;
            font-size: 0.71875rem;
            font-weight: 800;
            color: #7A6335;
            background: #FAF6ED;
            width: 110px;
            min-width: 110px;
            max-width: 110px;
            box-sizing: border-box;
        }

        .cmd-th-hour.is-live {
            background: linear-gradient(180deg, #FEF3C7 0%, #FDE68A 100%);
            color: #92400E;
            border-bottom: 2px solid #D97706;
        }

        .cmd-td-court {
            position: sticky;
            left: 0;
            z-index: 10;
            background: #FFFFFF;
            border-right: 2px solid #DEC898;
            border-bottom: 1px solid #EADBBE;
            padding: 0.85rem 1rem;
            width: 200px;
            min-width: 200px;
            max-width: 200px;
            box-sizing: border-box;
        }

        .cmd-td-slot {
            border-right: 1px solid #F0E6D2;
            border-bottom: 1px solid #EADBBE;
            padding: 0.4rem;
            vertical-align: top;
            height: 96px;
            background: #FFFFFF;
            position: relative;
            width: 110px;
            min-width: 110px;
            max-width: 110px;
            box-sizing: border-box;
        }

        .cmd-td-slot.is-live-col {
            background: rgba(254, 243, 199, 0.2);
        }

        /* Slot Card Variants */
        .cmd-slot-box {
            width: 100%;
            height: 100%;
            border-radius: 12px;
            padding: 0.45rem 0.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            font-size: 0.6875rem;
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            position: relative;
            overflow: hidden;
            box-sizing: border-box;
        }

        .cmd-slot-box:hover {
            transform: scale(1.02);
            z-index: 5;
        }

        /* Status: Sedang Main (Emerald Active) */
        .cmd-slot-active {
            background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%);
            border: 1.5px solid #10B981;
            color: #065F46;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.15);
        }

        /* Status: Terjadwal Lunas (Gold Luxury) */
        .cmd-slot-paid {
            background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
            border: 1.5px solid #D4AF37;
            color: #78350F;
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.15);
        }

        /* Status: Menunggu Bayar (Amber Striped) */
        .cmd-slot-pending {
            background: repeating-linear-gradient(
                45deg,
                #FFF7ED,
                #FFF7ED 8px,
                #FFEDD5 8px,
                #FFEDD5 16px
            );
            border: 1.5px solid #F97316;
            color: #9A3412;
        }

        /* Status: Selesai (Muted Clean) */
        .cmd-slot-completed {
            background: #F3F4F6;
            border: 1px solid #E5E7EB;
            color: #6B7280;
        }

        /* Status: Kosong (Dashed Available) */
        .cmd-slot-available {
            background: #FCFCFC;
            border: 1.5px dashed #DEC898;
            color: #A18A56;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
        }

        .cmd-slot-available:hover {
            background: #FAF4E4;
            border-color: #D4AF37;
            border-style: solid;
            color: #8C6418;
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.1);
        }

        /* Status: Jam Lampau / Lewat (Disabled Muted ala POS) */
        .cmd-slot-past {
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            color: #9CA3AF;
            cursor: not-allowed;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.15rem;
            opacity: 0.65;
            user-select: none;
        }

        .cmd-slot-past:hover {
            transform: none !important;
            box-shadow: none !important;
        }

        /* Modal / Inspector Drawer */
        .cmd-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 12, 6, 0.6);
            backdrop-filter: blur(6px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }

        .cmd-modal-content {
            background: #FFFFFF;
            border: 1.5px solid #D4AF37;
            border-radius: 24px;
            width: 100%;
            max-width: 540px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            overflow: hidden;
            animation: cmd-pop 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes cmd-pop {
            0% { transform: scale(0.95); opacity: 0; }
            100% { transform: scale(1); opacity: 1; }
        }
    </style>

    <!-- 1. Header Banner -->
    <div class="cmd-header">
        <div>
            <div style="display: flex; align-items: center; gap: 0.6rem; margin-bottom: 0.4rem;">
                <div class="cmd-pill-gold">
                    <span class="cmd-live-dot"></span>
                    <span>Venue Command Board &bull; Live Multi-Court Radar</span>
                </div>
                <span style="font-size: 0.71875rem; color: #8C6418; font-weight: 700;">Auto-Sync 10s</span>
            </div>
            <div style="font-size: 1.35rem; font-weight: 900; color: #1A150B; letter-spacing: -0.02em;">
                Booking System &amp; Monitoring Lapangan
            </div>
            <div style="font-size: 0.8125rem; color: #7A6335; font-weight: 500; margin-top: 0.2rem;">
                Pantau visual jadwal 4 lapangan realtime, deteksi slot kosong instan, dan kontrol check-in gate pemain.
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.6rem; flex-wrap: wrap;">
            <button type="button" wire:click="openCheckInModal()" class="cmd-btn-primary">
                <svg style="width:18px; height:18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path>
                </svg>
                <span>Scan QR / Check-In Gate</span>
            </button>
            <a href="/admin/book-offline-court" class="cmd-btn-nav" style="background:#FAF5E8; border-color:#D4AF37; color:#8C6418; font-weight:800;">
                <svg style="width:16px; height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                <span>+ Walk-In Booking</span>
            </a>
            <a href="/admin/kelola-pemesanan" class="cmd-btn-nav">
                <span>Kelola Semua Tiket</span>
            </a>
        </div>
    </div>

    <!-- 2. Realtime Pulse KPI Radar Strip -->
    <div class="cmd-pulse-grid">
        <!-- KPI 1: Okupansi Hari Ini -->
        <div class="cmd-kpi-card">
            <div>
                <div class="cmd-kpi-val">{{ $occupancyRate }}%</div>
                <div class="cmd-kpi-lbl">Tingkat Okupansi Hari Ini</div>
                <div style="width: 120px; height: 6px; background: #F3ECE0; border-radius: 9999px; margin-top: 0.5rem; overflow: hidden;">
                    <div style="width: {{ min(100, $occupancyRate) }}%; height: 100%; background: linear-gradient(90deg, #D4AF37, #B38622); border-radius: 9999px;"></div>
                </div>
            </div>
            <div style="width: 44px; height: 44px; border-radius: 12px; background: #FAF5E8; border: 1px solid #DEC898; display: flex; align-items: center; justify-content: center; color: #8C6418;">
                <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
            </div>
        </div>

        <!-- KPI 2: Sedang Main Detik Ini -->
        <div class="cmd-kpi-card">
            <div>
                <div class="cmd-kpi-val" style="color: #059669;">
                    {{ $activeCourtsNow }} <span style="font-size: 0.875rem; font-weight: 700; color: #6B7280;">/ {{ $totalCourtsCount }} Court</span>
                </div>
                <div class="cmd-kpi-lbl">Sedang Main Saat Ini</div>
                <div style="font-size: 0.6875rem; color: #059669; font-weight: 700; margin-top: 0.35rem;">
                    {{ $isToday ? 'Live di venue sekarang' : 'Melihat jadwal tanggal lain' }}
                </div>
            </div>
            <div style="width: 44px; height: 44px; border-radius: 12px; background: #ECFDF5; border: 1px solid #A7F3D0; display: flex; align-items: center; justify-content: center; color: #059669;">
                <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>

        <!-- KPI 3: Total Sesi Terjadwal -->
        <div class="cmd-kpi-card">
            <div>
                <div class="cmd-kpi-val" style="color: #B45309;">
                    {{ $occupiedSlotsCount }} <span style="font-size: 0.875rem; font-weight: 700; color: #6B7280;">Sesi</span>
                </div>
                <div class="cmd-kpi-lbl">Total Sesi Terisi</div>
                <div style="font-size: 0.6875rem; color: #8C6418; font-weight: 600; margin-top: 0.35rem;">
                    {{ $paidUpcomingCount }} lunas terjadwal
                </div>
            </div>
            <div style="width: 44px; height: 44px; border-radius: 12px; background: #FFFBEB; border: 1px solid #FDE68A; display: flex; align-items: center; justify-content: center; color: #B45309;">
                <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
            </div>
        </div>

        <!-- KPI 4: Sisa Slot Kosong (Deteksi Cepat!) -->
        <div class="cmd-kpi-card" style="border-color: #A7F3D0; background: linear-gradient(135deg, #FFFFFF 0%, #F0FDF4 100%);">
            <div>
                <div class="cmd-kpi-val" style="color: #047857;">
                    {{ $freeSlotsCount }} <span style="font-size: 0.875rem; font-weight: 700; color: #6B7280;">Jam</span>
                </div>
                <div class="cmd-kpi-lbl" style="color: #065F46;">Sisa Slot Kosong Hari Ini</div>
                <div style="font-size: 0.6875rem; color: #047857; font-weight: 700; margin-top: 0.35rem;">
                    Siap dipesan / walk-in
                </div>
            </div>
            <div style="width: 44px; height: 44px; border-radius: 12px; background: #D1FAE5; border: 1px solid #6EE7B7; display: flex; align-items: center; justify-content: center; color: #047857;">
                <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>

    <!-- 3. Navigasi Tanggal & Filter Bar -->
    <div class="cmd-control-bar">
        <!-- Date Switcher -->
        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <button type="button" wire:click="prevDay()" class="cmd-btn-nav" title="Hari Sebelumnya">
                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"></path>
                </svg>
                <span>Kemarin</span>
            </button>

            <button type="button" wire:click="today()" class="cmd-btn-nav {{ $isToday ? 'style=\"background:#FAF4E2; border-color:#D4AF37; font-weight:800;\"' : '' }}">
                <span>Hari Ini</span>
            </button>

            <button type="button" wire:click="nextDay()" class="cmd-btn-nav" title="Hari Berikutnya">
                <span>Besok</span>
                <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path>
                </svg>
            </button>

            <div style="display: flex; align-items: center; gap: 0.4rem; margin-left: 0.5rem;">
                <input type="date" 
                       wire:model.live="selectedDate" 
                       style="padding: 0.4rem 0.65rem; border: 1.5px solid #DEC898; border-radius: 10px; font-size: 0.8125rem; font-weight: 700; color: #1A150B; background: #FFFFFF; outline: none;">
                <span style="font-size: 0.8125rem; font-weight: 800; color: #8C6418;">
                    {{ \Carbon\Carbon::parse($selectedDate)->translatedFormat('l, d F Y') }}
                </span>
            </div>
        </div>

        <!-- Filter Court Type & Legend -->
        <div style="display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
            <!-- Filter Dropdown -->
            <select wire:model.live="courtFilter" style="padding: 0.4rem 0.75rem; border: 1.5px solid #DEC898; border-radius: 10px; font-size: 0.75rem; font-weight: 700; color: #4A3A18; background: #FAF7F0;">
                <option value="all">Semua Lapangan (4 Court)</option>
                <option value="indoor">Indoor Only</option>
                <option value="outdoor">Outdoor Only</option>
            </select>

            <!-- Legend Badges -->
            <div style="display: flex; align-items: center; gap: 0.75rem; font-size: 0.6875rem; font-weight: 700;">
                <div style="display: flex; align-items: center; gap: 0.3rem;">
                    <span style="width: 10px; height: 10px; border-radius: 3px; background: #10B981;"></span>
                    <span style="color: #065F46;">Sedang Main</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.3rem;">
                    <span style="width: 10px; height: 10px; border-radius: 3px; background: #D4AF37;"></span>
                    <span style="color: #78350F;">Terjadwal Lunas</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.3rem;">
                    <span style="width: 10px; height: 10px; border-radius: 3px; background: #F97316;"></span>
                    <span style="color: #9A3412;">Menunggu Bayar</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.3rem;">
                    <span style="width: 10px; height: 10px; border-radius: 3px; border: 1px dashed #DEC898; background: #FCFCFC;"></span>
                    <span style="color: #8C6418;">Slot Kosong (+)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 0.3rem;">
                    <span style="width: 10px; height: 10px; border-radius: 3px; border: 1px solid #E5E7EB; background: #F9FAFB;"></span>
                    <span style="color: #9CA3AF;">Lewat (-)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. Interactive Gantt Matrix Timeline (Venue Command Board) -->
    <div class="cmd-matrix-container">
        <div class="cmd-matrix-header">
            <div style="display: flex; align-items: center; gap: 0.5rem;">
                <svg style="width: 18px; height: 18px; color: #D4AF37;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path>
                </svg>
                <span style="font-size: 0.875rem; font-weight: 900; color: #1A150B; text-transform: uppercase; letter-spacing: 0.04em;">
                    Timetable Jadwal Lapangan Padel (06:00 - 24:00 WIB)
                </span>
            </div>
            <div style="font-size: 0.75rem; color: #7A6335; font-weight: 600;">
                Klik blok slot untuk melihat detail pemain, check-in gate, atau booking instan.
            </div>
        </div>

        <div class="cmd-matrix-scroll">
            <table class="cmd-table">
                <thead>
                    <tr>
                        <th class="cmd-th-court">Lapangan Padel</th>
                        @foreach($operationalHours as $opHour)
                            <th class="cmd-th-hour {{ $opHour['is_current'] ? 'is-live' : '' }}">
                                <div>{{ $opHour['label'] }}</div>
                                @if($opHour['is_current'])
                                    <div style="font-size: 0.5625rem; font-weight: 900; color: #B45309; text-transform: uppercase; margin-top: 0.1rem;">
                                        LIVE NOW
                                    </div>
                                @endif
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($matrix as $row)
                        @php $court = $row['court']; @endphp
                        <tr>
                            <!-- Sticky Court Column -->
                            <td class="cmd-td-court">
                                <div style="display: flex; justify-content: space-between; align-items: center;">
                                    <div>
                                        <div style="font-weight: 900; font-size: 0.875rem; color: #1A150B;">
                                            {{ $court->name }}
                                        </div>
                                        <div style="font-size: 0.6875rem; color: #8C6418; font-weight: 600; margin-top: 0.15rem;">
                                            {{ $court->type ?? 'Indoor' }} &bull; Rp {{ number_format($court->hourly_rate_regular, 0, ',', '.') }}/jam
                                        </div>
                                    </div>
                                    <span class="cmd-pill-gold" style="font-size: 0.5625rem; padding: 0.15rem 0.4rem;">
                                        {{ $court->type === 'INDOOR' ? 'INDOOR' : 'OUTDOOR' }}
                                    </span>
                                </div>
                            </td>

                            <!-- 18 Hourly Slots -->
                            @foreach($operationalHours as $opHour)
                                @php
                                    $h = $opHour['hour'];
                                    $slot = $row['slots'][$h];
                                    $isLiveCol = $opHour['is_current'];
                                @endphp
                                <td class="cmd-td-slot {{ $isLiveCol ? 'is-live-col' : '' }}">
                                    @if($slot['type'] === 'booked')
                                        @php
                                            $booking = $slot['booking'];
                                            $cardClass = 'cmd-slot-paid';
                                            if ($slot['is_playing']) {
                                                $cardClass = 'cmd-slot-active';
                                            } elseif ($slot['is_pending']) {
                                                $cardClass = 'cmd-slot-pending';
                                            } elseif ($slot['is_completed']) {
                                                $cardClass = 'cmd-slot-completed';
                                            }
                                        @endphp
                                        <div wire:click="inspectBooking('{{ $booking->id }}')" class="cmd-slot-box {{ $cardClass }}" title="Klik untuk rincian sesi">
                                            <!-- Top Line: Status & Time -->
                                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                                <span style="font-weight: 800; font-size: 0.625rem; text-transform: uppercase;">
                                                    @if($slot['is_playing'])
                                                        &bull; MAIN
                                                    @elseif($slot['is_paid'])
                                                        LUNAS
                                                    @elseif($slot['is_pending'])
                                                        HOLD
                                                    @elseif($slot['is_completed'])
                                                        SELESAI
                                                    @endif
                                                </span>
                                                <span style="font-size: 0.5625rem; opacity: 0.85; font-family: monospace;">
                                                    {{ $booking->start_time->format('H:i') }}
                                                </span>
                                            </div>

                                            <!-- Middle: Player Name (Static Truncated) -->
                                            <div style="font-weight: 900; font-size: 0.75rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block; max-width: 100%; margin: 0.15rem 0;" title="{{ $slot['player_name'] }}">
                                                {{ $slot['player_name'] }}
                                            </div>

                                            <!-- Bottom: Ticket / Equipment -->
                                            <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.5625rem;">
                                                <span style="font-family: monospace; opacity: 0.85;">
                                                    {{ substr($slot['booking_code'], -6) }}
                                                </span>
                                                @if($slot['equipment_count'] > 0)
                                                    <span style="padding: 0.05rem 0.3rem; border-radius: 4px; background: rgba(0,0,0,0.06); font-weight: 700;">
                                                        +{{ $slot['equipment_count'] }} alat
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        @if($slot['is_past'])
                                            <!-- Slot Jam Lewat (Disabled ala POS - Tidak Bisa Diklik) -->
                                            <div class="cmd-slot-box cmd-slot-past" title="Jam operasional telah lewat">
                                                <span style="font-size: 0.75rem; font-weight: 800; color: #9CA3AF;">-</span>
                                                <span style="font-size: 0.5625rem; font-weight: 700; color: #9CA3AF; text-transform: uppercase;">LEWAT</span>
                                            </div>
                                        @else
                                            <!-- Slot Kosong / Available (Bisa Diklik Booking) -->
                                            <div wire:click="inspectEmptySlot('{{ $court->id }}', '{{ $h }}')" class="cmd-slot-box cmd-slot-available" title="Slot Kosong - Klik untuk booking instan">
                                                <svg style="width: 14px; height: 14px; opacity: 0.6;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                                </svg>
                                                <span style="font-size: 0.625rem; font-weight: 800;">KOSONG</span>
                                                <span style="font-size: 0.5625rem; opacity: 0.7;">{{ sprintf('%02d:00', $h) }}</span>
                                            </div>
                                        @endif
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- 5. Quick Inspector Modal / Slide-Out (Detail Slot Terpilih) -->
    @if($showInspectorDrawer && $inspectData)
        <div class="cmd-modal-backdrop" wire:click.self="closeInspector()">
            <div class="cmd-modal-content">
                @if($inspectData['type'] === 'booking')
                    <!-- Header Modal Booking -->
                    <div style="padding: 1.25rem 1.5rem; background: #FAF5E8; border-bottom: 1.5px solid #DEC898; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span class="cmd-pill-gold">{{ $inspectData['court_name'] }}</span>
                                <span style="font-size: 0.75rem; font-weight: 800; color: {{ $inspectData['is_checked_in'] ? '#059669' : '#8C6418' }};">
                                    {{ $inspectData['is_checked_in'] ? 'SEDANG MAIN' : ($inspectData['status'] === 'PAID' ? 'TERJADWAL LUNAS' : $inspectData['status']) }}
                                </span>
                            </div>
                            <div style="font-size: 1.125rem; font-weight: 900; color: #1A150B; margin-top: 0.25rem;">
                                {{ $inspectData['customer_name'] }}
                            </div>
                        </div>
                        <button type="button" wire:click="closeInspector()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: #8C6418; font-weight: 800;">
                            &times;
                        </button>
                    </div>

                    <!-- Body Modal Booking -->
                    <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; font-size: 0.8125rem;">
                        <!-- Grid Info -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; background: #FAF8F2; padding: 1rem; border-radius: 14px; border: 1px solid #EADBBE;">
                            <div>
                                <div style="font-size: 0.6875rem; color: #7A6335; font-weight: 700; text-transform: uppercase;">Jadwal Bermain</div>
                                <div style="font-weight: 800; color: #1A150B; margin-top: 0.15rem;">
                                    {{ $inspectData['start_time'] }} - {{ $inspectData['end_time'] }} WIB
                                </div>
                                <div style="font-size: 0.6875rem; color: #8C6418;">{{ $inspectData['date_formatted'] }}</div>
                            </div>
                            <div>
                                <div style="font-size: 0.6875rem; color: #7A6335; font-weight: 700; text-transform: uppercase;">Kode Tiket</div>
                                <div style="font-weight: 800; color: #1A150B; font-family: monospace; margin-top: 0.15rem;">
                                    {{ $inspectData['booking_code'] }}
                                </div>
                                <div style="font-size: 0.6875rem; color: #059669; font-weight: 700;">
                                    Status: {{ $inspectData['payment_status'] }}
                                </div>
                            </div>
                        </div>

                        <!-- Customer Contact -->
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.75rem 1rem; background: #FFFFFF; border: 1px solid #EADBBE; border-radius: 12px;">
                            <div>
                                <div style="font-size: 0.6875rem; color: #7A6335; font-weight: 700; text-transform: uppercase;">Kontak Pemain</div>
                                <div style="font-weight: 800; color: #1A150B;">{{ $inspectData['customer_phone'] }}</div>
                            </div>
                            @if($inspectData['customer_phone'] && $inspectData['customer_phone'] !== '-')
                                @php
                                    $cleanPhone = preg_replace('/[^0-9]/', '', $inspectData['customer_phone']);
                                    if (str_starts_with($cleanPhone, '0')) {
                                        $cleanPhone = '62' . substr($cleanPhone, 1);
                                    }
                                @endphp
                                <a href="https://wa.me/{{ $cleanPhone }}" target="_blank" class="cmd-btn-nav" style="font-size: 0.75rem; padding: 0.35rem 0.75rem;">
                                    <span>Hubungi WA</span>
                                </a>
                            @endif
                        </div>

                        <!-- Sewa Alat Handover -->
                        @if(!empty($inspectData['equipments']))
                            <div style="background: #FFFFFF; border: 1px solid #EADBBE; border-radius: 12px; padding: 0.75rem 1rem;">
                                <div style="font-size: 0.6875rem; color: #7A6335; font-weight: 800; text-transform: uppercase; margin-bottom: 0.5rem;">
                                    Serah Terima Alat Sewa
                                </div>
                                <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                    @foreach($inspectData['equipments'] as $eq)
                                        <div style="display: flex; justify-content: space-between; font-size: 0.75rem;">
                                            <span style="font-weight: 700; color: #1A150B;">{{ $eq['quantity'] }}x {{ $eq['name'] }}</span>
                                            <span style="color: #8C6418;">Rp {{ number_format($eq['price'], 0, ',', '.') }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <!-- Action Buttons -->
                        <div style="display: flex; gap: 0.75rem; margin-top: 0.5rem;">
                            @if(! $inspectData['is_checked_in'] && $inspectData['status'] === 'PAID')
                                <button type="button" wire:click="quickCheckInFromInspector('{{ $inspectData['id'] }}')" class="cmd-btn-primary" style="flex: 1; justify-content: center; padding: 0.75rem;">
                                    <svg style="width: 18px; height: 18px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    <span>Check-In Pemain Sekarang</span>
                                </button>
                            @elseif($inspectData['is_checked_in'])
                                <button type="button" wire:click="executeComplete('{{ $inspectData['id'] }}')" class="cmd-btn-nav" style="flex: 1; justify-content: center; padding: 0.75rem; background: #ECFDF5; border-color: #10B981; color: #065F46; font-weight: 800;">
                                    <span>Tandai Sesi Selesai (Completed)</span>
                                </button>
                            @endif
                            <button type="button" wire:click="closeInspector()" class="cmd-btn-nav" style="padding: 0.75rem 1.25rem;">
                                <span>Tutup</span>
                            </button>
                        </div>
                    </div>
                @else
                    <!-- Header Modal Available Slot -->
                    <div style="padding: 1.25rem 1.5rem; background: #FAF5E8; border-bottom: 1.5px solid #DEC898; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <span class="cmd-pill-gold">{{ $inspectData['court_name'] }}</span>
                            <div style="font-size: 1.125rem; font-weight: 900; color: #047857; margin-top: 0.25rem;">
                                Slot Kosong Tersedia
                            </div>
                        </div>
                        <button type="button" wire:click="closeInspector()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: #8C6418; font-weight: 800;">
                            &times;
                        </button>
                    </div>

                    <!-- Body Modal Available Slot -->
                    <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; font-size: 0.8125rem;">
                        <div style="background: #F0FDF4; border: 1.5px solid #A7F3D0; padding: 1rem; border-radius: 14px; color: #065F46;">
                            <div style="font-weight: 800; font-size: 0.875rem;">Jadwal: {{ $inspectData['time_label'] }}</div>
                            <div style="font-size: 0.75rem; margin-top: 0.2rem;">{{ $inspectData['date_formatted'] }}</div>
                            <div style="font-size: 0.75rem; font-weight: 700; margin-top: 0.5rem;">
                                Tarif Sewa: Rp {{ number_format($inspectData['rate'], 0, ',', '.') }} / jam
                            </div>
                        </div>

                        <div style="font-size: 0.75rem; color: #7A6335;">
                            Slot ini belum memiliki reservasi. Anda dapat langsung mengarahkan pelanggan walk-in atau memesan untuk anggota.
                        </div>

                        <!-- Action Button: Open Walk-In Direct Link -->
                        <div style="display: flex; gap: 0.75rem; margin-top: 0.5rem;">
                            <a href="/admin/book-offline-court" class="cmd-btn-primary" style="flex: 1; justify-content: center; text-decoration: none; padding: 0.75rem;">
                                <span>Buka Form Walk-In Booking</span>
                            </a>
                            <button type="button" wire:click="closeInspector()" class="cmd-btn-nav" style="padding: 0.75rem 1.25rem;">
                                <span>Tutup</span>
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    <!-- 6. Gate Scanner Modal -->
    @if($showCheckInModal)
        <div class="cmd-modal-backdrop" wire:click.self="closeCheckInModal()">
            <div class="cmd-modal-content">
                <div style="padding: 1.25rem 1.5rem; background: #FAF5E8; border-bottom: 1.5px solid #DEC898; display: flex; justify-content: space-between; align-items: center;">
                    <div style="font-weight: 900; font-size: 1.125rem; color: #1A150B;">
                        Scan QR / Verifikasi Check-In Gate
                    </div>
                    <button type="button" wire:click="closeCheckInModal()" style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: #8C6418; font-weight: 800;">
                        &times;
                    </button>
                </div>

                <div style="padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem;">
                    <form wire:submit.prevent="executeCheckIn(app(\App\Services\Padel\PadelBookingService::class))">
                        <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                            <label style="font-size: 0.75rem; font-weight: 800; color: #7A6335; text-transform: uppercase;">
                                Masukkan Kode Tiket / Scan Hash Barcode:
                            </label>
                            <input type="text" 
                                   wire:model="checkInQuery" 
                                   placeholder="Contoh: BK-PAD-VRK0QHPJ atau Hash QR" 
                                   autofocus
                                   style="width: 100%; padding: 0.75rem 1rem; border: 2px solid #D4AF37; border-radius: 12px; font-size: 0.875rem; font-weight: 700; color: #1A150B; outline: none;">
                        </div>

                        <div style="display: flex; gap: 0.75rem; margin-top: 1rem;">
                            <button type="submit" class="cmd-btn-primary" style="flex: 1; justify-content: center; padding: 0.75rem;">
                                <span>Verifikasi &amp; Check-In Gate</span>
                            </button>
                            <button type="button" wire:click="closeCheckInModal()" class="cmd-btn-nav" style="padding: 0.75rem 1.25rem;">
                                <span>Batal</span>
                            </button>
                        </div>
                    </form>

                    @if($checkInResult)
                        <div style="margin-top: 0.5rem; padding: 1rem; border-radius: 14px; background: {{ ($checkInResult['already_checked_in'] ?? false) ? '#FFFBEB' : '#ECFDF5' }}; border: 1.5px solid {{ ($checkInResult['already_checked_in'] ?? false) ? '#F59E0B' : '#10B981' }}; font-size: 0.8125rem;">
                            <div style="font-weight: 900; color: #1A150B;">
                                {{ $checkInResult['message'] ?? 'Check-in berhasil.' }}
                            </div>
                            @if(!empty($checkInResult['booking']))
                                @php $resB = $checkInResult['booking']; @endphp
                                <div style="font-size: 0.75rem; color: #4A3A18; margin-top: 0.35rem;">
                                    Pemain: <strong>{{ $resB->user?->name ?? 'Guest' }}</strong> &bull; {{ $resB->court?->name }} ({{ $resB->start_time->format('H:i') }} - {{ $resB->end_time->format('H:i') }})
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
