<div class="adm-wrap">
    <style>
        .adm-table-dimmed {
            opacity: 0.5;
            pointer-events: none;
            transition: opacity 0.12s ease;
        }

        @keyframes spin {
            from {
                transform: rotate(0deg);
            }

            to {
                transform: rotate(360deg);
            }
        }

        .adm-btn-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            min-width: 32px;
            border-radius: 8px;
            border: 1px solid #D4AF37;
            background: #FFFFFF;
            color: #1F170D;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.15s ease;
            padding: 0;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            flex-shrink: 0;
        }

        .adm-btn-icon:hover {
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(212, 175, 55, 0.25);
            background: #FFFDF5;
        }

        .adm-btn-icon:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .adm-btn-icon-danger {
            color: #DC2626;
            border-color: #FECACA;
            background: #FEF2F2;
        }

        .adm-btn-icon-danger:hover {
            background: #FEE2E2;
            border-color: #F87171;
            box-shadow: 0 3px 8px rgba(220, 38, 38, 0.25);
        }

        .adm-btn-icon-settle {
            background: #DC2626;
            color: #FFFFFF;
            border-color: #B91C1C;
        }

        .adm-btn-icon-settle:hover {
            background: #B91C1C;
            box-shadow: 0 3px 8px rgba(185, 28, 28, 0.35);
        }

        .adm-table-wrap-static {
            overflow-x: hidden !important;
            border-radius: 0;
            border: none;
            background: #FFFFFF;
            width: 100%;
            box-sizing: border-box;
            margin-top: 0;
        }

        .adm-table-static {
            width: 100% !important;
            table-layout: fixed !important;
            border-collapse: collapse;
        }

        .adm-table-static th,
        .adm-table-static td {
            box-sizing: border-box;
            padding: 0.75rem 0.65rem;
            vertical-align: middle;
        }

        .adm-table-static th:first-child,
        .adm-table-static td:first-child {
            padding-left: 1.25rem !important;
        }

        .adm-table-static th:last-child,
        .adm-table-static td:last-child {
            padding-right: 1.25rem !important;
            padding-left: 0.5rem !important;
            text-align: center !important;
            white-space: nowrap !important;
        }

        .adm-truncate-cell {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
    </style>

    <!-- Header Banner -->
    <div class="adm-banner">
        <div>
            <div class="adm-pill adm-pill-gold">
                <span
                    style="display:inline-block; width:6px; height:6px; border-radius:50%; background-color:#D4AF37;"></span>
                <span>Modul Kelola Pemesanan &bull; Order Management System</span>
            </div>
            <div class="adm-banner-title">
                Kelola Pemesanan &amp; Booking
            </div>
            <div class="adm-banner-sub">
                Daftar transaksi reservasi customer, verifikasi QR Code check-in, penyesuaian jadwal (reschedule), dan
                proses refund kasir.
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem;">
            <button type="button" wire:click="openCheckInModal()" class="adm-btn-sec"
                style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05; border: 1px solid #FBF0CE; font-weight: 800; cursor: pointer; box-shadow: 0 4px 12px rgba(184, 134, 11, 0.25);">
                <span>Scan QR / Check-In Gate</span>
            </button>
            <a href="/admin/booking-system" class="adm-btn-sec">
                <span>Lihat Matriks Lapangan</span>
            </a>
        </div>
    </div>

    <!-- Filter Tabs -->
    <div class="adm-tabs">
        <button type="button" wire:click="setTab('ALL')" wire:loading.attr="disabled"
            class="adm-tab-btn {{ $activeTab === 'ALL' ? 'active' : '' }}">
            Semua Reservasi ({{ $counts['ALL'] }})
        </button>
        <button type="button" wire:click="setTab('CONFIRMED')" wire:loading.attr="disabled"
            class="adm-tab-btn {{ $activeTab === 'CONFIRMED' ? 'active' : '' }}">
            Konfirmasi / Lunas ({{ $counts['CONFIRMED'] }})
        </button>
        <button type="button" wire:click="setTab('COMPLETED')" wire:loading.attr="disabled"
            class="adm-tab-btn {{ $activeTab === 'COMPLETED' ? 'active' : '' }}">
            Selesai ({{ $counts['COMPLETED'] }})
        </button>
        <button type="button" wire:click="setTab('CANCELLED')" wire:loading.attr="disabled"
            class="adm-tab-btn {{ $activeTab === 'CANCELLED' ? 'active' : '' }}">
            Dibatalkan / Refund ({{ $counts['CANCELLED'] }})
        </button>
    </div>

    <!-- Bookings Table Card -->
    <div class="adm-card" style="padding: 0; overflow: hidden; border-radius: 16px;">
        <!-- Card Header with Search & Per-Page Controls -->
        <div
            style="display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid #F0DB9D; background: #FFFDF9; flex-wrap: wrap; gap: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="font-weight: 800; font-size: 0.9375rem; color: #1F170D;">
                    Daftar Transaksi Reservasi
                </div>
                <span
                    style="font-size: 0.7rem; color: #8C6418; background: #FAF5E8; border: 1px solid #DFC387; padding: 0.15rem 0.6rem; border-radius: 9999px; font-weight: 700;">
                    Total: {{ $bookings->total() }} Data
                </span>
            </div>

            <div style="display: flex; align-items: center; gap: 0.75rem; flex-wrap: wrap;">
                <!-- Search Box with Clear Button -->
                <div style="position: relative; width: 320px;">
                    <input type="text" wire:model.live.debounce.300ms="search"
                        placeholder="Cari nama cust, no tiket, email, lapangan..." class="adm-search-input"
                        style="padding-left: 0.85rem; padding-right: 2rem; width: 100%; max-width: 100%;" />
                    @if ($search)
                        <button type="button" wire:click="$set('search', '')" title="Hapus filter pencarian"
                            style="position: absolute; right: 0.65rem; top: 50%; transform: translateY(-50%); background: none; border: none; font-size: 0.75rem; color: #9CA3AF; cursor: pointer; padding: 2px 4px; font-weight: bold;">
                            X
                        </button>
                    @endif
                </div>

                <!-- Per Page Selector -->
                <div
                    style="display: flex; align-items: center; gap: 0.35rem; font-size: 0.75rem; color: #785A1D; font-weight: 700;">
                    <span>Tampil:</span>
                    <select wire:model.live="perPage"
                        style="background: #FAF5E8; border: 1.5px solid #DFC387; border-radius: 8px; padding: 0.35rem 0.5rem; font-size: 0.75rem; font-weight: 700; color: #1F170D; cursor: pointer; outline: none;">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                    </select>
                    <span>baris</span>
                </div>
            </div>
        </div>

        <!-- Static Table (No Horizontal Scroll) -->
        <div class="adm-table-wrap-static" wire:loading.class="adm-table-dimmed"
            wire:target="setTab, gotoPage, nextPage, previousPage, search, perPage">
            <table class="adm-table adm-table-static">
                <thead>
                    <tr>
                        <th style="width: {{ $activeTab === 'CANCELLED' ? '12%' : '13%' }};">No Tiket</th>
                        <th style="width: {{ $activeTab === 'CANCELLED' ? '14%' : '17%' }};">Member / Customer</th>
                        <th style="width: {{ $activeTab === 'CANCELLED' ? '14%' : '16%' }};">Lapangan &amp; Durasi</th>
                        <th style="width: {{ $activeTab === 'CANCELLED' ? '13%' : '14%' }};">Jadwal Main</th>
                        <th style="width: {{ $activeTab === 'CANCELLED' ? '11%' : '14%' }};">Status</th>
                        @if ($activeTab === 'CANCELLED')
                            <th style="width: 14%;">Note / Keterangan</th>
                        @endif
                        <th style="width: {{ $activeTab === 'CANCELLED' ? '10%' : '11%' }};">Total Bayar</th>
                        <th style="width: {{ $activeTab === 'CANCELLED' ? '12%' : '15%' }}; text-align: center;">Aksi
                            Kasir</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bookings as $b)
                        @php
                            $duration = (int) $b->start_time->diffInHours($b->end_time);
                            if ($duration < 1) {
                                $duration = 1;
                            }

                            $pendingPayment = $b->order?->payments?->firstWhere('status', 'PENDING');
                            $pendingAmount = $pendingPayment
                                ? (float) $pendingPayment->amount
                                : ($b->status === 'PENDING_PAYMENT'
                                    ? (float) ($b->order?->grand_total ?: $b->total_amount)
                                    : 0);
                        @endphp
                        <tr>
                            <td style="font-family: var(--font-mono, monospace); font-weight: 700; color: #8C6418;">
                                <div class="adm-truncate-cell" title="{{ $b->booking_code }}">{{ $b->booking_code }}
                                </div>
                                @if ($b->reschedule_count > 0)
                                    <div style="font-size: 0.625rem; color: #B45309; font-weight: 600;">Reschedule
                                        ({{ $b->reschedule_count }}x)</div>
                                @endif
                            </td>
                            <td>
                                <div class="adm-truncate-cell" style="font-weight: 800; color: #1F170D;"
                                    title="{{ $b->user?->name ?? 'Guest User' }}">
                                    {{ $b->user?->name ?? 'Guest User' }}</div>
                                <div class="adm-truncate-cell" style="font-size: 0.625rem; color: #8C7A58;"
                                    title="{{ $b->user?->email ?? '-' }}">{{ $b->user?->email ?? '-' }}</div>
                            </td>
                            <td>
                                <div class="adm-truncate-cell" style="font-weight: 700; color: #1F170D;"
                                    title="{{ $b->court?->name ?? '-' }}">{{ $b->court?->name ?? '-' }}</div>
                                <div style="font-size: 0.625rem; color: #78350F; font-weight: 600;">Sesi:
                                    {{ $duration }} Jam</div>
                            </td>
                            <td>
                                <div style="font-weight: 600; color: #1F170D;">{{ $b->booking_date->format('d M Y') }}
                                </div>
                                <div style="font-size: 0.625rem; color: #8C7A58;">{{ $b->start_time->format('H:i') }} -
                                    {{ $b->end_time->format('H:i') }} WIB</div>
                            </td>
                            <td>
                                @if ($b->status === 'PAID')
                                    <span class="adm-pill adm-pill-green">Confirmed / Lunas</span>
                                @elseif($b->status === 'PENDING_PAYMENT')
                                    <span class="adm-pill"
                                        style="background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; font-weight: 700;">Pending
                                        Payment</span>
                                @elseif($b->status === 'LOCKED')
                                    <span class="adm-pill adm-pill-gold">Locked / Waiting</span>
                                @elseif($b->status === 'CHECKED_IN')
                                    <span class="adm-pill adm-pill-gold">Checked In</span>
                                @elseif($b->status === 'COMPLETED')
                                    <span class="adm-pill"
                                        style="background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; font-weight: 700;">Completed</span>
                                @elseif($b->status === 'REFUNDED')
                                    <span class="adm-pill"
                                        style="background: #F3F4F6; color: #374151; border: 1px solid #D1D5DB; font-weight: 700;">Refunded</span>
                                @elseif($b->status === 'CANCELLED')
                                    <span class="adm-pill"
                                        style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; font-weight: 700;">Cancelled</span>
                                @elseif($b->status === 'EXPIRED')
                                    <span class="adm-pill"
                                        style="background: #FEF2F2; color: #991B1B; border: 1px solid #FCA5A5; font-weight: 700;">Expired</span>
                                @else
                                    <span class="adm-pill">{{ $b->status }}</span>
                                @endif
                            </td>
                            @if ($activeTab === 'CANCELLED')
                                <td style="font-size: 0.75rem; color: #374151;">
                                    @if ($b->cancel_reason)
                                        <div style="background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; border-radius: 6px; padding: 0.35rem 0.55rem; font-size: 0.71875rem; font-weight: 600; line-height: 1.35; word-break: break-word;"
                                            title="{{ $b->cancel_reason }}">
                                            {{ $b->cancel_reason }}
                                        </div>
                                    @elseif($b->status === 'EXPIRED')
                                        @php
                                            $isPaidNoShow =
                                                ($b->order && $b->order->payment_status === 'PAID') ||
                                                ($b->order &&
                                                    $b->order->payments &&
                                                    $b->order->payments->where('status', 'SUCCESS')->isNotEmpty());
                                        @endphp
                                        @if ($isPaidNoShow)
                                            <div
                                                style="background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; border-radius: 6px; padding: 0.35rem 0.55rem; font-size: 0.71875rem; font-weight: 600; line-height: 1.35;">
                                                Lewat Jadwal Main (No-Show / Lunas)
                                            </div>
                                        @else
                                            <div
                                                style="background: #FFFBEB; color: #92400E; border: 1px solid #FDE68A; border-radius: 6px; padding: 0.35rem 0.55rem; font-size: 0.71875rem; font-weight: 600; line-height: 1.35;">
                                                Kedaluwarsa Pembayaran (Belum Bayar > 15 Menit)
                                            </div>
                                        @endif
                                    @else
                                        <span style="color: #9CA3AF; font-size: 0.75rem; font-style: italic;">Tidak ada
                                            catatan</span>
                                    @endif
                                </td>
                            @endif
                            <td style="font-family: var(--font-mono, monospace); font-weight: 800; color: #1F170D;">
                                Rp {{ number_format($b->total_amount, 0, ',', '.') }}
                            </td>
                            <td style="text-align: center; white-space: nowrap;">
                                <div
                                    style="display: inline-flex; gap: 0.4rem; align-items: center; justify-content: center;">
                                    @if (($b->status === 'LOCKED' && $pendingAmount > 0) || $b->status === 'PENDING_PAYMENT')
                                        <button type="button" wire:click="openSettleModal('{{ $b->id }}')"
                                            wire:loading.attr="disabled"
                                            title="Pelunasan Kasir / Settle Tunai (Rp {{ number_format($pendingAmount ?: $b->total_amount, 0, ',', '.') }})"
                                            class="adm-btn-icon adm-btn-icon-settle">
                                            <span wire:loading.remove
                                                wire:target="openSettleModal('{{ $b->id }}')">
                                                <svg style="width: 15px; height: 15px;" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                                                </svg>
                                            </span>
                                            <span wire:loading wire:target="openSettleModal('{{ $b->id }}')">
                                                <svg style="width: 13px; height: 13px; animation: spin 1s linear infinite;"
                                                    fill="none" viewBox="0 0 24 24">
                                                    <circle cx="12" cy="12" r="10"
                                                        stroke="currentColor" stroke-width="4"
                                                        style="opacity: 0.25;"></circle>
                                                    <path fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                                        style="opacity: 0.75;"></path>
                                                </svg>
                                            </span>
                                        </button>
                                    @endif

                                    @if ($b->status === 'PAID')
                                        <button type="button"
                                            wire:click="openCheckInModal('{{ $b->booking_code }}')"
                                            wire:loading.attr="disabled" title="Check-In Customer (Scan QR)"
                                            class="adm-btn-icon"
                                            style="background: #ECFDF5; border-color: #A7F3D0; color: #065F46;">
                                            <span wire:loading.remove
                                                wire:target="openCheckInModal('{{ $b->booking_code }}')">
                                                <svg style="width: 15px; height: 15px;" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z" />
                                                </svg>
                                            </span>
                                            <span wire:loading
                                                wire:target="openCheckInModal('{{ $b->booking_code }}')">
                                                <svg style="width: 13px; height: 13px; animation: spin 1s linear infinite;"
                                                    fill="none" viewBox="0 0 24 24">
                                                    <circle cx="12" cy="12" r="10"
                                                        stroke="currentColor" stroke-width="4"
                                                        style="opacity: 0.25;"></circle>
                                                    <path fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                                        style="opacity: 0.75;"></path>
                                                </svg>
                                            </span>
                                        </button>
                                    @endif

                                    @if ($b->status === 'CHECKED_IN')
                                        <button type="button" wire:click="executeComplete('{{ $b->id }}')"
                                            wire:confirm="Tandai sesi bermain tiket {{ $b->booking_code }} telah selesai (COMPLETED)?"
                                            wire:loading.attr="disabled" title="Tandai Selesai (Complete)"
                                            class="adm-btn-icon"
                                            style="background: #FEF3C7; border-color: #FDE68A; color: #92400E;">
                                            <span wire:loading.remove
                                                wire:target="executeComplete('{{ $b->id }}')">
                                                <svg style="width: 15px; height: 15px;" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                            </span>
                                            <span wire:loading wire:target="executeComplete('{{ $b->id }}')">
                                                <svg style="width: 13px; height: 13px; animation: spin 1s linear infinite;"
                                                    fill="none" viewBox="0 0 24 24">
                                                    <circle cx="12" cy="12" r="10"
                                                        stroke="currentColor" stroke-width="4"
                                                        style="opacity: 0.25;"></circle>
                                                    <path fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                                        style="opacity: 0.75;"></path>
                                                </svg>
                                            </span>
                                        </button>
                                    @endif

                                    @if (in_array($b->status, ['PAID', 'LOCKED']))
                                        <button type="button"
                                            wire:click="openRescheduleModal('{{ $b->id }}')"
                                            wire:loading.attr="disabled" title="Pindah Jadwal (Reschedule)"
                                            class="adm-btn-icon" style="color: #8C6418;">
                                            <span wire:loading.remove
                                                wire:target="openRescheduleModal('{{ $b->id }}')">
                                                <svg style="width: 15px; height: 15px;" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                </svg>
                                            </span>
                                            <span wire:loading
                                                wire:target="openRescheduleModal('{{ $b->id }}')">
                                                <svg style="width: 13px; height: 13px; animation: spin 1s linear infinite;"
                                                    fill="none" viewBox="0 0 24 24">
                                                    <circle cx="12" cy="12" r="10"
                                                        stroke="currentColor" stroke-width="4"
                                                        style="opacity: 0.25;"></circle>
                                                    <path fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                                        style="opacity: 0.75;"></path>
                                                </svg>
                                            </span>
                                        </button>
                                    @endif

                                    @if (in_array($b->status, ['PAID', 'LOCKED', 'REFUND_PENDING']) &&
                                            (auth()->user()->can('cancel_refund_padel') ||
                                                auth()->user()->can('cancel_padel_booking') ||
                                                auth()->user()->isAdmin()))
                                        <button type="button"
                                            wire:click="openCancelRefundModal('{{ $b->id }}')"
                                            wire:loading.attr="disabled" title="Batalkan Reservasi &amp; Refund"
                                            class="adm-btn-icon adm-btn-icon-danger">
                                            <span wire:loading.remove
                                                wire:target="openCancelRefundModal('{{ $b->id }}')">
                                                <svg style="width: 15px; height: 15px;" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                </svg>
                                            </span>
                                            <span wire:loading
                                                wire:target="openCancelRefundModal('{{ $b->id }}')">
                                                <svg style="width: 13px; height: 13px; animation: spin 1s linear infinite;"
                                                    fill="none" viewBox="0 0 24 24">
                                                    <circle cx="12" cy="12" r="10"
                                                        stroke="currentColor" stroke-width="4"
                                                        style="opacity: 0.25;"></circle>
                                                    <path fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                                                        style="opacity: 0.75;"></path>
                                                </svg>
                                            </span>
                                        </button>
                                    @endif

                                    @if (in_array($b->status, ['REFUNDED', 'CANCELLED', 'EXPIRED', 'COMPLETED']))
                                        <span
                                            title="{{ $b->status === 'COMPLETED' ? 'Sesi Telah Selesai' : 'Tiket Telah Dinonaktifkan' }}"
                                            style="display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 8px; background: #F3F4F6; border: 1px solid #D1D5DB; color: #6B7280; cursor: help;">
                                            @if ($b->status === 'COMPLETED')
                                                <svg style="width: 15px; height: 15px; color: #059669;" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2.5" d="M5 13l4 4L19 7" />
                                                </svg>
                                            @else
                                                <svg style="width: 15px; height: 15px;" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                </svg>
                                            @endif
                                        </span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $activeTab === 'CANCELLED' ? 8 : 7 }}"
                                style="text-align: center; padding: 2.5rem 1rem; color: #8C7A58;">
                                @if ($search)
                                    <div style="font-weight: 700; color: #1F170D; margin-bottom: 0.25rem;">Tidak ada
                                        reservasi yang cocok</div>
                                    <div style="font-size: 0.75rem; color: #8C7A58; margin-bottom: 0.75rem;">
                                        Tidak ditemukan hasil untuk kata kunci "<strong>{{ $search }}</strong>".
                                    </div>
                                    <button type="button" wire:click="$set('search', '')" class="adm-btn-sec"
                                        style="font-size: 0.75rem; padding: 0.35rem 0.85rem;">
                                        Hapus Filter Pencarian
                                    </button>
                                @else
                                    <div style="font-weight: 600;">Belum ada reservasi pada kategori ini.</div>
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <!-- Custom Luxury Gold Pagination Bar -->
        <div
            style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem 1.25rem; border-top: 1px solid #F0DB9D; background: #FCFAF5; flex-wrap: wrap; gap: 0.75rem;">
            <div style="font-size: 0.75rem; color: #785A1D; font-weight: 600;">
                Menampilkan <strong style="color: #1F170D;">{{ $bookings->firstItem() ?? 0 }}</strong> - <strong
                    style="color: #1F170D;">{{ $bookings->lastItem() ?? 0 }}</strong> dari <strong
                    style="color: #1F170D;">{{ $bookings->total() }}</strong> reservasi
                @if ($search)
                    <span style="color: #B45309;">(difilter)</span>
                @endif
            </div>

            @if ($bookings->hasPages())
                <div style="display: flex; align-items: center; gap: 0.35rem;">
                    {{-- Previous Page Button --}}
                    <button type="button" wire:click="previousPage" wire:loading.attr="disabled"
                        @if ($bookings->onFirstPage()) disabled @endif class="adm-btn-sec"
                        style="padding: 0.25rem 0.65rem; font-size: 0.75rem; {{ $bookings->onFirstPage() ? 'opacity: 0.35; cursor: not-allowed;' : '' }}">
                        &larr; Prev
                    </button>

                    {{-- Page Numbers --}}
                    @foreach ($bookings->getUrlRange(1, $bookings->lastPage()) as $page => $url)
                        <button type="button" wire:click="gotoPage({{ $page }})"
                            wire:loading.attr="disabled"
                            style="min-width: 28px; height: 28px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; border: 1px solid {{ $page == $bookings->currentPage() ? '#D4AF37' : '#E5E7EB' }}; background: {{ $page == $bookings->currentPage() ? 'linear-gradient(180deg, #F0DB9D 0%, #D4AF37 100%)' : '#FFFFFF' }}; color: {{ $page == $bookings->currentPage() ? '#1F170D' : '#4B5563' }}; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
                            {{ $page }}
                        </button>
                    @endforeach

                    {{-- Next Page Button --}}
                    <button type="button" wire:click="nextPage" wire:loading.attr="disabled"
                        @if (!$bookings->hasMorePages()) disabled @endif class="adm-btn-sec"
                        style="padding: 0.25rem 0.65rem; font-size: 0.75rem; {{ !$bookings->hasMorePages() ? 'opacity: 0.35; cursor: not-allowed;' : '' }}">
                        Next &rarr;
                    </button>
                </div>
            @endif
        </div>
    </div>

    <!-- MODAL 1: PINDAH JADWAL (ADMIN OVERRIDE) -->
    @if ($showRescheduleModal && $selectedBookingData)
        <div
            style="position: fixed; inset: 0; z-index: 99999; background: rgba(15, 10, 5, 0.7); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 1rem;">
            <div
                style="background: #FFFFFF; border-radius: 16px; width: 100%; max-width: 600px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); border: 1px solid #D4AF37; overflow: hidden; max-height: 90vh; display: flex; flex-direction: column;">
                <!-- Header Modal -->
                <div
                    style="background: linear-gradient(135deg, #2B1D0E 0%, #170E04 100%); padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #D4AF37;">
                    <div>
                        <div
                            style="color: #D4AF37; font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                            Admin Override &bull; Concierge Backdoor</div>
                        <div style="color: #FFFFFF; font-size: 1.125rem; font-weight: 800; margin-top: 0.25rem;">Pindah
                            Jadwal Reservasi</div>
                    </div>
                    <button type="button" wire:click="$set('showRescheduleModal', false)"
                        style="background: none; border: none; color: #D4AF37; font-size: 1.5rem; cursor: pointer; line-height: 1;">&times;</button>
                </div>

                <!-- Body Modal -->
                <div style="padding: 1.5rem; overflow-y: auto; flex: 1;">
                    <!-- Context Card -->
                    <div
                        style="background: #FAF5E8; border: 1px solid #F0DB9D; border-radius: 12px; padding: 1rem; margin-bottom: 1.25rem;">
                        <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                            <span style="font-size: 0.75rem; color: #8C6418; font-weight: 600;">Customer &amp;
                                Tiket</span>
                            <span
                                style="font-family: var(--font-mono, monospace); font-weight: 800; color: #8C6418;">#{{ $selectedBookingData['booking_code'] }}</span>
                        </div>
                        <div style="font-size: 0.9375rem; font-weight: 800; color: #1F170D;">
                            {{ $selectedBookingData['customer_name'] }}</div>
                        <div style="font-size: 0.75rem; color: #7A643E; margin-top: 0.25rem;">
                            Jadwal Asli: {{ $selectedBookingData['court_name'] }} &bull;
                            {{ $selectedBookingData['original_date'] }}, {{ $selectedBookingData['original_time'] }}
                        </div>
                        <div style="display: flex; gap: 0.5rem; margin-top: 0.5rem; font-size: 0.6875rem;">
                            <span
                                style="background: #EAF7EC; color: #1E7E34; padding: 0.2rem 0.5rem; border-radius: 6px; font-weight: 700;">
                                Durasi Terkunci: {{ $rescheduleDurationHours }} Jam
                            </span>
                            <span
                                style="background: #F3F4F6; color: #4B5563; padding: 0.2rem 0.5rem; border-radius: 6px; font-weight: 600;">
                                Sewa Raket: Terikut Otomatis (Order ID)
                            </span>
                        </div>
                    </div>

                    <!-- Input Lapangan Baru -->
                    <div style="margin-bottom: 1rem;">
                        <label
                            style="display: block; font-size: 0.75rem; font-weight: 700; color: #1F170D; margin-bottom: 0.35rem;">Pilih
                            Lapangan Tujuan</label>
                        <select wire:model.live="rescheduleCourtId" wire:loading.attr="disabled"
                            style="width: 100%; border: 1px solid #D4AF37; border-radius: 8px; padding: 0.5rem; font-size: 0.8125rem;">
                            @foreach ($courts as $court)
                                <option value="{{ $court->id }}">{{ $court->name }} ({{ $court->type }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Input Tanggal Baru (Anti Tanggal Lampau) -->
                    <div style="margin-bottom: 1rem;">
                        <label
                            style="display: block; font-size: 0.75rem; font-weight: 700; color: #1F170D; margin-bottom: 0.35rem;">
                            Tanggal Baru <span style="color: #DC2626; font-size: 0.6875rem;">(Hanya Hari Ini atau Masa
                                Depan)</span>
                        </label>
                        <input type="date" min="{{ now()->format('Y-m-d') }}"
                            wire:model.live.debounce.250ms="rescheduleDate" wire:loading.attr="disabled"
                            style="width: 100%; border: 1px solid #D4AF37; border-radius: 8px; padding: 0.5rem; font-size: 0.8125rem;">
                    </div>

                    <!-- Input Jam Mulai Baru (Slot Durasi Penuh) -->
                    <div style="margin-bottom: 1rem;">
                        <div
                            style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem;">
                            <label style="font-size: 0.75rem; font-weight: 700; color: #1F170D;">
                                Pilih Jam Main Baru (Blok Kontigu {{ $rescheduleDurationHours }} Jam)
                            </label>
                            <span wire:loading wire:target="rescheduleDate, rescheduleCourtId"
                                style="font-size: 0.6875rem; color: #8C6418; font-weight: 700;">
                                Memeriksa ketersediaan...
                            </span>
                        </div>
                        @if (empty($availableSlots))
                            <div
                                style="padding: 0.75rem; background: #FEE2E2; border: 1px solid #F87171; border-radius: 8px; color: #991B1B; font-size: 0.75rem;">
                                Tidak ada jadwal kosong yang memiliki {{ $rescheduleDurationHours }} jam berturut-turut
                                pada lapangan dan tanggal ini. Silakan pilih tanggal atau lapangan lain.
                            </div>
                        @else
                            <select wire:model.live="rescheduleStartTime" wire:loading.attr="disabled"
                                wire:target="rescheduleDate, rescheduleCourtId"
                                style="width: 100%; border: 1px solid #D4AF37; border-radius: 8px; padding: 0.5rem; font-size: 0.8125rem;">
                                @foreach ($availableSlots as $slot)
                                    <option value="{{ $slot['start_time'] }}">{{ $slot['label'] }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    <!-- Perhitungan Selisih Tarif (Price Delta) -->
                    @if (!empty($availableSlots) && $rescheduleStartTime)
                        <div
                            style="border-radius: 12px; padding: 1rem; margin-bottom: 1rem; border: 1px solid {{ $rescheduleDelta > 0 ? '#F87171' : ($rescheduleDelta < 0 ? '#86EFAC' : '#E5E7EB') }}; background: {{ $rescheduleDelta > 0 ? '#FEF2F2' : ($rescheduleDelta < 0 ? '#F0FDF4' : '#F9FAFB') }};">
                            <div
                                style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #4B5563; margin-bottom: 0.25rem;">
                                <span>Tarif Sesi Sebelumnya:</span>
                                <span style="font-weight: 700;">Rp
                                    {{ number_format($selectedBookingData['original_court_fee'], 0, ',', '.') }}</span>
                            </div>
                            <div
                                style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #4B5563; margin-bottom: 0.5rem;">
                                <span>Tarif Sesi Jadwal Baru:</span>
                                <span style="font-weight: 700;">Rp
                                    {{ number_format($rescheduleEstimatedFee, 0, ',', '.') }}</span>
                            </div>

                            <div
                                style="border-top: 1px dashed {{ $rescheduleDelta > 0 ? '#F87171' : ($rescheduleDelta < 0 ? '#86EFAC' : '#D1D5DB') }}; padding-top: 0.5rem; display: flex; justify-content: space-between; align-items: center;">
                                <span
                                    style="font-size: 0.8125rem; font-weight: 800; color: {{ $rescheduleDelta > 0 ? '#991B1B' : ($rescheduleDelta < 0 ? '#166534' : '#374151') }};">
                                    @if ($rescheduleDelta > 0)
                                        Selisih Kurang Bayar (Wajib Ditagih):
                                    @elseif($rescheduleDelta < 0)
                                        Selisih Lebih Bayar (Saldo Member):
                                    @else
                                        Tidak Ada Selisih Tarif (Sama):
                                    @endif
                                </span>
                                <span
                                    style="font-family: var(--font-mono, monospace); font-size: 1rem; font-weight: 900; color: {{ $rescheduleDelta > 0 ? '#991B1B' : ($rescheduleDelta < 0 ? '#166534' : '#111827') }};">
                                    Rp {{ number_format(abs($rescheduleDelta), 0, ',', '.') }}
                                </span>
                            </div>

                            @if ($rescheduleDelta > 0)
                                <div style="margin-top: 0.75rem; padding-top: 0.75rem; border-top: 1px solid #FECACA;">
                                    <label
                                        style="display: block; font-size: 0.6875rem; font-weight: 700; color: #991B1B; margin-bottom: 0.25rem;">Opsi
                                        Pelunasan Kasir:</label>
                                    <div style="display: flex; gap: 1rem; font-size: 0.75rem; margin-bottom: 0.5rem;">
                                        <label
                                            style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer;">
                                            <input type="radio" wire:model.live="rescheduleIsDeltaPaidNow"
                                                value="1">
                                            <span>Lunasi Sekarang di Frontdesk</span>
                                        </label>
                                        <label
                                            style="display: flex; align-items: center; gap: 0.25rem; cursor: pointer;">
                                            <input type="radio" wire:model.live="rescheduleIsDeltaPaidNow"
                                                value="0">
                                            <span>Tagihan Gantung (QR Ditahan)</span>
                                        </label>
                                    </div>

                                    @if ($rescheduleIsDeltaPaidNow)
                                        <label
                                            style="display: block; font-size: 0.6875rem; font-weight: 700; color: #991B1B; margin-bottom: 0.25rem;">Metode
                                            Bayar Selisih:</label>
                                        <select wire:model="reschedulePaymentMethod"
                                            style="width: 100%; border: 1px solid #F87171; border-radius: 6px; padding: 0.4rem; font-size: 0.75rem;">
                                            <option value="CASH">Tunai Kasir Frontdesk</option>
                                            <option value="EDC_BCA">Mesin EDC BCA / Mandiri</option>
                                            <option value="QRIS">QRIS Kasir Frontdesk</option>
                                        </select>
                                    @endif
                                </div>
                            @elseif($rescheduleDelta < 0)
                                <div style="margin-top: 0.5rem; font-size: 0.6875rem; color: #166534;">
                                    Dana selisih otomatis dicatat ke tabel <code>refunds</code> sebagai saldo deposit
                                    akun member.
                                </div>
                            @endif
                        </div>
                    @endif

                    <!-- Alasan Perubahan -->
                    <div style="margin-bottom: 0.5rem;">
                        <label
                            style="display: block; font-size: 0.75rem; font-weight: 700; color: #1F170D; margin-bottom: 0.35rem;">Alasan
                            Perubahan Jadwal</label>
                        <input type="text" wire:model="rescheduleReason"
                            placeholder="Misal: Customer salah booking via WhatsApp, hujan di lapangan outdoor..."
                            style="width: 100%; border: 1px solid #D4AF37; border-radius: 8px; padding: 0.5rem; font-size: 0.8125rem;">
                    </div>
                </div>

                <!-- Footer Modal -->
                <div
                    style="background: #FAF5E8; border-top: 1px solid #F0DB9D; padding: 1rem 1.5rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
                    <button type="button" wire:click="$set('showRescheduleModal', false)" class="adm-btn-sec"
                        style="background: #FFFFFF;">Batal</button>
                    <button type="button" wire:click="executeReschedule" wire:loading.attr="disabled"
                        @if (empty($availableSlots) || !$rescheduleStartTime) disabled @endif class="adm-btn-sec"
                        style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05; border: 1px solid #FBF0CE; font-weight: 800; cursor: pointer;">
                        <span wire:loading.remove wire:target="executeReschedule">Simpan &amp; Proses Jadwal</span>
                        <span wire:loading wire:target="executeReschedule">Memproses &amp; Mengunci...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 2: PELUNASAN TAGIHAN GANTUNG (QUICK SETTLE) -->
    @if ($showSettleModal)
        <div
            style="position: fixed; inset: 0; z-index: 99999; background: rgba(15, 10, 5, 0.7); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 1rem;">
            <div
                style="background: #FFFFFF; border-radius: 16px; width: 100%; max-width: 480px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); border: 1px solid #DC2626; overflow: hidden;">
                <!-- Header -->
                <div
                    style="background: #991B1B; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div
                            style="color: #FECACA; font-size: 0.6875rem; font-weight: 700; text-transform: uppercase;">
                            Frontdesk Cashier &bull; Settlement</div>
                        <div style="color: #FFFFFF; font-size: 1.125rem; font-weight: 800; margin-top: 0.25rem;">
                            Pelunasan Kasir Frontdesk (Cashier Settle)</div>
                    </div>
                    <button type="button" wire:click="$set('showSettleModal', false)"
                        style="background: none; border: none; color: #FFFFFF; font-size: 1.5rem; cursor: pointer;">&times;</button>
                </div>

                <!-- Body -->
                <div style="padding: 1.5rem;">
                    <div
                        style="background: #FEF2F2; border: 1px solid #FECACA; border-radius: 12px; padding: 1rem; margin-bottom: 1.25rem;">
                        <div style="font-size: 0.75rem; color: #991B1B; font-weight: 600;">Customer &amp; Tiket:</div>
                        <div style="font-size: 0.9375rem; font-weight: 800; color: #1F170D;">{{ $settleCustomerName }}
                            (#{{ $settleBookingCode }})</div>
                        <div style="margin-top: 0.5rem; font-size: 0.75rem; color: #4B5563;">
                            Nominal yang Harus Dilunasi:
                        </div>
                        <div
                            style="font-family: var(--font-mono, monospace); font-size: 1.5rem; font-weight: 900; color: #991B1B;">
                            Rp {{ number_format($settleAmount, 0, ',', '.') }}
                        </div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <label
                            style="display: block; font-size: 0.75rem; font-weight: 700; color: #1F170D; margin-bottom: 0.35rem;">Metode
                            Pembayaran:</label>
                        <select wire:model="settlePaymentMethod"
                            style="width: 100%; border: 1px solid #D4AF37; border-radius: 8px; padding: 0.5rem; font-size: 0.8125rem;">
                            <option value="CASH">Tunai Kasir Frontdesk</option>
                            <option value="EDC_BCA">Mesin EDC BCA / Mandiri</option>
                            <option value="TRANSFER">Transfer Rekening Kasir</option>
                            <option value="QRIS">QRIS Kasir Frontdesk</option>
                        </select>
                    </div>

                    <div style="font-size: 0.6875rem; color: #6B7280; line-height: 1.4;">
                        Setelah pembayaran diterima, sistem otomatis mengubah status menjadi <strong>PAID</strong> dan
                        <strong>merilis QR Code</strong> booking customer.
                    </div>
                </div>

                <!-- Footer -->
                <div
                    style="background: #FAF5E8; border-top: 1px solid #F0DB9D; padding: 1rem 1.5rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
                    <button type="button" wire:click="$set('showSettleModal', false)" class="adm-btn-sec"
                        style="background: #FFFFFF;">Batal</button>
                    <button type="button" wire:click="executeSettleSupplemental" wire:loading.attr="disabled"
                        class="adm-btn-sec"
                        style="background: #DC2626; color: #FFFFFF; border-color: #B91C1C; font-weight: 800;">
                        <span wire:loading.remove wire:target="executeSettleSupplemental">Terima Pembayaran &amp; Buka
                            Tiket</span>
                        <span wire:loading wire:target="executeSettleSupplemental">Memproses...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL 3: CANCEL & REFUND -->
    @if ($showCancelRefundModal)
        <div
            style="position: fixed; inset: 0; z-index: 99999; background: rgba(15, 10, 5, 0.7); backdrop-filter: blur(4px); display: flex; align-items: center; justify-content: center; padding: 1rem;">
            <div
                style="background: #FFFFFF; border-radius: 16px; width: 100%; max-width: 520px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); border: 1px solid #DC2626; overflow: hidden;">
                <!-- Header -->
                <div
                    style="background: #1F170D; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #DC2626;">
                    <div>
                        <div
                            style="color: #F87171; font-size: 0.6875rem; font-weight: 700; text-transform: uppercase;">
                            Pembatalan Resmi &bull; Kasir Audit</div>
                        <div style="color: #FFFFFF; font-size: 1.125rem; font-weight: 800; margin-top: 0.25rem;">
                            Batalkan Reservasi &amp; Refund</div>
                    </div>
                    <button type="button" wire:click="$set('showCancelRefundModal', false)"
                        style="background: none; border: none; color: #FFFFFF; font-size: 1.5rem; cursor: pointer;">&times;</button>
                </div>

                <!-- Body -->
                <div style="padding: 1.5rem;">
                    <div
                        style="background: #FAF5E8; border: 1px solid #F0DB9D; border-radius: 12px; padding: 1rem; margin-bottom: 1.25rem;">
                        <div style="font-size: 0.75rem; color: #8C6418;">Customer &amp; Tiket:</div>
                        <div style="font-size: 0.9375rem; font-weight: 800; color: #1F170D;">{{ $cancelCustomerName }}
                            (#{{ $cancelBookingCode }})</div>
                        <div style="margin-top: 0.25rem; font-size: 0.75rem; color: #4B5563;">
                            Total Bayar: <strong>Rp {{ number_format($originalTotalAmount, 0, ',', '.') }}</strong>
                        </div>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <label
                            style="display: block; font-size: 0.75rem; font-weight: 700; color: #1F170D; margin-bottom: 0.35rem;">Kategori
                            Alasan Pembatalan:</label>
                        <select wire:model="refundCategory"
                            style="width: 100%; border: 1px solid #D4AF37; border-radius: 8px; padding: 0.5rem; font-size: 0.8125rem;">
                            <option value="SALAH_BAYAR">Salah Bayar / Double Transfer</option>
                            <option value="FORCE_MAJEURE">Force Majeure (Hujan Badai / Lapangan Rusak)</option>
                            <option value="PERMINTAAN_MEMBER">Permintaan Khusus Member (Disetujui Manager)</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <label
                            style="display: block; font-size: 0.75rem; font-weight: 700; color: #1F170D; margin-bottom: 0.35rem;">Nominal
                            Pengembalian Dana (Rp):</label>
                        <input type="number" wire:model="refundAmount"
                            style="width: 100%; border: 1px solid #D4AF37; border-radius: 8px; padding: 0.5rem; font-size: 0.8125rem; font-family: var(--font-mono, monospace); font-weight: 700;">
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <label
                            style="display: block; font-size: 0.75rem; font-weight: 700; color: #1F170D; margin-bottom: 0.35rem;">Metode
                            Pengembalian:</label>
                        <select wire:model="refundMethod"
                            style="width: 100%; border: 1px solid #D4AF37; border-radius: 8px; padding: 0.5rem; font-size: 0.8125rem;">
                            <option value="TUNAI_KASIR">Tunai Kasir Frontdesk</option>
                            <option value="TRANSFER_MANUAL">Transfer Bank Manual</option>
                            <option value="DEPOSIT_MEMBER">Saldo Deposit Member</option>
                        </select>
                    </div>

                    <div style="margin-bottom: 0.5rem;">
                        <label
                            style="display: block; font-size: 0.75rem; font-weight: 700; color: #1F170D; margin-bottom: 0.35rem;">Catatan
                            Kasir:</label>
                        <textarea wire:model="refundNotes" rows="2" placeholder="Tuliskan keterangan detail kasir..."
                            style="width: 100%; border: 1px solid #D4AF37; border-radius: 8px; padding: 0.5rem; font-size: 0.8125rem;"></textarea>
                    </div>

                    <div style="font-size: 0.6875rem; color: #DC2626; line-height: 1.4; margin-top: 0.5rem;">
                        Perhatian: QR Code tiket akan langsung DIMATIKAN dan slot lapangan otomatis kembali TERSEDIA
                        untuk publik.
                    </div>
                </div>

                <!-- Footer -->
                <div
                    style="background: #FAF5E8; border-top: 1px solid #F0DB9D; padding: 1rem 1.5rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
                    <button type="button" wire:click="$set('showCancelRefundModal', false)" class="adm-btn-sec"
                        style="background: #FFFFFF;">Tutup</button>
                    <button type="button" wire:click="executeCancelRefund" wire:loading.attr="disabled"
                        class="adm-btn-sec"
                        style="background: #DC2626; color: #FFFFFF; border-color: #B91C1C; font-weight: 800;">
                        <span wire:loading.remove wire:target="executeCancelRefund">Konfirmasi &amp; Refund</span>
                        <span wire:loading wire:target="executeCancelRefund">Membatalkan...</span>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <!-- MODAL CHECK-IN GATE & HANDOVER ALAT -->
    @if ($showCheckInModal)
        <div
            style="position: fixed; inset: 0; z-index: 9999; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(8px); display: flex; align-items: center; justify-content: center; padding: 1rem;">
            <div
                style="background: #FFFFFF; border: 1.5px solid #DFC387; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(184, 134, 11, 0.35); width: 100%; max-width: 540px; overflow: hidden;">
                <!-- Header -->
                <div
                    style="background: linear-gradient(135deg, #FAF5E8 0%, #F5E8C7 100%); border-bottom: 1.5px solid #DFC387; padding: 1.25rem 1.5rem; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <div class="adm-pill adm-pill-gold" style="font-size: 0.625rem; padding: 0.2rem 0.6rem;">GATE
                            ACCESS &bull; CLUB 61 PADEL COURT</div>
                        <div style="color: #1F170D; font-size: 1.25rem; font-weight: 900; margin-top: 0.25rem;">
                            Check-In Gate &amp; Scanner</div>
                    </div>
                    <button type="button" wire:click="closeCheckInModal"
                        style="background: none; border: none; color: #78350F; font-size: 1.75rem; cursor: pointer; line-height: 1;">&times;</button>
                </div>

                <!-- Body -->
                <div style="padding: 1.5rem;">
                    <!-- Input Scan / Tiket -->
                    <div style="margin-bottom: 1.25rem;">
                        <label
                            style="display: block; font-size: 0.8125rem; font-weight: 800; color: #1F170D; margin-bottom: 0.4rem;">
                            Scan Barcode Gun / Input Kode Tiket:
                        </label>
                        <div style="display: flex; gap: 0.5rem;">
                            <input type="text" wire:model="checkInQuery" wire:keydown.enter="executeCheckIn"
                                placeholder="Scan QR atau ketik BK-PAD-XXXX..." autofocus
                                style="flex: 1; border: 1.5px solid #D4AF37; border-radius: 12px; padding: 0.65rem 0.85rem; font-size: 0.875rem; font-family: var(--font-mono, monospace); font-weight: 700; background: #FFFDF5; outline: none;">
                            <button type="button" wire:click="executeCheckIn" wire:loading.attr="disabled"
                                class="adm-btn-sec"
                                style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05; border: 1px solid #FBF0CE; font-weight: 800; padding: 0.65rem 1rem;">
                                <span wire:loading.remove wire:target="executeCheckIn">Verifikasi</span>
                                <span wire:loading wire:target="executeCheckIn">Memproses...</span>
                            </button>
                        </div>
                        <div style="font-size: 0.6875rem; color: #8C7A58; margin-top: 0.35rem;">
                            Mendukung tembakan Barcode Scanner Gun USB, QR Code Hash, atau input manual kode booking.
                        </div>
                    </div>

                    <!-- HASIL VERIFIKASI & HANDOVER ALAT -->
                    @if ($checkInResult)
                        <div
                            style="border-radius: 16px; border: 1.5px solid {{ $checkInResult['already_checked_in'] ? '#FDE68A' : '#A7F3D0' }}; background: {{ $checkInResult['already_checked_in'] ? '#FFFBEB' : '#F0FDF4' }}; padding: 1.25rem; margin-bottom: 1rem;">
                            <div
                                style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                                <span class="adm-pill"
                                    style="background: {{ $checkInResult['already_checked_in'] ? '#FEF3C7' : '#DCFCE7' }}; color: {{ $checkInResult['already_checked_in'] ? '#92400E' : '#166534' }}; font-weight: 800;">
                                    {{ $checkInResult['already_checked_in'] ? 'SUDAH PERNAH CHECK-IN' : 'CHECK-IN BERHASIL' }}
                                </span>
                                <span
                                    style="font-size: 0.6875rem; color: #6B7280; font-family: var(--font-mono, monospace);">
                                    Gate Staff: {{ $checkInResult['gate_marshall'] ?? 'Kasir' }}
                                </span>
                            </div>

                            <div style="font-size: 1.125rem; font-weight: 900; color: #1F170D;">
                                {{ $checkInResult['player_name'] }}
                            </div>
                            <div style="font-size: 0.8125rem; color: #374151; font-weight: 600; margin-top: 0.2rem;">
                                {{ $checkInResult['court_name'] }} &bull; {{ $checkInResult['schedule'] }}
                            </div>
                            <div
                                style="font-size: 0.75rem; color: #6B7280; margin-top: 0.2rem; font-family: var(--font-mono, monospace);">
                                Tiket: <strong>{{ $checkInResult['booking_code'] }}</strong>
                            </div>

                            <!-- EQUIPMENT HANDOVER CHECKLIST -->
                            <div
                                style="margin-top: 1rem; border-top: 1px dashed {{ $checkInResult['already_checked_in'] ? '#FCD34D' : '#86EFAC' }}; padding-top: 0.75rem;">
                                <div
                                    style="font-size: 0.75rem; font-weight: 800; color: #1F170D; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.4rem;">
                                    Serah-Terima Peralatan (Equipment Handover):
                                </div>
                                @if (!empty($checkInResult['equipments']))
                                    <div style="display: flex; flex-direction: column; gap: 0.35rem;">
                                        @foreach ($checkInResult['equipments'] as $eq)
                                            <div
                                                style="background: #FFFFFF; border: 1px solid #D1D5DB; border-radius: 8px; padding: 0.5rem 0.75rem; display: flex; align-items: center; justify-content: space-between; font-size: 0.8125rem;">
                                                <span
                                                    style="font-weight: 700; color: #1F170D;">{{ $eq['name'] }}</span>
                                                <span
                                                    style="background: #FAF5E8; border: 1px solid #DFC387; color: #8C6418; font-weight: 800; padding: 0.15rem 0.5rem; border-radius: 6px; font-size: 0.75rem;">
                                                    {{ $eq['quantity'] }} Pcs
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                    <div
                                        style="margin-top: 0.5rem; font-size: 0.6875rem; color: #047857; font-weight: 700;">
                                        Harap serahkan raket &amp; bola di atas kepada pemain sebelum memasuki lapangan.
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
                <div
                    style="background: #FAF5E8; border-top: 1px solid #DFC387; padding: 1rem 1.5rem; display: flex; justify-content: flex-end; gap: 0.5rem;">
                    <button type="button" wire:click="closeCheckInModal" class="adm-btn-sec"
                        style="background: #FFFFFF;">
                        Tutup
                    </button>
                    @if ($checkInResult)
                        <button type="button" wire:click="closeCheckInModal" class="adm-btn-sec"
                            style="background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); color: #281A05; border: 1px solid #FBF0CE; font-weight: 800;">
                            Selesai &amp; Buka Akses Gate
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
