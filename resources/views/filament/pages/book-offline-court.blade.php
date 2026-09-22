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
    /* ===== DRAFT RECOVERY BANNER ===== */
    .pos-draft-banner {
        background: linear-gradient(135deg, #FFFBEB 0%, #FEF3C7 100%);
        border: 1.5px solid #FCD34D;
        border-radius: 12px;
        padding: 0.65rem 1rem;
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        box-shadow: 0 2px 10px rgba(245, 158, 11, 0.12);
        animation: fadeInDown 0.25s ease;
        flex-shrink: 0;
    }
    @keyframes fadeInDown {
        from { opacity: 0; transform: translateY(-10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    .pos-draft-title {
        font-size: 0.8125rem;
        font-weight: 900;
        color: #92400E;
    }
    .pos-draft-desc {
        font-size: 0.6875rem;
        color: #B45309;
        margin-top: 0.1rem;
    }
    .pos-draft-resume-btn {
        padding: 0.4rem 0.9rem;
        font-size: 0.75rem;
        font-weight: 900;
        background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%);
        border: 1px solid #FBF0CE;
        color: #281A05;
        border-radius: 7px;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(184, 134, 11, 0.25);
        white-space: nowrap;
        transition: transform 0.1s;
    }
    .pos-draft-resume-btn:hover { transform: translateY(-1px); }
    .pos-draft-discard-btn {
        padding: 0.4rem 0.8rem;
        font-size: 0.75rem;
        font-weight: 800;
        background: #FFFFFF;
        border: 1.5px solid #FECACA;
        color: #DC2626;
        border-radius: 7px;
        cursor: pointer;
        white-space: nowrap;
    }
    .pos-draft-discard-btn:hover { background: #FEF2F2; }

    /* ===== TERMINAL LAYAR BAYAR (IN-PAGE PAYMENT) ===== */
    .pos-terminal-card {
        background: #FFFFFF;
        border: 1.5px solid #DFC387;
        border-radius: 14px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        min-height: 0;
    }
    .pos-terminal-header {
        padding: 0.75rem 1.2rem;
        background: linear-gradient(135deg, #FAF5E8 0%, #F5E8C7 100%);
        border-bottom: 1.5px solid #DFC387;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;
    }
    .pos-terminal-body {
        flex: 1;
        overflow-y: auto;
        padding: 1.2rem 1.4rem;
        display: flex;
        flex-direction: column;
        gap: 1.1rem;
    }
    .pos-method-selector-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 0.65rem;
    }
    .pos-method-tab {
        border: 2px solid #E5E7EB;
        background: #F9FAFB;
        border-radius: 10px;
        padding: 0.7rem 0.5rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.15s ease;
        user-select: none;
    }
    .pos-method-tab:hover {
        border-color: #DFC387;
        background: #FFFDF5;
    }
    .pos-method-tab.active {
        border-color: #B38622;
        background: linear-gradient(180deg, #FFFDF5 0%, #FAF5E8 100%);
        box-shadow: 0 4px 12px rgba(179, 134, 34, 0.18);
    }
    .pos-method-tab-title {
        font-size: 0.8125rem;
        font-weight: 900;
        color: #1F170D;
    }
    .pos-method-tab.active .pos-method-tab-title {
        color: #8C6418;
    }
    .pos-method-tab-sub {
        font-size: 0.625rem;
        color: #6B7280;
        margin-top: 0.15rem;
    }

    /* Form Card Pembayaran */
    .pos-pay-content-card {
        background: #FFFDF5;
        border: 1.5px solid #DFC387;
        border-radius: 12px;
        padding: 1.1rem 1.25rem;
    }

    /* Quick Cash Buttons */
    .quick-cash-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 0.5rem;
        margin-top: 0.5rem;
    }
    .quick-cash-btn {
        background: #FFFFFF;
        border: 1.5px solid #DFC387;
        color: #1F170D;
        font-weight: 800;
        font-size: 0.75rem;
        padding: 0.5rem 0.4rem;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.12s;
        text-align: center;
    }
    .quick-cash-btn:hover {
        background: #FAF5E8;
        border-color: #B38622;
        color: #8C6418;
    }

    /* Struk POS In-Page Card */
    .pos-receipt-inpage {
        background: #FFFFFF;
        border: 1.5px solid #DFC387;
        border-radius: 14px;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        min-height: 0;
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

    {{-- Indikator Shift Kasir Frontdesk --}}
    <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
        @if($activeShift)
            <div style="display:flex; align-items:center; gap:0.45rem; background:#ECFDF5; border:1.5px solid #6EE7B7; padding:0.25rem 0.65rem; border-radius:8px;">
                <span style="width:8px; height:8px; border-radius:50%; background:#10B981; display:inline-block; box-shadow:0 0 0 2px rgba(16,185,129,0.25);"></span>
                <span style="font-size:0.6875rem; font-weight:900; color:#065F46;">
                    SHIFT AKTIF: {{ $activeShift->shift_number }}
                </span>
                <span style="font-size:0.625rem; color:#047857; font-weight:600;">
                    ({{ $activeShift->openedBy?->name ?? 'Kasir' }} &bull; {{ $activeShift->opened_at->setTimezone('Asia/Jakarta')->format('H:i') }} WIB)
                </span>
            </div>
            <button type="button" wire:click="prepareCloseShift"
                style="padding:0.32rem 0.75rem; font-size:0.75rem; font-weight:800; background:#FFF1F2; border:1.5px solid #FECDD3; color:#BE123C; border-radius:8px; cursor:pointer; display:flex; align-items:center; gap:0.3rem;">
                Tutup Shift (Closing)
            </button>
        @else
            <div style="display:flex; align-items:center; gap:0.45rem; background:#FFF1F2; border:1.5px solid #FECDD3; padding:0.25rem 0.65rem; border-radius:8px;">
                <span style="width:8px; height:8px; border-radius:50%; background:#EF4444; display:inline-block;"></span>
                <span style="font-size:0.6875rem; font-weight:900; color:#9F1239;">
                    LOKET TUTUP (BELUM BUKA SHIFT)
                </span>
            </div>
            <button type="button" wire:click="openShiftModal"
                style="padding:0.32rem 0.75rem; font-size:0.75rem; font-weight:900; background:linear-gradient(180deg,#F0DB9D 0%,#D4AF37 35%,#B38622 100%); border:1px solid #FBF0CE; color:#281A05; border-radius:8px; cursor:pointer; display:flex; align-items:center; gap:0.3rem; box-shadow:0 2px 8px rgba(184,134,11,0.25);">
                Buka Shift Pagi
            </button>
        @endif
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
     BANNER AUTO-RECOVERY DRAF TRANSAKSI POS
     ============================ --}}
@if($hasPendingDraft && $pendingDraftSummary && $posStep === 'selection')
    <div class="pos-draft-banner">
        <div>
            <div class="pos-draft-title">Draf Transaksi Kasir Tersimpan</div>
            <div class="pos-draft-desc">
                Pelanggan: <strong>{{ $pendingDraftSummary['customerName'] }}</strong> &bull;
                {{ $pendingDraftSummary['slotsCount'] }} slot lapangan &bull;
                Total: <strong>Rp {{ number_format($pendingDraftSummary['grandTotal'], 0, ',', '.') }}</strong>
                (tersimpan otomatis pukul {{ $pendingDraftSummary['savedAt'] }} WIB)
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:0.5rem;">
            <button type="button" wire:click="discardDraft" class="pos-draft-discard-btn">
                Buang Draf
            </button>
            <button type="button" wire:click="resumeDraft" class="pos-draft-resume-btn">
                Lanjutkan Transaksi &rarr;
            </button>
        </div>
    </div>
@endif

{{-- ============================
     MAIN 2-COLUMN POS LAYOUT
     ============================ --}}
<div class="pos-main">

    @if($posStep === 'selection')
    {{-- ==================== KIRI: TIMETABLE GRID ==================== --}}
    <div class="pos-grid-card">
        <div class="pos-grid-header">
            <div>
                <div style="font-size:0.8125rem; font-weight:900; color:#1F170D;">Slot Lapangan &mdash; {{ !empty($operationalHours) ? $operationalHours[0]['label'] . ' sampai ' . substr(end($operationalHours)['end_time'], 0, 5) . ' WIB' : 'Jam Operasional' }}</div>
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
                                    @elseif($st === 'CLOSED')
                                        <div class="slot-btn slot-past" title="Di luar jam operasional lapangan">
                                            <span style="font-size:0.5rem; text-transform:uppercase; color:#9CA3AF;">Tutup</span>
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
    @elseif($posStep === 'payment')
        {{-- ==================== KIRI: TERMINAL PEMBAYARAN KASIR IN-PAGE ==================== --}}
        <div class="pos-terminal-card">
            <div class="pos-terminal-header">
                <div>
                    <div style="font-size:0.625rem; font-weight:900; color:#8C6418; text-transform:uppercase; letter-spacing:0.06em; background:#FAF5E8; border:1px solid #DFC387; border-radius:5px; padding:0.1rem 0.45rem; display:inline-block;">TERMINAL KASIR LOKET</div>
                    <div style="font-size:1.0625rem; font-weight:900; color:#1F170D; margin-top:0.2rem;">Layar Pembayaran &amp; Penyelesaian Transaksi</div>
                </div>
                <button type="button" wire:click="backToSelection"
                    style="padding:0.4rem 0.85rem; font-size:0.75rem; font-weight:800; border:1.5px solid #DFC387; background:#FFFFFF; border-radius:8px; cursor:pointer; color:#1F170D; display:flex; align-items:center; gap:0.3rem;">
                    &larr; Ubah Pilihan Slot
                </button>
            </div>

            <div class="pos-terminal-body">
                {{-- Tabs Metode Bayar --}}
                <div>
                    <div style="font-size:0.75rem; font-weight:900; color:#1F170D; margin-bottom:0.45rem;">Pilih Metode Pembayaran:</div>
                    <div class="pos-method-selector-grid">
                        <div wire:click="setPaymentMethod('CASH')" class="pos-method-tab {{ in_array($paymentMethod, ['CASH', 'TUNAI']) ? 'active' : '' }}">
                            <div class="pos-method-tab-title">TUNAI (CASH)</div>
                            <div class="pos-method-tab-sub">Uang Fisik di Laci</div>
                        </div>
                        <div wire:click="setPaymentMethod('DEBIT_CARD')" class="pos-method-tab {{ in_array($paymentMethod, ['DEBIT_CARD', 'DEBIT']) || ($paymentMethod === 'EDC_BCA' && $edcCardType === 'DEBIT') ? 'active' : '' }}">
                            <div class="pos-method-tab-title">KARTU DEBIT</div>
                            <div class="pos-method-tab-sub">Semua Bank (Via EDC)</div>
                        </div>
                        <div wire:click="setPaymentMethod('CREDIT_CARD')" class="pos-method-tab {{ in_array($paymentMethod, ['CREDIT_CARD', 'CREDIT']) || ($paymentMethod === 'EDC_BCA' && $edcCardType === 'CREDIT') || ($paymentMethod === 'EDC_MANDIRI') ? 'active' : '' }}">
                            <div class="pos-method-tab-title">KARTU KREDIT</div>
                            <div class="pos-method-tab-sub">Visa, MC, JCB, Amex</div>
                        </div>
                        <div wire:click="setPaymentMethod('QRIS')" class="pos-method-tab {{ in_array($paymentMethod, ['QRIS', 'QRIS_STATIS']) ? 'active' : '' }}">
                            <div class="pos-method-tab-title">QRIS</div>
                            <div class="pos-method-tab-sub">QR Code / E-Wallet</div>
                        </div>
                    </div>
                </div>

                {{-- Detail Form Metode Bayar --}}
                @if($paymentMethod === 'CASH')
                    <div class="pos-pay-content-card">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid #DFC387; padding-bottom:0.75rem;">
                            <div>
                                <div style="font-size:0.875rem; font-weight:900; color:#1F170D;">Pembayaran Tunai di Loket</div>
                                <div style="font-size:0.6875rem; color:#7A643E;">Masukkan uang yang diterima untuk menghitung kembalian laci kasir.</div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:0.625rem; font-weight:800; color:#8C6418; text-transform:uppercase;">Total Tagihan</div>
                                <div style="font-size:1.25rem; font-weight:900; color:#B38622;">Rp {{ number_format($this->grandTotal, 0, ',', '.') }}</div>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                            <div>
                                <label style="display:block; font-size:0.75rem; font-weight:900; color:#1F170D; margin-bottom:0.35rem;">
                                    Uang Diterima dari Pelanggan (Rp) *
                                </label>
                                <div style="position:relative;">
                                    <span style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); font-size:0.9375rem; font-weight:900; color:#8C6418;">Rp</span>
                                    <input type="number" wire:model.live.debounce.300ms="cashReceived" step="1000" min="0"
                                        style="width:100%; border:2px solid #D4AF37; border-radius:9px; padding:0.6rem 0.75rem 0.6rem 2.5rem; font-size:1rem; font-weight:900; color:#1F170D; background:#FFFFFF; outline:none;"
                                        placeholder="Contoh: 300000">
                                </div>

                                <div style="margin-top:0.6rem;">
                                    <div style="font-size:0.625rem; font-weight:800; color:#7A643E; text-transform:uppercase; margin-bottom:0.25rem;">Nominal Cepat:</div>
                                    <div class="quick-cash-grid">
                                        <button type="button" wire:click="setQuickCash({{ $this->grandTotal }})" class="quick-cash-btn" style="border-color:#B38622; background:#FAF5E8; color:#8C6418; font-weight:900;">
                                            Uang Pas
                                        </button>
                                        <button type="button" wire:click="setQuickCash(100000)" class="quick-cash-btn">100.000</button>
                                        <button type="button" wire:click="setQuickCash(200000)" class="quick-cash-btn">200.000</button>
                                        <button type="button" wire:click="setQuickCash(300000)" class="quick-cash-btn">300.000</button>
                                        <button type="button" wire:click="setQuickCash(500000)" class="quick-cash-btn">500.000</button>
                                        <button type="button" wire:click="setQuickCash(1000000)" class="quick-cash-btn">1.000.000</button>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <div style="background:#FFFFFF; border:1.5px solid #DFC387; border-radius:10px; padding:0.9rem; height:100%; display:flex; flex-direction:column; justify-content:space-between;">
                                    <div>
                                        <div style="font-size:0.6875rem; font-weight:800; color:#7A643E; text-transform:uppercase;">Kalkulator Kasir</div>
                                        <div style="margin-top:0.5rem; display:flex; justify-content:space-between; font-size:0.8125rem; color:#4B5563;">
                                            <span>Total Tagihan:</span>
                                            <span>Rp {{ number_format($this->grandTotal, 0, ',', '.') }}</span>
                                        </div>
                                        <div style="margin-top:0.3rem; display:flex; justify-content:space-between; font-size:0.8125rem; color:#4B5563;">
                                            <span>Diterima:</span>
                                            <span style="font-weight:800; color:#1F170D;">Rp {{ number_format((float) ($cashReceived ?? 0), 0, ',', '.') }}</span>
                                        </div>
                                    </div>

                                    <div style="border-top:1.5px dashed #DFC387; padding-top:0.6rem; margin-top:0.8rem;">
                                        @if((float) ($cashReceived ?? 0) < $this->grandTotal)
                                            <div style="background:#FEF2F2; border:1px solid #FECACA; border-radius:7px; padding:0.45rem 0.6rem; font-size:0.6875rem; color:#DC2626; font-weight:700;">
                                                Kurang: Rp {{ number_format($this->grandTotal - (float) ($cashReceived ?? 0), 0, ',', '.') }}
                                            </div>
                                        @else
                                            <div style="display:flex; justify-content:space-between; align-items:baseline;">
                                                <span style="font-size:0.875rem; font-weight:900; color:#065F46;">UANG KEMBALIAN:</span>
                                                <span style="font-size:1.25rem; font-weight:900; color:#059669;">Rp {{ number_format($cashChange, 0, ',', '.') }}</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                {{-- Form KARTU DEBIT --}}
                @elseif(in_array($paymentMethod, ['DEBIT_CARD', 'DEBIT']) || ($paymentMethod === 'EDC_BCA' && $edcCardType === 'DEBIT'))
                    <div class="pos-pay-content-card">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid #DFC387; padding-bottom:0.75rem;">
                            <div>
                                <div style="font-size:0.875rem; font-weight:900; color:#1F170D;">
                                    Pembayaran Kartu Debit (Debit Card)
                                </div>
                                <div style="font-size:0.6875rem; color:#7A643E;">
                                    Gesek, dip, atau tap kartu debit pada mesin EDC fisik kasir lalu catat rincian slip transaksi di bawah ini.
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:0.625rem; font-weight:800; color:#8C6418; text-transform:uppercase;">Nominal Charge EDC</div>
                                <div style="font-size:1.25rem; font-weight:900; color:#B38622;">Rp {{ number_format($this->grandTotal, 0, ',', '.') }}</div>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:0.85rem;">
                            <div>
                                <label style="display:block; font-size:0.75rem; font-weight:800; color:#1F170D; margin-bottom:0.3rem;">Mesin EDC Fisik *</label>
                                <select wire:model="edcTerminal" class="pos-input" style="background:#FFFFFF; font-weight:700;">
                                    <option value="EDC_BCA">Mesin EDC BCA</option>
                                    <option value="EDC_MANDIRI">Mesin EDC Mandiri</option>
                                    <option value="EDC_LAINNYA">Mesin EDC Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.75rem; font-weight:800; color:#1F170D; margin-bottom:0.3rem;">Bank Penerbit *</label>
                                <select wire:model="edcBank" class="pos-input" style="background:#FFFFFF; font-weight:700;">
                                    <option value="BCA">BCA</option>
                                    <option value="MANDIRI">Bank Mandiri</option>
                                    <option value="BNI">BNI</option>
                                    <option value="BRI">BRI</option>
                                    <option value="CIMB">CIMB Niaga</option>
                                    <option value="PERMATA">Bank Permata</option>
                                    <option value="DANAMON">Bank Danamon</option>
                                    <option value="BSI">BSI (Bank Syariah Indonesia)</option>
                                    <option value="LAINNYA">Bank Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.75rem; font-weight:800; color:#1F170D; margin-bottom:0.3rem;">Jaringan Kartu (Scheme)</label>
                                <select wire:model="edcCardNetwork" class="pos-input" style="background:#FFFFFF; font-weight:700;">
                                    <option value="GPN">GPN (Gerbang Pembayaran Nasional)</option>
                                    <option value="MASTERCARD">Mastercard Debit</option>
                                    <option value="VISA">Visa Debit</option>
                                    <option value="LAINNYA">Debit Lainnya</option>
                                </select>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:0.85rem; margin-top:0.85rem;">
                            <div>
                                <label style="display:block; font-size:0.75rem; font-weight:800; color:#1F170D; margin-bottom:0.3rem;">4 Digit Terakhir Kartu *</label>
                                <input type="text" wire:model="edcLast4" maxlength="4" placeholder="4 digit, contoh: 8842" class="pos-input" style="background:#FFFFFF; font-weight:800; letter-spacing:0.1em;" autocomplete="off">
                            </div>
                            <div>
                                <label style="display:block; font-size:0.75rem; font-weight:800; color:#1F170D; margin-bottom:0.3rem;">No. Approval / Auth Code *</label>
                                <input type="text" wire:model="edcApprovalCode" placeholder="Tertera di slip EDC, contoh: 128941" class="pos-input" style="background:#FFFFFF; font-weight:800;" autocomplete="off">
                            </div>
                            <div>
                                <label style="display:block; font-size:0.75rem; font-weight:800; color:#1F170D; margin-bottom:0.3rem;">No. Trace / Audit Slip EDC *</label>
                                <input type="text" wire:model="edcTraceNumber" placeholder="Tertera di slip EDC, contoh: 004812" class="pos-input" style="background:#FFFFFF; font-weight:800;" autocomplete="off">
                            </div>
                        </div>

                        <div style="margin-top:0.85rem; background:#FAF5E8; border:1px dashed #DFC387; border-radius:8px; padding:0.5rem 0.75rem; font-size:0.65rem; color:#7A643E;">
                            Keamanan PCI-DSS: Sistem hanya mencatat 4 digit terakhir kartu fisik sebagai bukti rekonsiliasi slip audit perbankan. Dilarang mencatat atau meminta nomor kartu lengkap maupun kode CVV.
                        </div>
                    </div>

                {{-- Form KARTU KREDIT --}}
                @elseif(in_array($paymentMethod, ['CREDIT_CARD', 'CREDIT']) || ($paymentMethod === 'EDC_BCA' && $edcCardType === 'CREDIT') || ($paymentMethod === 'EDC_MANDIRI'))
                    <div class="pos-pay-content-card">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid #DFC387; padding-bottom:0.75rem;">
                            <div>
                                <div style="font-size:0.875rem; font-weight:900; color:#1F170D;">
                                    Pembayaran Kartu Kredit (Credit Card)
                                </div>
                                <div style="font-size:0.6875rem; color:#7A643E;">
                                    Gesek, dip, atau tap kartu kredit pada mesin EDC fisik kasir lalu catat rincian slip transaksi di bawah ini.
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:0.625rem; font-weight:800; color:#8C6418; text-transform:uppercase;">Nominal Charge EDC</div>
                                <div style="font-size:1.25rem; font-weight:900; color:#B38622;">Rp {{ number_format($this->grandTotal, 0, ',', '.') }}</div>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:0.85rem;">
                            <div>
                                <label style="display:block; font-size:0.75rem; font-weight:800; color:#1F170D; margin-bottom:0.3rem;">Mesin EDC Fisik *</label>
                                <select wire:model="edcTerminal" class="pos-input" style="background:#FFFFFF; font-weight:700;">
                                    <option value="EDC_BCA">Mesin EDC BCA</option>
                                    <option value="EDC_MANDIRI">Mesin EDC Mandiri</option>
                                    <option value="EDC_LAINNYA">Mesin EDC Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.75rem; font-weight:800; color:#1F170D; margin-bottom:0.3rem;">Bank Penerbit *</label>
                                <select wire:model="edcBank" class="pos-input" style="background:#FFFFFF; font-weight:700;">
                                    <option value="BCA">BCA</option>
                                    <option value="MANDIRI">Bank Mandiri</option>
                                    <option value="BNI">BNI</option>
                                    <option value="BRI">BRI</option>
                                    <option value="CIMB">CIMB Niaga</option>
                                    <option value="MEGA">Bank Mega</option>
                                    <option value="PERMATA">Bank Permata</option>
                                    <option value="OVERSEAS">Bank Internasional / Luar Negeri</option>
                                    <option value="LAINNYA">Bank Lainnya</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.75rem; font-weight:800; color:#1F170D; margin-bottom:0.3rem;">Brand Jaringan Kartu *</label>
                                <select wire:model="edcCardNetwork" class="pos-input" style="background:#FFFFFF; font-weight:700;">
                                    <option value="VISA">Visa</option>
                                    <option value="MASTERCARD">Mastercard</option>
                                    <option value="JCB">JCB</option>
                                    <option value="AMEX">American Express (Amex)</option>
                                    <option value="UNIONPAY">UnionPay</option>
                                    <option value="LAINNYA">Brand Lainnya</option>
                                </select>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:0.85rem; margin-top:0.85rem;">
                            <div>
                                <label style="display:block; font-size:0.75rem; font-weight:800; color:#1F170D; margin-bottom:0.3rem;">4 Digit Terakhir Kartu *</label>
                                <input type="text" wire:model="edcLast4" maxlength="4" placeholder="4 digit, contoh: 8842" class="pos-input" style="background:#FFFFFF; font-weight:800; letter-spacing:0.1em;" autocomplete="off">
                            </div>
                            <div>
                                <label style="display:block; font-size:0.75rem; font-weight:800; color:#1F170D; margin-bottom:0.3rem;">No. Approval / Auth Code *</label>
                                <input type="text" wire:model="edcApprovalCode" placeholder="Tertera di slip EDC, contoh: 128941" class="pos-input" style="background:#FFFFFF; font-weight:800;" autocomplete="off">
                            </div>
                            <div>
                                <label style="display:block; font-size:0.75rem; font-weight:800; color:#1F170D; margin-bottom:0.3rem;">No. Trace / Audit Slip EDC *</label>
                                <input type="text" wire:model="edcTraceNumber" placeholder="Tertera di slip EDC, contoh: 004812" class="pos-input" style="background:#FFFFFF; font-weight:800;" autocomplete="off">
                            </div>
                        </div>

                        <div style="margin-top:0.85rem; background:#FAF5E8; border:1px dashed #DFC387; border-radius:8px; padding:0.5rem 0.75rem; font-size:0.65rem; color:#7A643E;">
                            Keamanan PCI-DSS: Sistem hanya mencatat 4 digit terakhir kartu fisik sebagai bukti rekonsiliasi slip audit perbankan. Dilarang mencatat atau meminta nomor kartu lengkap maupun kode CVV.
                        </div>
                    </div>

                {{-- Form QRIS --}}
                @elseif(in_array($paymentMethod, ['QRIS', 'QRIS_STATIS']))
                    <div class="pos-pay-content-card">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; border-bottom:1px solid #DFC387; padding-bottom:0.75rem;">
                            <div>
                                <div style="font-size:0.875rem; font-weight:900; color:#1F170D;">Pembayaran QRIS (QR Code)</div>
                                <div style="font-size:0.6875rem; color:#7A643E;">Pelanggan memindai QRIS kasir frontdesk dan pastikan transaksi berhasil di aplikasi customer.</div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:0.625rem; font-weight:800; color:#8C6418; text-transform:uppercase;">Total Bayar QRIS</div>
                                <div style="font-size:1.25rem; font-weight:900; color:#B38622;">Rp {{ number_format($this->grandTotal, 0, ',', '.') }}</div>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.85rem;">
                            <div>
                                <label style="display:block; font-size:0.75rem; font-weight:800; color:#1F170D; margin-bottom:0.3rem;">Penyedia / Acquirer QRIS *</label>
                                <select wire:model="qrisProvider" class="pos-input" style="background:#FFFFFF; font-weight:700;">
                                    <option value="BCA_QRIS">QRIS BCA Frontdesk</option>
                                    <option value="MANDIRI_QRIS">QRIS Bank Mandiri</option>
                                    <option value="GOPAY">GoPay / Midtrans QRIS</option>
                                    <option value="OVO">OVO</option>
                                    <option value="SHOPEEPAY">ShopeePay</option>
                                    <option value="DANA">DANA</option>
                                    <option value="LIVIN">Livin Mandiri</option>
                                    <option value="LAINNYA">Lainnya / Bank Lain</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:0.75rem; font-weight:800; color:#1F170D; margin-bottom:0.3rem;">Nomor RRN (Retrieval Reference Number) *</label>
                                <input type="text" wire:model="qrisRrn" placeholder="Min. 6 digit di mutasi / resi app customer" class="pos-input" style="background:#FFFFFF; font-weight:800;" autocomplete="off">
                            </div>
                        </div>

                        <div style="margin-top:0.85rem;">
                            <label style="display:block; font-size:0.75rem; font-weight:800; color:#1F170D; margin-bottom:0.3rem;">Nama Pengirim di Resi QRIS (Opsional)</label>
                            <input type="text" wire:model="qrisSenderName" placeholder="Contoh: Budi Santoso / BCA Mobile" class="pos-input" style="background:#FFFFFF;" autocomplete="off">
                        </div>
                    </div>
                @endif

                {{-- Action Buttons --}}
                <div style="display:flex; justify-content:space-between; align-items:center; gap:0.75rem; margin-top:0.5rem;">
                    <button type="button" wire:click="backToSelection"
                        style="padding:0.7rem 1.2rem; border-radius:10px; border:1.5px solid #DFC387; background:#FFFFFF; color:#1F170D; font-weight:800; font-size:0.8125rem; cursor:pointer;">
                        &larr; Kembali ke Pilih Jadwal
                    </button>
                    <button type="button" wire:click="submitWalkInBooking" wire:loading.attr="disabled"
                        style="flex:1; padding:0.75rem 1.5rem; border-radius:10px; background:linear-gradient(180deg,#F0DB9D 0%,#D4AF37 30%,#B38622 100%); color:#281A05; border:1px solid #FBF0CE; font-weight:900; font-size:0.9375rem; cursor:pointer; box-shadow:0 4px 14px rgba(184,134,11,0.35); text-transform:uppercase; letter-spacing:0.05em;">
                        <span wire:loading.remove wire:target="submitWalkInBooking">Bayar Lunas &amp; Cetak Struk</span>
                        <span wire:loading wire:target="submitWalkInBooking">Memproses Transaksi...</span>
                    </button>
                </div>
            </div>
        </div>

    @elseif($posStep === 'receipt')
        {{-- ==================== KIRI: STRUK POS RESMI IN-PAGE ==================== --}}
        <div class="pos-receipt-inpage">
            <div class="pos-terminal-header">
                <div>
                    <div style="font-size:0.625rem; font-weight:900; color:#065F46; text-transform:uppercase; letter-spacing:0.06em; background:#ECFDF5; border:1px solid #6EE7B7; border-radius:5px; padding:0.1rem 0.45rem; display:inline-block;">TRANSAKSI SELESAI</div>
                    <div style="font-size:1.0625rem; font-weight:900; color:#1F170D; margin-top:0.2rem;">Struk Pembayaran POS &amp; E-Tiket Walk-In</div>
                </div>
                <div style="display:flex; gap:0.5rem;">
                    <button type="button" onclick="window.print()"
                        style="padding:0.4rem 0.85rem; font-size:0.75rem; font-weight:800; border:1.5px solid #DFC387; background:#FFFFFF; border-radius:8px; cursor:pointer; color:#1F170D;">
                        Cetak Struk
                    </button>
                    <button type="button" wire:click="startNewTransaction"
                        style="padding:0.4rem 1rem; font-size:0.75rem; font-weight:900; background:linear-gradient(180deg,#F0DB9D 0%,#D4AF37 35%,#B38622 100%); color:#281A05; border:1px solid #FBF0CE; border-radius:8px; cursor:pointer;">
                        Transaksi Baru
                    </button>
                </div>
            </div>

            <div style="flex:1; overflow-y:auto; padding:1.25rem 2rem; background:#F9FAFB; display:flex; justify-content:center;">
                @if($completedOrderData)
                    <div id="printable-pos-receipt" style="background:#FFFFFF; border:1px solid #E5E7EB; box-shadow:0 4px 15px rgba(0,0,0,0.06); padding:1.5rem; width:100%; max-width:420px; font-family:monospace; font-size:0.75rem; color:#111827; border-radius:8px;">
                        <div style="text-align:center; border-bottom:1px dashed #000; padding-bottom:0.75rem; margin-bottom:0.75rem;">
                            <div style="font-weight:900; font-size:1rem; letter-spacing:0.05em;">CLUB 61 PADEL ARENA</div>
                            <div style="font-size:0.65rem; color:#4B5563;">Jl. Karang Tengah Raya No. 61, Lebak Bulus</div>
                            <div style="font-size:0.65rem; color:#4B5563;">Frontdesk &amp; Reservation Counter</div>
                        </div>

                        <div style="border-bottom:1px dashed #000; padding-bottom:0.5rem; margin-bottom:0.5rem; line-height:1.4;">
                            <div>No. Order: <strong>{{ $completedOrderData['order_number'] }}</strong></div>
                            <div>Waktu: {{ $completedOrderData['created_at'] }}</div>
                            <div>Kasir: {{ $completedOrderData['cashier_name'] }}</div>
                            <div>Customer: {{ $completedOrderData['customer_name'] }} ({{ $completedOrderData['customer_phone'] }})</div>
                            <div>Metode: <strong>{{ $completedOrderData['payment_method'] }}</strong></div>

                            @if(!empty($completedOrderData['payment_meta']))
                                @php $pm = $completedOrderData['payment_meta']; @endphp
                                @if(isset($pm['terminal']) || isset($pm['card_last_4']))
                                    <div style="font-size:0.65rem; color:#4B5563; margin-top:0.2rem;">
                                        Kartu: {{ $pm['card_type'] ?? 'CARD' }}{{ !empty($pm['card_network']) ? ' ('.$pm['card_network'].')' : '' }} &bull; {{ $pm['card_issuer'] ?? '' }} (**** {{ $pm['card_last_4'] }})
                                    </div>
                                    <div style="font-size:0.65rem; color:#4B5563;">
                                        Appr: {{ $pm['approval_code'] }} &bull; Trace: {{ $pm['trace_number'] }} &bull; Mesin: {{ $pm['terminal'] ?? '-' }}
                                    </div>
                                @elseif(isset($pm['qris_provider']))
                                    <div style="font-size:0.65rem; color:#4B5563; margin-top:0.2rem;">
                                        QRIS: {{ $pm['qris_provider'] }} &bull; RRN: {{ $pm['qris_rrn'] }}
                                    </div>
                                @elseif(isset($pm['cash_received']))
                                    <div style="font-size:0.65rem; color:#4B5563; margin-top:0.2rem;">
                                        Tunai: Rp {{ number_format($pm['cash_received'], 0, ',', '.') }} &bull; Kembali: Rp {{ number_format($pm['cash_change'], 0, ',', '.') }}
                                    </div>
                                @endif
                            @endif
                        </div>

                        <div style="border-bottom:1px dashed #000; padding-bottom:0.5rem; margin-bottom:0.5rem;">
                            <div style="font-weight:800; margin-bottom:0.25rem;">ITEM LAPANGAN:</div>
                            @foreach($completedOrderData['bookings'] as $b)
                                <div style="margin-bottom:0.35rem;">
                                    <div style="display:flex; justify-content:space-between;">
                                        <span>{{ $b['court_name'] }}</span>
                                        <span>Rp {{ number_format($b['court_fee'], 0, ',', '.') }}</span>
                                    </div>
                                    <div style="font-size:0.625rem; color:#4B5563;">{{ $completedOrderData['booking_date'] }} &bull; {{ $b['time_label'] }} WIB</div>
                                    <div style="font-size:0.625rem; font-weight:800; color:#1F170D;">Kode: {{ $b['booking_code'] }}</div>
                                </div>
                            @endforeach

                            @if(!empty($completedOrderData['equipments']))
                                <div style="font-weight:800; margin-top:0.4rem; margin-bottom:0.2rem;">SEWA ALAT:</div>
                                @foreach($completedOrderData['equipments'] as $eq)
                                    <div style="display:flex; justify-content:space-between;">
                                        <span>{{ $eq['quantity'] }}x {{ $eq['name'] }}</span>
                                        <span>Rp {{ number_format($eq['price'], 0, ',', '.') }}</span>
                                    </div>
                                @endforeach
                            @endif

                            @if(!empty($completedOrderData['tax_amount']) && $completedOrderData['tax_amount'] > 0)
                                <div style="display:flex; justify-content:space-between; font-size:0.65rem; margin-top:0.35rem; color:#4B5563;">
                                    <span>{{ $completedOrderData['tax_name'] ?? 'Pajak Daerah' }}</span>
                                    <span>Rp {{ number_format($completedOrderData['tax_amount'], 0, ',', '.') }}</span>
                                </div>
                            @endif
                            @if(!empty($completedOrderData['service_charge']) && $completedOrderData['service_charge'] > 0)
                                <div style="display:flex; justify-content:space-between; font-size:0.65rem; margin-top:0.15rem; color:#4B5563;">
                                    <span>{{ $completedOrderData['admin_fee_name'] ?? 'Biaya Layanan' }}</span>
                                    <span>Rp {{ number_format($completedOrderData['service_charge'], 0, ',', '.') }}</span>
                                </div>
                            @endif
                        </div>

                        <div style="border-bottom:1px dashed #000; padding-bottom:0.5rem; margin-bottom:0.6rem;">
                            <div style="display:flex; justify-content:space-between; font-weight:900; font-size:0.9375rem;">
                                <span>TOTAL BAYAR:</span>
                                <span>Rp {{ number_format($completedOrderData['grand_total'], 0, ',', '.') }}</span>
                            </div>
                            <div style="font-size:0.65rem; margin-top:0.2rem;">
                                Status: <strong>LUNAS (PAID){{ $completedOrderData['auto_checked_in'] ? ' — CHECKED IN' : '' }}</strong>
                            </div>
                        </div>

                        <div style="text-align:center; font-size:0.625rem; color:#4B5563; line-height:1.3;">
                            <div>Terima kasih telah bermain di Club 61!</div>
                            <div>Tunjukkan struk ini kepada petugas lapangan.</div>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    @endif

    {{-- ==================== KANAN: CHECKOUT PANEL ==================== --}}
    <div class="pos-panel-card">
        {{-- Header --}}
        <div class="pos-panel-header">
            <div>
                @if($posStep === 'payment')
                    <div style="font-size:0.625rem; font-weight:900; color:#8C6418; text-transform:uppercase; letter-spacing:0.06em; background:#FAF5E8; border:1px solid #DFC387; border-radius:5px; padding:0.1rem 0.45rem; display:inline-block;">LANGKAH 2 DARI 2</div>
                    <div style="font-size:0.9375rem; font-weight:900; color:#1F170D; margin-top:0.2rem;">Ringkasan Tagihan</div>
                @elseif($posStep === 'receipt')
                    <div style="font-size:0.625rem; font-weight:900; color:#065F46; text-transform:uppercase; letter-spacing:0.06em; background:#ECFDF5; border:1px solid #6EE7B7; border-radius:5px; padding:0.1rem 0.45rem; display:inline-block;">TRANSAKSI SELESAI</div>
                    <div style="font-size:0.9375rem; font-weight:900; color:#1F170D; margin-top:0.2rem;">Detail Reservasi</div>
                @else
                    <div style="font-size:0.625rem; font-weight:900; color:#8C6418; text-transform:uppercase; letter-spacing:0.06em; background:#FAF5E8; border:1px solid #DFC387; border-radius:5px; padding:0.1rem 0.45rem; display:inline-block;">POS Kasir Loket</div>
                    <div style="font-size:0.9375rem; font-weight:900; color:#1F170D; margin-top:0.2rem;">Walk-In Checkout</div>
                @endif
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
                    @if($posStep === 'selection')
                    <div class="pos-tab-group">
                        <button type="button" wire:click="setCustomerMode('quick_create')"
                            class="pos-tab {{ $customerMode === 'quick_create' ? 'active' : '' }}">Walk-In Baru</button>
                        <button type="button" wire:click="setCustomerMode('search')"
                            class="pos-tab {{ $customerMode === 'search' ? 'active' : '' }}">Cari Member</button>
                    </div>
                    @endif
                </div>

                @if($customerMode === 'quick_create')
                    <div style="display:flex; flex-direction:column; gap:0.4rem;">
                        <input type="text" wire:model="walkInName" placeholder="Nama Lengkap *"
                            class="pos-input" autocomplete="off" {{ $posStep !== 'selection' ? 'disabled' : '' }}>
                        <input type="tel" wire:model="walkInPhone" placeholder="No. WhatsApp / Telp *"
                            class="pos-input" autocomplete="off" {{ $posStep !== 'selection' ? 'disabled' : '' }}>
                        <input type="email" wire:model="walkInEmail" placeholder="Email (opsional)"
                            class="pos-input" autocomplete="off" {{ $posStep !== 'selection' ? 'disabled' : '' }}>
                        <div style="font-size:0.5625rem; color:#9CA3AF;">Nomor HP lama otomatis dikenali — tidak buat akun ganda.</div>
                    </div>
                @else
                    @if($selectedCustomerId)
                        <div style="background:#FFFFFF; border:1.5px solid #D4AF37; border-radius:8px; padding:0.5rem 0.65rem; display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <div style="font-weight:900; color:#1F170D; font-size:0.8125rem;">{{ $selectedCustomerName }}</div>
                                <div style="font-size:0.6875rem; color:#8C6418;">{{ $selectedCustomerPhone ?? '-' }}</div>
                                @if($activeMembershipInfo)
                                    <div style="margin-top:0.25rem; display:flex; align-items:center; gap:0.35rem; flex-wrap:wrap;">
                                        <div style="display:inline-flex; align-items:center; gap:0.25rem; background:#FEF3C7; border:1px solid #F59E0B; border-radius:4px; padding:0.15rem 0.35rem; font-size:0.65rem; color:#92400E; font-weight:700;">
                                            <span>{{ $activeMembershipInfo['plan_name'] }}</span>
                                            <span>•</span>
                                            <span>
                                                @if($activeMembershipInfo['quota_type'] === 'HOURS')
                                                    Sisa: {{ number_format($activeMembershipInfo['remaining_quota'], 1) }} Jam
                                                @elseif($activeMembershipInfo['discount_percent'] > 0)
                                                    Diskon {{ $activeMembershipInfo['discount_percent'] }}%
                                                @else
                                                    Member
                                                @endif
                                            </span>
                                        </div>
                                        @if($posStep === 'selection')
                                        <button type="button" wire:click="toggleMembershipBenefit"
                                            style="display:inline-flex; align-items:center; gap:0.3rem; background:{{ $useMembershipBenefit ? '#ECFDF5' : '#F3F4F6' }}; border:1px solid {{ $useMembershipBenefit ? '#6EE7B7' : '#D1D5DB' }}; border-radius:999px; padding:0.15rem 0.5rem 0.15rem 0.3rem; font-size:0.6rem; font-weight:800; color:{{ $useMembershipBenefit ? '#047857' : '#6B7280' }}; cursor:pointer;"
                                            title="{{ $useMembershipBenefit ? 'Klik untuk tidak memakai benefit membership' : 'Klik untuk memakai benefit membership' }}">
                                            <span style="width:0.55rem; height:0.55rem; border-radius:999px; background:{{ $useMembershipBenefit ? '#10B981' : '#9CA3AF' }};"></span>
                                            {{ $useMembershipBenefit ? 'Benefit Dipakai' : 'Benefit Dimatikan' }}
                                        </button>
                                        @endif
                                    </div>
                                @endif
                            </div>
                            @if($posStep === 'selection')
                            <button type="button" wire:click="clearSelectedCustomer"
                                style="background:#FEF2F2; border:1px solid #FECACA; color:#DC2626; font-size:0.625rem; font-weight:800; padding:0.2rem 0.45rem; border-radius:5px; cursor:pointer;">
                                Ganti
                            </button>
                            @endif
                        </div>
                    @else
                        <div style="position:relative;">
                            <input type="text" wire:model.live.debounce.300ms="customerSearch"
                                placeholder="Cari nama atau nomor telp..." class="pos-input" autocomplete="off" {{ $posStep !== 'selection' ? 'disabled' : '' }}>
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
                                    @if($posStep === 'selection')
                                    <button type="button" wire:click="removeSlot('{{ $sKey }}')"
                                        style="background:#FEF2F2; border:1px solid #FECACA; color:#DC2626; border-radius:50%; width:18px; height:18px; font-weight:900; font-size:0.625rem; cursor:pointer; display:flex; align-items:center; justify-content:center; line-height:1;">&times;</button>
                                    @endif
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
                                @if($posStep === 'selection')
                                <button type="button" wire:click="decrementEquipment('{{ $eq->id }}')" class="pos-qty-btn">-</button>
                                @endif
                                <span style="min-width:20px; text-align:center; font-weight:900; font-size:0.8125rem; color:#1F170D;">{{ $qty }}</span>
                                @if($posStep === 'selection')
                                <button type="button" wire:click="incrementEquipment('{{ $eq->id }}', {{ $eq->stock_quantity }})" class="pos-qty-btn">+</button>
                                @endif
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
                <div style="display:flex; justify-content:space-between; font-size:0.6875rem; color:#7A643E; margin-bottom:0.2rem;">
                    <span>Sewa Alat:</span><span>Rp {{ number_format($this->equipmentTotal, 0, ',', '.') }}</span>
                </div>
                @if($this->membershipDiscountAmount > 0)
                <div style="display:flex; justify-content:space-between; font-size:0.6875rem; color:#047857; font-weight:800; margin-bottom:0.2rem;">
                    <span>Diskon Membership ({{ $activeMembershipInfo['plan_name'] ?? 'Member' }}):</span>
                    <span>- Rp {{ number_format($this->membershipDiscountAmount, 0, ',', '.') }}</span>
                </div>
                @endif
                @if($this->isTaxEnabled && $this->taxAmount > 0)
                <div style="display:flex; justify-content:space-between; font-size:0.6875rem; color:#7A643E; margin-bottom:0.2rem;">
                    <span>{{ $this->taxName }}:</span><span>Rp {{ number_format($this->taxAmount, 0, ',', '.') }}</span>
                </div>
                @endif
                @if($this->isAdminFeeEnabled && $this->adminFeeAmount > 0)
                <div style="display:flex; justify-content:space-between; font-size:0.6875rem; color:#7A643E; margin-bottom:0.2rem;">
                    <span>{{ $this->adminFeeName }}:</span><span>Rp {{ number_format($this->adminFeeAmount, 0, ',', '.') }}</span>
                </div>
                @endif
                <div style="border-top:1.5px dashed #D4AF37; padding-top:0.4rem; display:flex; justify-content:space-between; align-items:baseline;">
                    <span style="font-size:0.8125rem; font-weight:900; color:#1F170D;">TOTAL:</span>
                    <span style="font-size:1.1875rem; font-weight:900; color:#B38622;">Rp {{ number_format($this->grandTotal, 0, ',', '.') }}</span>
                </div>
            </div>

            {{-- 5. METODE BAYAR (Ringkasan saat step payment / receipt) --}}
            @if($posStep !== 'selection')
            <div>
                <div class="pos-section-label" style="margin-bottom:0.4rem;">Metode Pembayaran</div>
                <div style="background:#FFFDF5; border:1.5px solid #DFC387; border-radius:8px; padding:0.45rem 0.65rem; font-size:0.75rem; font-weight:800; color:#8C6418;">
                    {{ match($paymentMethod) {
                        'CASH', 'TUNAI' => 'Tunai Kasir (Cash)',
                        'DEBIT_CARD', 'DEBIT' => 'Kartu Debit (EDC)',
                        'CREDIT_CARD', 'CREDIT' => 'Kartu Kredit (EDC)',
                        'EDC_BCA' => 'Mesin EDC BCA',
                        'EDC_MANDIRI' => 'Mesin EDC Mandiri',
                        'QRIS', 'QRIS_STATIS' => 'QRIS Kasir Frontdesk',
                        default => $paymentMethod
                    } }}
                </div>
            </div>
            @endif

            {{-- 6. AUTO CHECK-IN --}}
            <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer; background:#F0FDF4; border:1px solid #BBF7D0; border-radius:7px; padding:0.45rem 0.65rem;">
                <input type="checkbox" wire:model="isAutoCheckIn" style="accent-color:#10B981; width:15px; height:15px;">
                <div>
                    <div style="font-size:0.6875rem; font-weight:900; color:#166534;">Langsung Check-In (Software)</div>
                    <div style="font-size:0.5625rem; color:#15803D;">Status jadi CHECKED_IN saat order dibuat.</div>
                </div>
            </label>

        </div>{{-- end pos-panel-body --}}

        {{-- Footer: Action Button --}}
        <div class="pos-panel-footer">
            @if($posStep === 'selection')
                <button type="button"
                    wire:click="proceedToPayment"
                    class="pos-submit-btn">
                    <span>Lanjut ke Pembayaran &rarr;</span>
                </button>
            @elseif($posStep === 'payment')
                <button type="button"
                    wire:click="backToSelection"
                    style="width:100%; padding:0.65rem; border-radius:10px; border:1.5px solid #DFC387; background:#FFFFFF; color:#1F170D; font-weight:800; font-size:0.8125rem; cursor:pointer;">
                    &larr; Ubah Pilihan Slot
                </button>
            @elseif($posStep === 'receipt')
                <button type="button"
                    wire:click="startNewTransaction"
                    class="pos-submit-btn">
                    <span>Mulai Transaksi Baru</span>
                </button>
            @endif
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

{{-- ============================
     MODAL BUKA SHIFT KASIR
     ============================ --}}
@if($showOpenShiftModal)
    <div style="position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.65); backdrop-filter:blur(6px); display:flex; align-items:center; justify-content:center; padding:1rem;">
        <div style="background:#FFFFFF; border:1.5px solid #DFC387; border-radius:18px; box-shadow:0 25px 50px -12px rgba(184,134,11,0.4); width:100%; max-width:460px; overflow:hidden; animation:fadeInUp 0.2s ease;">
            <div style="background:linear-gradient(135deg,#FAF5E8 0%,#F5E8C7 100%); border-bottom:1.5px solid #DFC387; padding:0.85rem 1.1rem; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-size:0.625rem; font-weight:900; color:#8C6418; text-transform:uppercase; letter-spacing:0.06em; background:#FAF5E8; border:1px solid #DFC387; border-radius:4px; padding:0.08rem 0.4rem; display:inline-block;">REGISTER OPENING</div>
                    <div style="font-size:1rem; font-weight:900; color:#1F170D; margin-top:0.2rem;">Buka Shift Kasir Baru</div>
                </div>
                <button type="button" wire:click="$set('showOpenShiftModal', false)" style="background:none; border:none; font-size:1.25rem; color:#78350F; cursor:pointer; line-height:1;">&times;</button>
            </div>

            <div style="padding:1.1rem; display:flex; flex-direction:column; gap:0.85rem;">
                <div style="background:#FFFDF5; border:1px solid #DFC387; border-radius:8px; padding:0.65rem 0.8rem; font-size:0.75rem;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem;">
                        <span style="color:#6B7280; font-weight:600;">Loket Kasir:</span>
                        <strong style="color:#1F170D;">PADEL FRONTDESK</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem;">
                        <span style="color:#6B7280; font-weight:600;">Petugas Bertugas:</span>
                        <strong style="color:#1F170D;">{{ auth()->user()?->name ?? 'Kasir' }}</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:#6B7280; font-weight:600;">Waktu Pembukaan:</span>
                        <strong style="color:#1F170D;">{{ now()->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB</strong>
                    </div>
                </div>

                <div>
                    <label style="display:block; font-size:0.75rem; font-weight:800; color:#1F170D; margin-bottom:0.3rem;">
                        Modal Awal Kas Tunai (Cash Float / Uang Kembalian) *
                    </label>
                    <div style="position:relative;">
                        <span style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); font-size:0.8125rem; font-weight:800; color:#8C6418;">Rp</span>
                        <input type="number" wire:model="startingCashInput" step="1000" min="0"
                            style="width:100%; border:1.5px solid #DFC387; border-radius:8px; padding:0.5rem 0.75rem 0.5rem 2.2rem; font-size:0.875rem; font-weight:800; color:#1F170D; background:#FFFDF5; outline:none;"
                            placeholder="Contoh: 500000">
                    </div>
                    <span style="font-size:0.65rem; color:#6B7280; margin-top:0.2rem; display:block;">
                        Nominal uang tunai fisik yang ditaruh di laci kasir saat awal operasional.
                    </span>
                </div>

                <div>
                    <label style="display:block; font-size:0.75rem; font-weight:800; color:#1F170D; margin-bottom:0.3rem;">
                        Catatan Pembukaan (Opsional)
                    </label>
                    <textarea wire:model="openingNotes" rows="2"
                        style="width:100%; border:1.5px solid #DFC387; border-radius:8px; padding:0.5rem 0.75rem; font-size:0.75rem; color:#1F170D; background:#FFFDF5; outline:none;"
                        placeholder="Catatan serah terima kas atau kondisi register..."></textarea>
                </div>
            </div>

            <div style="background:#FAF5E8; border-top:1px solid #DFC387; padding:0.75rem 1.1rem; display:flex; justify-content:flex-end; gap:0.5rem;">
                <button type="button" wire:click="$set('showOpenShiftModal', false)"
                    style="padding:0.45rem 0.9rem; font-size:0.8125rem; font-weight:700; border:1.5px solid #DFC387; background:#FFFFFF; border-radius:8px; cursor:pointer; color:#1F170D;">
                    Batal
                </button>
                <button type="button" wire:click="executeOpenShift"
                    style="padding:0.45rem 1.1rem; font-size:0.8125rem; font-weight:900; background:linear-gradient(180deg,#F0DB9D 0%,#D4AF37 35%,#B38622 100%); color:#281A05; border:1px solid #FBF0CE; border-radius:8px; cursor:pointer; box-shadow:0 2px 8px rgba(184,134,11,0.25);">
                    Konfirmasi &amp; Buka Shift
                </button>
            </div>
        </div>
    </div>
@endif

{{-- ============================
     MODAL TUTUP SHIFT KASIR (BLIND CASH COUNT)
     ============================ --}}
@if($showCloseShiftModal && $activeShift)
    <div style="position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.65); backdrop-filter:blur(6px); display:flex; align-items:center; justify-content:center; padding:1rem;">
        <div style="background:#FFFFFF; border:1.5px solid #DFC387; border-radius:18px; box-shadow:0 25px 50px -12px rgba(184,134,11,0.4); width:100%; max-width:480px; overflow:hidden; animation:fadeInUp 0.2s ease;">
            <div style="background:linear-gradient(135deg,#FAF5E8 0%,#F5E8C7 100%); border-bottom:1.5px solid #DFC387; padding:0.85rem 1.1rem; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-size:0.625rem; font-weight:900; color:#BE123C; text-transform:uppercase; letter-spacing:0.06em; background:#FFF1F2; border:1px solid #FECDD3; border-radius:4px; padding:0.08rem 0.4rem; display:inline-block;">CLOSING REGISTER</div>
                    <div style="font-size:1rem; font-weight:900; color:#1F170D; margin-top:0.2rem;">Tutup Shift &amp; Rekonsiliasi Kas</div>
                </div>
                <button type="button" wire:click="$set('showCloseShiftModal', false)" style="background:none; border:none; font-size:1.25rem; color:#78350F; cursor:pointer; line-height:1;">&times;</button>
            </div>

            <div style="padding:1.1rem; display:flex; flex-direction:column; gap:0.85rem;">
                <div style="background:#FFFDF5; border:1px solid #DFC387; border-radius:8px; padding:0.65rem 0.8rem; font-size:0.75rem;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem;">
                        <span style="color:#6B7280; font-weight:600;">No. Shift:</span>
                        <strong style="color:#1F170D;">{{ $activeShift->shift_number }}</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem;">
                        <span style="color:#6B7280; font-weight:600;">Dibuka Oleh:</span>
                        <strong style="color:#1F170D;">{{ $activeShift->openedBy?->name ?? 'Kasir' }} ({{ $activeShift->opened_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB)</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:0.25rem;">
                        <span style="color:#6B7280; font-weight:600;">Petugas Closing:</span>
                        <strong style="color:#1F170D;">{{ auth()->user()?->name ?? 'Kasir' }}</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:#6B7280; font-weight:600;">Modal Awal:</span>
                        <strong style="color:#8C6418;">Rp {{ number_format((float) $activeShift->starting_cash, 0, ',', '.') }}</strong>
                    </div>
                </div>

                <div style="background:#FEF3C7; border:1px solid #FCD34D; border-radius:8px; padding:0.6rem 0.75rem; font-size:0.6875rem; color:#92400E;">
                    <strong>Blind Cash Count:</strong> Masukkan jumlah total fisik uang tunai yang ada di dalam laci kasir saat ini. Sistem akan menghitung selisih secara otomatis setelah Anda mengonfirmasi penutupan.
                </div>

                <div>
                    <label style="display:block; font-size:0.75rem; font-weight:900; color:#1F170D; margin-bottom:0.3rem;">
                        Total Fisik Uang Tunai di Laci Kasir (Blind Count) *
                    </label>
                    <div style="position:relative;">
                        <span style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); font-size:0.875rem; font-weight:800; color:#8C6418;">Rp</span>
                        <input type="number" wire:model="actualCashInput" step="1000" min="0"
                            style="width:100%; border:2px solid #D4AF37; border-radius:8px; padding:0.55rem 0.75rem 0.55rem 2.4rem; font-size:0.9375rem; font-weight:900; color:#1F170D; background:#FFFFFF; outline:none;"
                            placeholder="Ketik total hitungan fisik uang di laci...">
                    </div>
                    <span style="font-size:0.65rem; color:#6B7280; margin-top:0.2rem; display:block;">
                        Wajib diisi dengan nominal uang fisik riil di laci kasir sebelum closing.
                    </span>
                </div>

                <div>
                    <label style="display:block; font-size:0.75rem; font-weight:800; color:#1F170D; margin-bottom:0.3rem;">
                        Catatan Penutupan Kasir (Opsional)
                    </label>
                    <textarea wire:model="closingNotes" rows="2"
                        style="width:100%; border:1.5px solid #DFC387; border-radius:8px; padding:0.5rem 0.75rem; font-size:0.75rem; color:#1F170D; background:#FFFDF5; outline:none;"
                        placeholder="Catatan serah terima kas, selisih uang, atau catatan operasional..."></textarea>
                </div>
            </div>

            <div style="background:#FAF5E8; border-top:1px solid #DFC387; padding:0.75rem 1.1rem; display:flex; justify-content:flex-end; gap:0.5rem;">
                <button type="button" wire:click="$set('showCloseShiftModal', false)"
                    style="padding:0.45rem 0.9rem; font-size:0.8125rem; font-weight:700; border:1.5px solid #DFC387; background:#FFFFFF; border-radius:8px; cursor:pointer; color:#1F170D;">
                    Batal
                </button>
                <button type="button" wire:click="executeCloseShift"
                    style="padding:0.45rem 1.1rem; font-size:0.8125rem; font-weight:900; background:#BE123C; color:#FFFFFF; border:none; border-radius:8px; cursor:pointer; box-shadow:0 2px 8px rgba(190,18,60,0.25);">
                    Tutup Shift &amp; Rekonsiliasi Kas
                </button>
            </div>
        </div>
    </div>
@endif

{{-- ============================
     MODAL LAPORAN SHIFT / STRUK Z-REPORT
     ============================ --}}
@if($showShiftReportModal && $reportShiftData)
    <div style="position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.65); backdrop-filter:blur(6px); display:flex; align-items:center; justify-content:center; padding:1rem;">
        <div style="background:#FFFFFF; border:1.5px solid #DFC387; border-radius:18px; box-shadow:0 25px 50px -12px rgba(184,134,11,0.4); width:100%; max-width:460px; max-height:90vh; display:flex; flex-direction:column; overflow:hidden; animation:fadeInUp 0.2s ease;">
            <div style="background:linear-gradient(135deg,#FAF5E8 0%,#F5E8C7 100%); border-bottom:1.5px solid #DFC387; padding:0.85rem 1.1rem; display:flex; justify-content:space-between; align-items:center; flex-shrink:0;">
                <div>
                    <div style="font-size:0.625rem; font-weight:900; color:#8C6418; text-transform:uppercase; letter-spacing:0.06em; background:#FAF5E8; border:1px solid #DFC387; border-radius:4px; padding:0.08rem 0.4rem; display:inline-block;">REKONSILIASI KAS</div>
                    <div style="font-size:1rem; font-weight:900; color:#1F170D; margin-top:0.2rem;">Laporan Penutupan Kasir (Z-Report)</div>
                </div>
                <button type="button" wire:click="closeShiftReportModal" style="background:none; border:none; font-size:1.25rem; color:#78350F; cursor:pointer; line-height:1;">&times;</button>
            </div>

            <div style="flex:1; overflow-y:auto; padding:1rem 1.1rem; background:#FFFFFF;">
                <div id="printable-z-report" style="font-family:monospace; font-size:0.75rem; color:#111827; background:#FFFFFF; padding:0.5rem 0;">
                    <div style="text-align:center; border-bottom:1px dashed #000; padding-bottom:0.6rem; margin-bottom:0.6rem;">
                        <div style="font-weight:900; font-size:0.9375rem;">CLUB 61 PADEL ARENA</div>
                        <div style="font-size:0.6rem;">Jl. Karang Tengah Raya No. 61, Lebak Bulus</div>
                        <div style="font-size:0.65rem; font-weight:800; margin-top:0.25rem;">LAPORAN PENUTUPAN KASIR (Z-REPORT)</div>
                        <div style="font-size:0.6rem;">Loket: {{ $reportShiftData['counter'] }}</div>
                    </div>

                    <div style="border-bottom:1px dashed #000; padding-bottom:0.45rem; margin-bottom:0.45rem;">
                        <div>No. Shift: <strong>{{ $reportShiftData['shift_number'] }}</strong></div>
                        <div>Buka : {{ $reportShiftData['opened_at'] }} ({{ $reportShiftData['opened_by'] }})</div>
                        <div>Tutup: {{ $reportShiftData['closed_at'] }} ({{ $reportShiftData['closed_by'] }})</div>
                        <div>Total Transaksi: {{ $reportShiftData['total_transactions'] }} transaksi</div>
                    </div>

                    <div style="border-bottom:1px dashed #000; padding-bottom:0.45rem; margin-bottom:0.45rem;">
                        <div style="font-weight:900; margin-bottom:0.25rem;">RINGKASAN PENJUALAN POS:</div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.15rem;">
                            <span>Penjualan Tunai (Cash):</span>
                            <span>Rp {{ number_format($reportShiftData['total_cash_sales'], 0, ',', '.') }}</span>
                        </div>
                        @if(($reportShiftData['total_debit_sales'] ?? 0) > 0)
                            <div style="display:flex; justify-content:space-between; margin-bottom:0.15rem;">
                                <span>Kartu Debit (EDC):</span>
                                <span>Rp {{ number_format($reportShiftData['total_debit_sales'], 0, ',', '.') }}</span>
                            </div>
                        @endif
                        @if(($reportShiftData['total_credit_sales'] ?? 0) > 0)
                            <div style="display:flex; justify-content:space-between; margin-bottom:0.15rem;">
                                <span>Kartu Kredit (EDC):</span>
                                <span>Rp {{ number_format($reportShiftData['total_credit_sales'], 0, ',', '.') }}</span>
                            </div>
                        @endif
                        @if(($reportShiftData['total_edc_bca_sales'] ?? 0) > 0)
                            <div style="display:flex; justify-content:space-between; margin-bottom:0.15rem;">
                                <span>EDC BCA:</span>
                                <span>Rp {{ number_format($reportShiftData['total_edc_bca_sales'], 0, ',', '.') }}</span>
                            </div>
                        @endif
                        @if(($reportShiftData['total_edc_mandiri_sales'] ?? 0) > 0)
                            <div style="display:flex; justify-content:space-between; margin-bottom:0.15rem;">
                                <span>EDC Mandiri:</span>
                                <span>Rp {{ number_format($reportShiftData['total_edc_mandiri_sales'], 0, ',', '.') }}</span>
                            </div>
                        @endif
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.15rem;">
                            <span>QRIS:</span>
                            <span>Rp {{ number_format($reportShiftData['total_qris_sales'], 0, ',', '.') }}</span>
                        </div>
                        @if($reportShiftData['total_other_sales'] > 0)
                            <div style="display:flex; justify-content:space-between; margin-bottom:0.15rem;">
                                <span>Lainnya:</span>
                                <span>Rp {{ number_format($reportShiftData['total_other_sales'], 0, ',', '.') }}</span>
                            </div>
                        @endif
                        <div style="display:flex; justify-content:space-between; font-weight:900; border-top:1px dashed #000; padding-top:0.25rem; margin-top:0.25rem;">
                            <span>TOTAL OMSET POS:</span>
                            <span>Rp {{ number_format($reportShiftData['total_sales'], 0, ',', '.') }}</span>
                        </div>
                    </div>

                    <div style="border-bottom:1px dashed #000; padding-bottom:0.45rem; margin-bottom:0.45rem;">
                        <div style="font-weight:900; margin-bottom:0.25rem;">REKONSILIASI KAS DI LACI:</div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.15rem;">
                            <span>Modal Awal (Float):</span>
                            <span>Rp {{ number_format($reportShiftData['starting_cash'], 0, ',', '.') }}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; margin-bottom:0.15rem;">
                            <span>(+) Penjualan Tunai:</span>
                            <span>Rp {{ number_format($reportShiftData['total_cash_sales'], 0, ',', '.') }}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-weight:800; border-top:1px dashed #ccc; padding-top:0.15rem; margin-top:0.15rem;">
                            <span>(=) Ekspektasi Kas Fisik:</span>
                            <span>Rp {{ number_format($reportShiftData['expected_cash'], 0, ',', '.') }}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-weight:800; margin-top:0.15rem;">
                            <span>(x) Fisik Kas Dihitung:</span>
                            <span>Rp {{ number_format($reportShiftData['actual_cash'], 0, ',', '.') }}</span>
                        </div>

                        @php
                            $diff = (float) $reportShiftData['cash_difference'];
                        @endphp
                        <div style="display:flex; justify-content:space-between; font-weight:900; font-size:0.8125rem; border-top:1.5px solid #000; padding-top:0.3rem; margin-top:0.3rem;">
                            <span>STATUS SELISIH:</span>
                            @if(abs($diff) < 0.01)
                                <span style="color:#059669;">PAS (BALANCE)</span>
                            @elseif($diff < 0)
                                <span style="color:#DC2626;">KURANG: Rp {{ number_format(abs($diff), 0, ',', '.') }}</span>
                            @else
                                <span style="color:#2563EB;">LEBIH: Rp {{ number_format($diff, 0, ',', '.') }}</span>
                            @endif
                        </div>
                    </div>

                    @if(!empty($reportShiftData['closing_notes']))
                        <div style="border-bottom:1px dashed #000; padding-bottom:0.45rem; margin-bottom:0.45rem; font-size:0.6875rem;">
                            <strong>Catatan:</strong> {{ $reportShiftData['closing_notes'] }}
                        </div>
                    @endif

                    <div style="display:flex; justify-content:space-between; text-align:center; padding-top:1.2rem; font-size:0.625rem;">
                        <div style="width:45%;">
                            <div>Kasir Bertugas,</div>
                            <div style="height:2.2rem;"></div>
                            <div style="border-top:1px solid #000; padding-top:0.15rem;">( {{ $reportShiftData['closed_by'] }} )</div>
                        </div>
                        <div style="width:45%;">
                            <div>Supervisor / Manager,</div>
                            <div style="height:2.2rem;"></div>
                            <div style="border-top:1px solid #000; padding-top:0.15rem;">( .................... )</div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="background:#FAF5E8; border-top:1px solid #DFC387; padding:0.65rem 1.1rem; display:flex; justify-content:flex-end; gap:0.5rem; flex-shrink:0;">
                <button type="button" onclick="window.print()"
                    style="padding:0.4rem 0.85rem; font-size:0.8125rem; font-weight:800; border:1.5px solid #DFC387; background:#FFFFFF; border-radius:7px; cursor:pointer; color:#1F170D;">
                    Cetak Z-Report
                </button>
                <button type="button" wire:click="closeShiftReportModal"
                    style="padding:0.4rem 0.85rem; font-size:0.8125rem; font-weight:800; background:linear-gradient(180deg,#F0DB9D 0%,#D4AF37 35%,#B38622 100%); color:#281A05; border:1px solid #FBF0CE; border-radius:7px; cursor:pointer;">
                    Selesai
                </button>
            </div>
        </div>
    </div>
@endif

<style>
    @media print {
        body * { visibility: hidden; }
        #printable-pos-receipt, #printable-pos-receipt *, #printable-z-report, #printable-z-report * { visibility: visible; }
        #printable-pos-receipt, #printable-z-report { position:absolute; left:0; top:0; width:78mm; margin:0; padding:5mm; border:none !important; }
    }
</style>
<script>
    window.addEventListener('beforeunload', function (e) {
        if (@this.get('posStep') === 'payment') {
            e.preventDefault();
            e.returnValue = 'Transaksi pembayaran kasir sedang berlangsung. Yakin ingin meninggalkan halaman?';
            return e.returnValue;
        }
    });
</script>

</div>{{-- end walkin-pos-root --}}
