{{-- Walk-In Offline Booking – Compact POS Terminal Layout --}}
<div class="walkin-pos-root">
<style>
    /* ===== ROOT: Ambil seluruh sisa tinggi viewport setelah header Filament ===== */
    .walkin-pos-root {
        display: flex;
        flex-direction: column;
        height: calc(100vh - 120px); /* header filament ~120px */
        min-height: 600px;
        gap: 0;
        overflow: hidden;
    }

    /* ===== TOP BAR: Tanggal + Navigasi ===== */
    .pos-topbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        padding: 0.6rem 1rem;
        background: #FFFDF5;
        border: 1.5px solid #DFC387;
        border-radius: 14px;
        margin-bottom: 0.75rem;
        flex-shrink: 0;
        flex-wrap: wrap;
    }

    /* ===== MAIN SPLIT: Kiri (Grid) + Kanan (Panel) ===== */
    .pos-main {
        display: grid;
        grid-template-columns: 1fr 340px;
        gap: 0.75rem;
        flex: 1;
        min-height: 0; /* penting agar anak bisa overflow */
    }

    @media (max-width: 1100px) {
        .pos-main { grid-template-columns: 1fr; }
        .walkin-pos-root { height: auto; overflow: visible; }
    }

    /* ===== KIRI: Timetable Card ===== */
    .pos-grid-card {
        background: #FFFFFF;
        border: 1.5px solid #DFC387;
        border-radius: 14px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        min-height: 0;
    }

    .pos-grid-header {
        padding: 0.65rem 1rem;
        background: #FAF5E8;
        border-bottom: 1.5px solid #DFC387;
        flex-shrink: 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 0.5rem;
    }

    .pos-grid-scroll {
        overflow: auto; /* scroll horizontal pada tabel, bukan halaman */
        flex-shrink: 0; /* tabel setinggi kontennya, tidak dipaksa mengisi ruang */
    }

    /* ===== Panel Bawah: Ringkasan Okupansi & Riwayat Walk-In ===== */
    .pos-grid-footer {
        flex: 1;
        min-height: 0;
        overflow-y: auto;
        padding: 0.85rem 1rem;
        border-top: 1.5px solid #F3E8CE;
    }

    .pos-stats-row {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.6rem;
        margin-bottom: 0.85rem;
    }

    .pos-stat-card {
        background: #FAF5E8;
        border: 1.5px solid #DFC387;
        border-radius: 10px;
        padding: 0.55rem 0.7rem;
        text-align: center;
    }

    .pos-stat-value {
        font-size: 1.0625rem;
        font-weight: 900;
        color: #8C6418;
    }

    .pos-stat-label {
        font-size: 0.625rem;
        font-weight: 700;
        color: #7A643E;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-top: 0.15rem;
    }

    .pos-recent-header {
        font-size: 0.6875rem;
        font-weight: 900;
        color: #8C6418;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 0.5rem;
    }

    .pos-recent-list {
        display: flex;
        flex-direction: column;
        gap: 0.4rem;
    }

    .pos-recent-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #FFFDF5;
        border: 1px solid #F0DB9D;
        border-radius: 8px;
        padding: 0.5rem 0.7rem;
    }

    .pos-recent-name {
        font-size: 0.75rem;
        font-weight: 800;
        color: #1F170D;
    }

    .pos-recent-sub {
        font-size: 0.625rem;
        color: #8C6418;
        margin-top: 0.1rem;
    }

    .pos-recent-amount {
        font-size: 0.8125rem;
        font-weight: 900;
        color: #B38622;
    }

    .pos-recent-badge {
        display: inline-block;
        margin-top: 0.15rem;
        font-size: 0.5625rem;
        font-weight: 800;
        padding: 0.05rem 0.4rem;
        border-radius: 4px;
        text-transform: uppercase;
    }

    .pos-badge-paid { background: #D1FAE5; color: #047857; }
    .pos-badge-partially_paid { background: #FEF3C7; color: #92400E; }
    .pos-badge-unpaid { background: #F3F4F6; color: #6B7280; }
    .pos-badge-cancelled { background: #FEE2E2; color: #B91C1C; }
    .pos-badge-refunded { background: #E0E7FF; color: #3730A3; }

    .pos-recent-empty {
        text-align: center;
        color: #9CA3AF;
        font-size: 0.6875rem;
        padding: 1rem;
        font-style: italic;
    }

    /* ===== KANAN: Checkout Panel ===== */
    .pos-panel-card {
        background: #FFFFFF;
        border: 1.5px solid #DFC387;
        border-radius: 14px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        min-height: 0;
    }

    .pos-panel-header {
        padding: 0.65rem 1rem;
        background: linear-gradient(135deg, #FAF5E8 0%, #F5E8C7 100%);
        border-bottom: 1.5px solid #DFC387;
        flex-shrink: 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .pos-panel-body {
        flex: 1;
        overflow-y: auto;
        padding: 0.85rem 1rem;
        min-height: 0;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .pos-panel-footer {
        padding: 0.75rem 1rem;
        background: #FAF5E8;
        border-top: 1.5px solid #DFC387;
        flex-shrink: 0;
    }

    /* ===== Timetable ===== */
    .pos-timetable {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        min-width: 860px;
        font-size: 0.72rem;
    }

    .pos-timetable th {
        padding: 0.45rem 0.3rem;
        text-align: center;
        font-weight: 800;
        color: #8C6418;
        border-right: 1px solid #F3E8CE;
        background: #FAF5E8;
        position: sticky;
        top: 0;
        z-index: 15;
    }

    .pos-timetable th:first-child {
        position: sticky;
        left: 0;
        z-index: 25;
        text-align: left;
        padding-left: 0.85rem;
        min-width: 130px;
        width: 130px;
        border-right: 1.5px solid #DFC387;
        font-weight: 900;
        color: #1F170D;
    }

    .pos-timetable td {
        padding: 0.25rem 0.2rem;
        border-right: 1px solid #F3E8CE;
        border-bottom: 1px solid #F3E8CE;
        vertical-align: middle;
        text-align: center;
    }

    .pos-timetable td:first-child {
        position: sticky;
        left: 0;
        z-index: 10;
        background: #FFFDF5;
        padding: 0.55rem 0.85rem;
        border-right: 1.5px solid #DFC387;
        text-align: left;
    }

    /* ===== Slot Buttons ===== */
    .slot-btn {
        width: 100%;
        min-height: 44px;
        border-radius: 7px;
        font-size: 0.625rem;
        font-weight: 800;
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.1rem;
        transition: all 0.12s ease;
        padding: 0.2rem;
        user-select: none;
    }

    .slot-btn:active { transform: scale(0.94); }

    .slot-available {
        background: #F0FDF4;
        border: 1px solid #86EFAC;
        color: #166534;
    }
    .slot-available:hover {
        background: #DCFCE7;
        border-color: #4ADE80;
        box-shadow: 0 2px 8px rgba(34, 197, 94, 0.2);
    }

    .slot-selected {
        background: linear-gradient(160deg, #D4AF37 0%, #B38622 100%);
        border: 1.5px solid #78350F;
        color: #FFFFFF;
        box-shadow: 0 3px 8px rgba(180,134,11,0.4);
    }

    .slot-booked {
        background: #10B981;
        border: 1px solid #059669;
        color: #FFFFFF;
        cursor: not-allowed;
    }

    .slot-locked {
        background: #FEF3C7;
        border: 1px dashed #D97706;
        color: #92400E;
        cursor: not-allowed;
    }

    .slot-past {
        background: #F3F4F6;
        border: 1px solid #E5E7EB;
        color: #D1D5DB;
        cursor: not-allowed;
    }

    /* ===== Panel Sub-sections ===== */
    .pos-section-label {
        font-size: 0.6875rem;
        font-weight: 900;
        color: #8C6418;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-bottom: 0.4rem;
    }

    .pos-customer-box {
        background: #FFFDF5;
        border: 1.5px solid #F0DB9D;
        border-radius: 10px;
        padding: 0.65rem 0.75rem;
    }

    .pos-input {
        width: 100%;
        border: 1px solid #DFC387;
        border-radius: 7px;
        padding: 0.35rem 0.6rem;
        font-size: 0.75rem;
        font-weight: 700;
        color: #1F170D;
        background: #FFFFFF;
        outline: none;
        box-sizing: border-box;
    }
    .pos-input:focus { border-color: #B38622; box-shadow: 0 0 0 2px rgba(180,134,11,0.15); }

    .pos-tab-group {
        display: flex;
        background: #FAF5E8;
        border: 1px solid #DFC387;
        border-radius: 7px;
        padding: 0.1rem;
        gap: 0.15rem;
    }

    .pos-tab {
        flex: 1;
        padding: 0.25rem 0.4rem;
        font-size: 0.6875rem;
        font-weight: 800;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        transition: all 0.15s;
        background: transparent;
        color: #8C6418;
    }

    .pos-tab.active {
        background: #B38622;
        color: #FFFFFF;
    }

    .pos-slot-chip {
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: #FAF5E8;
        border: 1px solid #DFC387;
        border-radius: 7px;
        padding: 0.35rem 0.5rem;
        font-size: 0.6875rem;
        font-weight: 700;
        color: #1F170D;
    }

    .pos-eq-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.35rem 0;
        border-bottom: 1px solid #F3E8CE;
        font-size: 0.6875rem;
    }
    .pos-eq-row:last-child { border-bottom: none; }

    .pos-qty-btn {
        width: 22px;
        height: 22px;
        border-radius: 5px;
        background: #FAF5E8;
        border: 1px solid #DFC387;
        color: #1F170D;
        font-weight: 900;
        font-size: 0.75rem;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.1s;
    }
    .pos-qty-btn:hover { background: #DFC387; }

    .pos-total-box {
        background: linear-gradient(135deg, #FAF5E8 0%, #F5E8C7 100%);
        border: 1.5px solid #DFC387;
        border-radius: 10px;
        padding: 0.6rem 0.75rem;
    }

    .pos-pm-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.35rem;
    }

    .pos-pm-label {
        border: 1.5px solid #E5E7EB;
        background: #FFFFFF;
        border-radius: 7px;
        padding: 0.4rem 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.35rem;
        cursor: pointer;
        font-size: 0.6875rem;
        font-weight: 800;
        color: #1F170D;
        transition: all 0.12s;
    }
    .pos-pm-label.active {
        border-color: #B38622;
        background: #FFFDF5;
    }

    .pos-submit-btn {
        width: 100%;
        padding: 0.7rem;
        border-radius: 10px;
        background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 30%, #B38622 100%);
        color: #281A05;
        border: 1px solid #FBF0CE;
        font-weight: 900;
        font-size: 0.875rem;
        cursor: pointer;
        box-shadow: 0 4px 14px rgba(184,134,11,0.35);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        transition: all 0.15s;
    }
    .pos-submit-btn:hover { box-shadow: 0 6px 20px rgba(184,134,11,0.5); transform: translateY(-1px); }
    .pos-submit-btn:active { transform: translateY(0); }
    .pos-submit-btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

    /* ===== Legend dots ===== */
    .legend-dot {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.625rem;
        font-weight: 700;
    }
    .legend-dot span:first-child {
        width: 9px; height: 9px; border-radius: 2px;
    }
</style>

{{-- ============================
     TOP BAR: Date Navigation
     ============================ --}}
<div class="pos-topbar">
    <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
        <span style="font-size:0.6875rem; font-weight:900; color:#8C6418; text-transform:uppercase; letter-spacing:0.05em;">Tanggal:</span>
        <button type="button" wire:click="prevDay" style="padding:0.3rem 0.6rem; font-size:0.75rem; font-weight:700; border:1.5px solid #DFC387; background:#FFFFFF; border-radius:7px; cursor:pointer; color:#1F170D;">&larr; H-1</button>
        <button type="button" wire:click="today"
            style="padding:0.3rem 0.7rem; font-size:0.75rem; font-weight:800; border-radius:7px; cursor:pointer;
            {{ $isToday ? 'background:linear-gradient(180deg,#F0DB9D 0%,#D4AF37 50%,#B38622 100%); color:#281A05; border:1px solid #FBF0CE;' : 'background:#FFFFFF; border:1.5px solid #DFC387; color:#1F170D;' }}">
            Hari Ini
        </button>
        <button type="button" wire:click="nextDay" style="padding:0.3rem 0.6rem; font-size:0.75rem; font-weight:700; border:1.5px solid #DFC387; background:#FFFFFF; border-radius:7px; cursor:pointer; color:#1F170D;">H+1 &rarr;</button>
        <input type="date" wire:model.live="bookingDate"
            style="border:1.5px solid #DFC387; border-radius:7px; padding:0.28rem 0.6rem; font-size:0.75rem; background:#FFFDF5; font-weight:700; color:#1F170D; outline:none;">
    </div>

    <div style="display:flex; align-items:center; gap:0.75rem;">
        <span style="font-size:0.8125rem; font-weight:900; color:#8C6418;">
            {{ \Carbon\Carbon::parse($bookingDate)->translatedFormat('l, d F Y') }}
        </span>
        @if(count($selectedSlots) > 0)
            <button type="button" wire:click="clearSelectedSlots"
                style="background:#FEF2F2; border:1px solid #FECACA; color:#DC2626; font-size:0.6875rem; font-weight:800; padding:0.25rem 0.55rem; border-radius:6px; cursor:pointer;">
                Hapus {{ count($selectedSlots) }} Slot
            </button>
        @endif
        <div style="display:flex; gap:0.5rem; align-items:center; flex-wrap:wrap;">
            <span class="legend-dot"><span style="background:#F0FDF4; border:1px solid #86EFAC;"></span><span style="color:#166534;">Tersedia</span></span>
            <span class="legend-dot"><span style="background:#D4AF37; border:1px solid #78350F;"></span><span style="color:#78350F;">Dipilih</span></span>
            <span class="legend-dot"><span style="background:#10B981; border:1px solid #059669;"></span><span style="color:#047857;">Terisi</span></span>
            <span class="legend-dot"><span style="background:#FEF3C7; border:1px dashed #D97706;"></span><span style="color:#92400E;">Hold</span></span>
        </div>
    </div>
</div>

{{-- ============================
     MAIN 2-COLUMN POS LAYOUT
     ============================ --}}
<div class="pos-main">

    {{-- ==================== KIRI: TIMETABLE GRID ==================== --}}
    <div class="pos-grid-card">
        <div class="pos-grid-header">
            <div>
                <div style="font-size:0.8125rem; font-weight:900; color:#1F170D;">Slot Lapangan — 06:00 sampai 23:00</div>
                <div style="font-size:0.625rem; color:#7A643E; margin-top:0.1rem;">Klik kotak jam hijau untuk memilih. Klik lagi untuk membatalkan pilihan.</div>
            </div>
            <div style="font-size:0.6875rem; color:#7A643E;">
                Reguler 06:00–17:00 &bull; Prime Time 17:00–23:00 &bull; Weekend: tarif prime all-day
            </div>
        </div>

        <div class="pos-grid-scroll">
            <table class="pos-timetable">
                <thead>
                    <tr>
                        <th>LAPANGAN</th>
                        @foreach($operationalHours as $oh)
                            <th style="min-width:50px;">{{ $oh['label'] }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($gridData as $courtRow)
                        @php $court = $courtRow['court']; @endphp
                        <tr>
                            <td>
                                <div style="font-weight:900; color:#1F170D; font-size:0.75rem; white-space:nowrap;">{{ $court->name }}</div>
                                <div style="font-size:0.5625rem; color:#8C6418; font-weight:700; margin-top:0.1rem;">
                                    {{ $court->type }} &bull; Rp{{ number_format($court->hourly_rate_regular/1000,0) }}k/jam
                                </div>
                            </td>
                            @foreach($courtRow['slots'] as $slot)
                                @php
                                    $st = $slot['status'];
                                    $rate = number_format($slot['rate']/1000,0).'k';
                                @endphp
                                <td>
                                    @if($st === 'SELECTED')
                                        <button type="button"
                                            wire:click="toggleSlot('{{ $court->id }}', '{{ addslashes($court->name) }}', '{{ $slot['start_time'] }}', '{{ $slot['end_time'] }}', {{ $slot['rate'] }})"
                                            class="slot-btn slot-selected"
                                            title="Batal pilih: {{ $slot['full_label'] }} ({{ $court->name }})">
                                            <span style="font-size:0.5625rem;">PILIH</span>
                                            <span style="background:rgba(0,0,0,0.25); padding:0.05rem 0.25rem; border-radius:3px; font-size:0.5625rem;">{{ $rate }}</span>
                                        </button>
                                    @elseif($st === 'AVAILABLE')
                                        <button type="button"
                                            wire:click="toggleSlot('{{ $court->id }}', '{{ addslashes($court->name) }}', '{{ $slot['start_time'] }}', '{{ $slot['end_time'] }}', {{ $slot['rate'] }})"
                                            class="slot-btn slot-available"
                                            title="Pilih: {{ $slot['full_label'] }} – Rp{{ number_format($slot['rate'],0,',','.') }}">
                                            <span style="font-size:0.5625rem; color:#047857;">Ada</span>
                                            <span style="font-size:0.625rem; color:#15803D; font-weight:900;">{{ $rate }}</span>
                                        </button>
                                    @elseif($st === 'BOOKED')
                                        <div class="slot-btn slot-booked" title="Terisi: {{ $slot['booking']['player'] ?? 'Pemain' }}">
                                            <span style="font-size:0.5rem; text-transform:uppercase;">Book</span>
                                            <span style="font-size:0.5rem; max-width:42px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $slot['booking']['player'] ?? 'Main' }}</span>
                                        </div>
                                    @elseif($st === 'LOCKED')
                                        <div class="slot-btn slot-locked" title="Hold di keranjang">
                                            <span style="font-size:0.5rem;">HOLD</span>
                                            <span style="font-size:0.5rem;">Cart</span>
                                        </div>
                                    @else
                                        <div class="slot-btn slot-past" title="Jam sudah lewat">
                                            <span style="font-size:0.5rem;">-</span>
                                        </div>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Ringkasan Okupansi & Riwayat Transaksi Walk-In --}}
        <div class="pos-grid-footer">
            <div class="pos-stats-row">
                <div class="pos-stat-card">
                    <div class="pos-stat-value">{{ $bookedSlotsAll }}/{{ $totalSlotsAll }}</div>
                    <div class="pos-stat-label">Slot Terisi Hari Ini</div>
                </div>
                <div class="pos-stat-card">
                    <div class="pos-stat-value">{{ $walkInStatsToday['count'] }}</div>
                    <div class="pos-stat-label">Transaksi Walk-In</div>
                </div>
                <div class="pos-stat-card">
                    <div class="pos-stat-value">Rp {{ number_format($walkInStatsToday['revenue'], 0, ',', '.') }}</div>
                    <div class="pos-stat-label">Omzet Walk-In</div>
                </div>
            </div>

            <div class="pos-recent-header">Transaksi Walk-In Terakhir</div>
            <div class="pos-recent-list">
                @forelse($recentWalkInOrders as $ro)
                    <div class="pos-recent-row">
                        <div>
                            <div class="pos-recent-name">{{ $ro->user?->name ?? 'Walk-In' }}</div>
                            <div class="pos-recent-sub">
                                {{ $ro->padelBookings->pluck('court.name')->filter()->unique()->implode(', ') ?: 'Lapangan' }}
                                &bull; {{ $ro->created_at->format('H:i') }}
                            </div>
                        </div>
                        <div style="text-align:right;">
                            <div class="pos-recent-amount">Rp {{ number_format($ro->grand_total, 0, ',', '.') }}</div>
                            <div class="pos-recent-badge pos-badge-{{ strtolower($ro->payment_status) }}">{{ $ro->payment_status }}</div>
                        </div>
                    </div>
                @empty
                    <div class="pos-recent-empty">Belum ada transaksi walk-in yang diproses hari ini.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ==================== KANAN: CHECKOUT PANEL ==================== --}}
    <div class="pos-panel-card">
        {{-- Header --}}
        <div class="pos-panel-header">
            <div>
                <div style="font-size:0.625rem; font-weight:900; color:#8C6418; text-transform:uppercase; letter-spacing:0.06em; background:#FAF5E8; border:1px solid #DFC387; border-radius:5px; padding:0.1rem 0.45rem; display:inline-block;">POS Kasir Loket</div>
                <div style="font-size:0.9375rem; font-weight:900; color:#1F170D; margin-top:0.2rem;">Walk-In Checkout</div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:1.125rem; font-weight:900; color:#B38622;">
                    Rp {{ number_format($this->grandTotal, 0, ',', '.') }}
                </div>
                <div style="font-size:0.625rem; color:#8C6418; font-weight:700;">{{ count($selectedSlots) }} slot dipilih</div>
            </div>
        </div>

        {{-- Scrollable Body --}}
        <div class="pos-panel-body">

            {{-- 1. DATA CUSTOMER --}}
            <div class="pos-customer-box">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                    <span class="pos-section-label">Data Customer</span>
                    <div class="pos-tab-group">
                        <button type="button" wire:click="setCustomerMode('quick_create')"
                            class="pos-tab {{ $customerMode === 'quick_create' ? 'active' : '' }}">Walk-In Baru</button>
                        <button type="button" wire:click="setCustomerMode('search')"
                            class="pos-tab {{ $customerMode === 'search' ? 'active' : '' }}">Cari Member</button>
                    </div>
                </div>

                @if($customerMode === 'quick_create')
                    <div style="display:flex; flex-direction:column; gap:0.4rem;">
                        <input type="text" wire:model="walkInName" placeholder="Nama Lengkap *"
                            class="pos-input" autocomplete="off">
                        <input type="tel" wire:model="walkInPhone" placeholder="No. WhatsApp / Telp *"
                            class="pos-input" autocomplete="off">
                        <input type="email" wire:model="walkInEmail" placeholder="Email (opsional)"
                            class="pos-input" autocomplete="off">
                        <div style="font-size:0.5625rem; color:#9CA3AF;">Nomor HP lama otomatis dikenali — tidak buat akun ganda.</div>
                    </div>
                @else
                    @if($selectedCustomerId)
                        <div style="background:#FFFFFF; border:1.5px solid #D4AF37; border-radius:8px; padding:0.5rem 0.65rem; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <div style="font-weight:900; color:#1F170D; font-size:0.8125rem;">{{ $selectedCustomerName }}</div>
                                <div style="font-size:0.6875rem; color:#8C6418;">{{ $selectedCustomerPhone ?? '-' }}</div>
                            </div>
                            <button type="button" wire:click="clearSelectedCustomer"
                                style="background:#FEF2F2; border:1px solid #FECACA; color:#DC2626; font-size:0.625rem; font-weight:800; padding:0.2rem 0.45rem; border-radius:5px; cursor:pointer;">
                                Ganti
                            </button>
                        </div>
                    @else
                        <div style="position:relative;">
                            <input type="text" wire:model.live.debounce.300ms="customerSearch"
                                placeholder="Cari nama atau nomor telp..." class="pos-input" autocomplete="off">
                            @if(count($searchResults) > 0)
                                <div style="position:absolute; top:100%; left:0; right:0; z-index:50; background:#FFFFFF; border:1.5px solid #DFC387; border-radius:8px; box-shadow:0 8px 20px rgba(0,0,0,0.1); margin-top:0.2rem; max-height:140px; overflow-y:auto;">
                                    @foreach($searchResults as $res)
                                        <button type="button" wire:click="selectCustomer('{{ $res->id }}')"
                                            style="width:100%; text-align:left; padding:0.4rem 0.65rem; border:none; border-bottom:1px solid #FAF2DE; background:#FFFFFF; cursor:pointer; font-size:0.75rem;"
                                            onmouseover="this.style.background='#FAF5E8'" onmouseout="this.style.background='#FFFFFF'">
                                            <div style="font-weight:800; color:#1F170D;">{{ $res->name }}</div>
                                            <div style="font-size:0.625rem; color:#8C6418;">{{ $res->phone ?? $res->email }}</div>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif(strlen(trim($customerSearch)) >= 2)
                                <div style="font-size:0.625rem; color:#9CA3AF; margin-top:0.25rem; font-style:italic;">Tidak ditemukan — pakai tab Walk-In Baru.</div>
                            @endif
                        </div>
                    @endif
                @endif
            </div>

            {{-- 2. SLOT TERPILIH --}}
            <div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
                    <span class="pos-section-label">Slot Dipilih ({{ count($selectedSlots) }})</span>
                    <span style="font-size:0.6875rem; font-weight:800; color:#B38622;">Rp {{ number_format($this->courtTotal, 0, ',', '.') }}</span>
                </div>
                @if(empty($selectedSlots))
                    <div style="background:#F9FAFB; border:1px dashed #D1D5DB; border-radius:8px; padding:0.85rem; text-align:center; color:#9CA3AF; font-size:0.6875rem;">
                        Klik kotak jam di tabel kiri untuk memilih slot.
                    </div>
                @else
                    <div style="display:flex; flex-direction:column; gap:0.3rem;">
                        @foreach($selectedSlots as $sKey => $s)
                            <div class="pos-slot-chip">
                                <div>
                                    <div style="font-weight:800; font-size:0.6875rem; color:#1F170D;">{{ $s['court_name'] }}</div>
                                    <div style="font-size:0.5625rem; color:#8C6418;">{{ $s['time_label'] }} WIB</div>
                                </div>
                                <div style="display:flex; align-items:center; gap:0.4rem;">
                                    <span style="font-weight:900; font-size:0.75rem; color:#B38622;">Rp{{ number_format($s['price'],0,',','.') }}</span>
                                    <button type="button" wire:click="removeSlot('{{ $sKey }}')"
                                        style="background:#FEF2F2; border:1px solid #FECACA; color:#DC2626; border-radius:50%; width:18px; height:18px; font-weight:900; font-size:0.625rem; cursor:pointer; display:flex; align-items:center; justify-content:center; line-height:1;">&times;</button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- 3. SEWA ALAT --}}
            @if(count($equipments) > 0)
            <div>
                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.4rem;">
                    <span class="pos-section-label">Sewa Alat (Opsional)</span>
                    <span style="font-size:0.6875rem; font-weight:800; color:#B38622;">Rp {{ number_format($this->equipmentTotal, 0, ',', '.') }}</span>
                </div>
                <div style="background:#FFFDF5; border:1px solid #F0DB9D; border-radius:8px; padding:0.35rem 0.65rem;">
                    @foreach($equipments as $eq)
                        @php $qty = $rentalQuantities[$eq->id] ?? 0; @endphp
                        <div class="pos-eq-row">
                            <div>
                                <div style="font-weight:800; color:#1F170D; font-size:0.6875rem;">{{ $eq->name }}</div>
                                <div style="font-size:0.5625rem; color:#8C6418;">Rp{{ number_format($eq->rental_price,0,',','.') }} &bull; Stok: {{ $eq->stock_quantity }}</div>
                            </div>
                            <div style="display:flex; align-items:center; gap:0.3rem;">
                                <button type="button" wire:click="decrementEquipment('{{ $eq->id }}')" class="pos-qty-btn">-</button>
                                <span style="min-width:20px; text-align:center; font-weight:900; font-size:0.8125rem; color:#1F170D;">{{ $qty }}</span>
                                <button type="button" wire:click="incrementEquipment('{{ $eq->id }}', {{ $eq->stock_quantity }})" class="pos-qty-btn">+</button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
            @endif

            {{-- 4. TOTAL --}}
            <div class="pos-total-box">
                <div style="display:flex; justify-content:space-between; font-size:0.6875rem; color:#7A643E; margin-bottom:0.2rem;">
                    <span>Lapangan:</span><span>Rp {{ number_format($this->courtTotal, 0, ',', '.') }}</span>
                </div>
                <div style="display:flex; justify-content:space-between; font-size:0.6875rem; color:#7A643E; margin-bottom:0.35rem;">
                    <span>Sewa Alat:</span><span>Rp {{ number_format($this->equipmentTotal, 0, ',', '.') }}</span>
                </div>
                <div style="border-top:1.5px dashed #D4AF37; padding-top:0.4rem; display:flex; justify-content:space-between; align-items:baseline;">
                    <span style="font-size:0.8125rem; font-weight:900; color:#1F170D;">TOTAL:</span>
                    <span style="font-size:1.1875rem; font-weight:900; color:#B38622;">Rp {{ number_format($this->grandTotal, 0, ',', '.') }}</span>
                </div>
            </div>

            {{-- 5. METODE BAYAR --}}
            <div>
                <div class="pos-section-label" style="margin-bottom:0.4rem;">Metode Pembayaran</div>
                <div class="pos-pm-grid">
                    @foreach(['CASH' => 'Tunai Kasir', 'EDC_BCA' => 'EDC BCA', 'EDC_MANDIRI' => 'EDC Mandiri', 'QRIS_STATIS' => 'QRIS Kasir'] as $mKey => $mLabel)
                        <label class="pos-pm-label {{ $paymentMethod === $mKey ? 'active' : '' }}">
                            <input type="radio" wire:model.live="paymentMethod" value="{{ $mKey }}" style="accent-color:#B38622; width:13px; height:13px;">
                            <span>{{ $mLabel }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            {{-- 6. AUTO CHECK-IN --}}
            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; background:#F0FDF4; border:1px solid #BBF7D0; border-radius:7px; padding:0.45rem 0.65rem;">
                <input type="checkbox" wire:model="isAutoCheckIn" style="accent-color:#10B981; width:15px; height:15px;">
                <div>
                    <div style="font-size:0.6875rem; font-weight:900; color:#166534;">Langsung Check-In (Software)</div>
                    <div style="font-size:0.5625rem; color:#15803D;">Status jadi CHECKED_IN saat order dibuat.</div>
                </div>
            </label>

        </div>{{-- end pos-panel-body --}}

        {{-- Footer: Submit Button --}}
        <div class="pos-panel-footer">
            <button type="button"
                wire:click="submitWalkInBooking"
                wire:loading.attr="disabled"
                class="pos-submit-btn">
                <span wire:loading.remove wire:target="submitWalkInBooking">Bayar Lunas &amp; Dispatch Tiket</span>
                <span wire:loading wire:target="submitWalkInBooking">Memproses Transaksi...</span>
            </button>
        </div>
    </div>{{-- end pos-panel-card --}}

</div>{{-- end pos-main --}}

{{-- ============================
     MODAL SUKSES – STRUK POS
     ============================ --}}
@if($showSuccessModal && $completedOrderData)
    <div style="position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.65); backdrop-filter:blur(6px); display:flex; align-items:center; justify-content:center; padding:1rem;">
        <div style="background:#FFFFFF; border:1.5px solid #DFC387; border-radius:18px; box-shadow:0 25px 50px -12px rgba(184,134,11,0.4); width:100%; max-width:440px; overflow:hidden; animation:fadeInUp 0.2s ease;">
            <style>
                @keyframes fadeInUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
            </style>

            <div style="background:linear-gradient(135deg,#FAF5E8 0%,#F5E8C7 100%); border-bottom:1.5px solid #DFC387; padding:0.85rem 1.1rem; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-size:0.625rem; font-weight:900; color:#8C6418; text-transform:uppercase; letter-spacing:0.06em; background:#FAF5E8; border:1px solid #DFC387; border-radius:4px; padding:0.08rem 0.4rem; display:inline-block;">TRANSAKSI LUNAS</div>
                    <div style="font-size:1rem; font-weight:900; color:#1F170D; margin-top:0.2rem;">Tiket Walk-In Siap Digunakan</div>
                </div>
                <button type="button" wire:click="closeSuccessModal" style="background:none; border:none; font-size:1.25rem; color:#78350F; cursor:pointer; line-height:1;">&times;</button>
            </div>

            <div id="printable-pos-receipt" style="padding:1rem 1.1rem; font-family:monospace; font-size:0.75rem; color:#111827; background:#FFFFFF;">
                <div style="text-align:center; border-bottom:1px dashed #000; padding-bottom:0.6rem; margin-bottom:0.6rem;">
                    <div style="font-weight:900; font-size:0.9375rem;">CLUB 61 PADEL ARENA</div>
                    <div style="font-size:0.6rem;">Jl. Karang Tengah Raya No. 61, Lebak Bulus</div>
                    <div style="font-size:0.6rem;">Frontdesk &amp; Reservation Counter</div>
                </div>

                <div style="border-bottom:1px dashed #000; padding-bottom:0.45rem; margin-bottom:0.45rem;">
                    <div>No. Order: <strong>{{ $completedOrderData['order_number'] }}</strong></div>
                    <div>Waktu: {{ $completedOrderData['created_at'] }}</div>
                    <div>Kasir: {{ $completedOrderData['cashier_name'] }}</div>
                    <div>Customer: {{ $completedOrderData['customer_name'] }} ({{ $completedOrderData['customer_phone'] }})</div>
                    <div>Metode: {{ $completedOrderData['payment_method'] }}</div>
                </div>

                <div style="border-bottom:1px dashed #000; padding-bottom:0.45rem; margin-bottom:0.45rem;">
                    <div style="font-weight:800; margin-bottom:0.2rem;">ITEM LAPANGAN:</div>
                    @foreach($completedOrderData['bookings'] as $b)
                        <div style="margin-bottom:0.3rem;">
                            <div style="display:flex; justify-content:space-between;">
                                <span>{{ $b['court_name'] }}</span>
                                <span>Rp {{ number_format($b['court_fee'], 0, ',', '.') }}</span>
                            </div>
                            <div style="font-size:0.6rem; color:#4B5563;">{{ $completedOrderData['booking_date'] }} &bull; {{ $b['time_label'] }} WIB</div>
                            <div style="font-size:0.6rem; font-weight:800;">Kode: {{ $b['booking_code'] }}</div>
                        </div>
                    @endforeach
                    @if(!empty($completedOrderData['equipments']))
                        <div style="font-weight:800; margin-top:0.3rem; margin-bottom:0.15rem;">SEWA ALAT:</div>
                        @foreach($completedOrderData['equipments'] as $eq)
                            <div style="display:flex; justify-content:space-between;">
                                <span>{{ $eq['quantity'] }}x {{ $eq['name'] }}</span>
                                <span>Rp {{ number_format($eq['price'], 0, ',', '.') }}</span>
                            </div>
                        @endforeach
                    @endif
                </div>

                <div style="border-bottom:1px dashed #000; padding-bottom:0.4rem; margin-bottom:0.5rem;">
                    <div style="display:flex; justify-content:space-between; font-weight:900; font-size:0.875rem;">
                        <span>TOTAL BAYAR:</span>
                        <span>Rp {{ number_format($completedOrderData['grand_total'], 0, ',', '.') }}</span>
                    </div>
                    <div style="font-size:0.6rem; margin-top:0.15rem;">
                        Status: <strong>LUNAS (PAID){{ $completedOrderData['auto_checked_in'] ? ' — CHECKED IN' : '' }}</strong>
                    </div>
                </div>

                <div style="text-align:center; font-size:0.6rem; color:#4B5563;">
                    <div>Terima kasih telah bermain di Club 61!</div>
                    <div>Tunjukkan kode tiket ini kepada petugas lapangan.</div>
                </div>
            </div>

            <style>
                @media print {
                    body * { visibility: hidden; }
                    #printable-pos-receipt, #printable-pos-receipt * { visibility: visible; }
                    #printable-pos-receipt { position:absolute; left:0; top:0; width:78mm; margin:0; padding:5mm; border:none !important; }
                }
            </style>

            <div style="background:#FAF5E8; border-top:1px solid #DFC387; padding:0.65rem 1.1rem; display:flex; justify-content:flex-end; gap:0.5rem;">
                <button type="button" onclick="window.print()"
                    style="padding:0.4rem 0.85rem; font-size:0.8125rem; font-weight:800; border:1.5px solid #DFC387; background:#FFFFFF; border-radius:7px; cursor:pointer; color:#1F170D;">
                    Cetak Struk
                </button>
                <button type="button" wire:click="closeSuccessModal"
                    style="padding:0.4rem 0.85rem; font-size:0.8125rem; font-weight:800; background:linear-gradient(180deg,#F0DB9D 0%,#D4AF37 35%,#B38622 100%); color:#281A05; border:1px solid #FBF0CE; border-radius:7px; cursor:pointer;">
                    Transaksi Baru
                </button>
            </div>
        </div>
    </div>
@endif

</div>{{-- end walkin-pos-root --}}
