<div class="adm-wrap">
    <!-- Header Banner -->
    <div class="adm-banner">
        <div>
            <div class="adm-pill adm-pill-gold">
                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background-color:#D4AF37;"></span>
                <span>Financial Business Intelligence &bull; Club 61 Padel Court</span>
            </div>
            <div class="adm-banner-title">
                Laporan Uang Masuk &amp; Analisis Finansial
            </div>
            <div class="adm-banner-sub">
                Rekapitulasi arus kas masuk, settlement payment gateway (Midtrans/Xendit/Cash), refund kasir, dan pendapatan bersih untuk manajemen &amp; PM.
            </div>
        </div>

        <div style="display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap;">
            <div class="adm-tabs" style="margin-bottom: 0; padding: 0.25rem; background: #FAF5E8; border: 1px solid #DFC387; border-radius: 14px;">
                <button type="button" wire:click="setPeriod('ALL')" class="adm-tab-btn {{ $period === 'ALL' ? 'active' : '' }}" style="font-size: 0.75rem; padding: 0.4rem 0.8rem;">
                    Semua Waktu
                </button>
                <button type="button" wire:click="setPeriod('THIS_MONTH')" class="adm-tab-btn {{ $period === 'THIS_MONTH' ? 'active' : '' }}" style="font-size: 0.75rem; padding: 0.4rem 0.8rem;">
                    Bulan Ini
                </button>
                <button type="button" wire:click="setPeriod('THIS_WEEK')" class="adm-tab-btn {{ $period === 'THIS_WEEK' ? 'active' : '' }}" style="font-size: 0.75rem; padding: 0.4rem 0.8rem;">
                    Minggu Ini
                </button>
                <button type="button" wire:click="setPeriod('TODAY')" class="adm-tab-btn {{ $period === 'TODAY' ? 'active' : '' }}" style="font-size: 0.75rem; padding: 0.4rem 0.8rem;">
                    Hari Ini
                </button>
            </div>
        </div>
    </div>

    <!-- Periode Aktif Banner -->
    <div style="margin-bottom: 1.25rem; font-size: 0.8125rem; color: #7A643E; font-weight: 700; display: flex; align-items: center; gap: 0.5rem;">
        <span>Menampilkan data periode:</span>
        <span class="adm-pill adm-pill-gold" style="font-size: 0.75rem;">{{ $periodLabel }}</span>
    </div>

    <!-- 4 Main Financial KPI Cards -->
    <div class="adm-metrics-grid" style="margin-bottom: 1.5rem;">
        <!-- Card 1: Uang Masuk Kotor -->
        <div class="adm-metric-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <div class="adm-metric-label">Total Uang Masuk Kotor (Gross)</div>
                    <div class="adm-metric-val" style="color: #1F170D;">
                        Rp {{ number_format($grossRevenue, 0, ',', '.') }}
                    </div>
                </div>
                <span class="adm-pill adm-pill-green">Cash In</span>
            </div>
            <div class="adm-metric-foot">
                <span>{{ $totalBookings }} Transaksi Lunas / Settled</span>
                <span style="color: #A68F63;">100% Terverifikasi</span>
            </div>
        </div>

        <!-- Card 2: Total Refund Dikeluarkan -->
        <div class="adm-metric-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <div class="adm-metric-label">Total Refund Dikeluarkan</div>
                    <div class="adm-metric-val" style="color: #DC2626;">
                        Rp {{ number_format($totalRefund, 0, ',', '.') }}
                    </div>
                </div>
                <span class="adm-pill" style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; font-weight: 700;">
                    Refund
                </span>
            </div>
            <div class="adm-metric-foot">
                <span>Pengembalian dana resmi</span>
                <span style="color: #DC2626; font-weight: 700;">H-24 / Force Majeure</span>
            </div>
        </div>

        <!-- Card 3: Pendapatan Bersih (Net Revenue) -->
        <div class="adm-metric-card" style="border: 2px solid #D4AF37; background: linear-gradient(135deg, #FFFFFF 0%, #FFFDF7 100%);">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <div class="adm-metric-label" style="color: #8C6418; font-weight: 800;">Pendapatan Bersih (Net Revenue)</div>
                    <div class="adm-metric-val" style="color: #8C6418;">
                        Rp {{ number_format($netRevenue, 0, ',', '.') }}
                    </div>
                </div>
                <span class="adm-pill adm-pill-gold">Net Income</span>
            </div>
            <div class="adm-metric-foot">
                <span>Gross dikurangi Total Refund</span>
                <span style="color: #047857; font-weight: 800;">Arus Kas Positif</span>
            </div>
        </div>

        <!-- Card 4: Okupansi Lapangan -->
        <div class="adm-metric-card">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <div class="adm-metric-label">Tingkat Okupansi Lapangan</div>
                    <div class="adm-metric-val" style="color: #1F170D;">
                        {{ $occupancyRate }}%
                    </div>
                </div>
                <span class="adm-pill adm-pill-green">Lapangan</span>
            </div>
            <div class="adm-metric-foot">
                <span>{{ $totalHoursBooked }} Jam sewa terpakai</span>
                <span style="color: #A68F63;">Kapasitas 4 Court</span>
            </div>
        </div>
    </div>

    <!-- 2 Kolom Rincian Finansial -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(360px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
        <!-- Kolom 1: Kanal Pembayaran (Gateway vs Cash) -->
        <div class="adm-card" style="padding: 1.25rem;">
            <div class="adm-card-head" style="margin-bottom: 1rem;">
                <div>
                    <div class="adm-card-title">Distribusi Kanal Pembayaran</div>
                    <div class="adm-card-sub">Rekap uang masuk berdasarkan saluran transaksi</div>
                </div>
                <span class="adm-pill adm-pill-gold">Payment Channel</span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #FAF5E8; border-radius: 12px; border: 1px solid #DFC387;">
                    <div>
                        <div style="font-weight: 800; font-size: 0.875rem; color: #1F170D;">Tunai Kasir Frontdesk / Transfer</div>
                        <div style="font-size: 0.6875rem; color: #7A643E;">Settlement langsung di kasir / transfer manual</div>
                    </div>
                    <div style="font-family: var(--font-mono, monospace); font-weight: 900; font-size: 1rem; color: #1F170D;">
                        Rp {{ number_format($cashTotal, 0, ',', '.') }}
                    </div>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #FFFDF5; border-radius: 12px; border: 1px solid #DFC387;">
                    <div>
                        <div style="font-weight: 800; font-size: 0.875rem; color: #1F170D;">Midtrans Gateway (QRIS, GoPay, VA)</div>
                        <div style="font-size: 0.6875rem; color: #7A643E;">Pembayaran otomatis instant settlement online</div>
                    </div>
                    <div style="font-family: var(--font-mono, monospace); font-weight: 900; font-size: 1rem; color: #8C6418;">
                        Rp {{ number_format($midtransTotal, 0, ',', '.') }}
                    </div>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #FFFDF5; border-radius: 12px; border: 1px solid #DFC387;">
                    <div>
                        <div style="font-weight: 800; font-size: 0.875rem; color: #1F170D;">Xendit Gateway (Kartu Kredit &amp; E-Wallet)</div>
                        <div style="font-size: 0.6875rem; color: #7A643E;">Kartu kredit internasional &amp; invoice virtual account</div>
                    </div>
                    <div style="font-family: var(--font-mono, monospace); font-weight: 900; font-size: 1rem; color: #8C6418;">
                        Rp {{ number_format($xenditTotal, 0, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>

        <!-- Kolom 2: Breakdown Lini Pendapatan -->
        <div class="adm-card" style="padding: 1.25rem;">
            <div class="adm-card-head" style="margin-bottom: 1rem;">
                <div>
                    <div class="adm-card-title">Rincian Pendapatan per Lini Layanan</div>
                    <div class="adm-card-sub">Kontribusi sewa lapangan, add-on raket/bola, dan coach</div>
                </div>
                <span class="adm-pill adm-pill-gold">Revenue Mix</span>
            </div>

            <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #FAF5E8; border-radius: 12px; border: 1px solid #DFC387;">
                    <div>
                        <div style="font-weight: 800; font-size: 0.875rem; color: #1F170D;">Sewa Lapangan Padel (Court Rental)</div>
                        <div style="font-size: 0.6875rem; color: #7A643E;">Sewa slot jam 4 lapangan panoramic</div>
                    </div>
                    <div style="font-family: var(--font-mono, monospace); font-weight: 900; font-size: 1rem; color: #1F170D;">
                        Rp {{ number_format($courtRevenue, 0, ',', '.') }}
                    </div>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #FFFDF5; border-radius: 12px; border: 1px solid #DFC387;">
                    <div>
                        <div style="font-weight: 800; font-size: 0.875rem; color: #1F170D;">Sewa Alat &amp; Add-on Bola (Equipment)</div>
                        <div style="font-size: 0.6875rem; color: #7A643E;">Sewa raket Babolat/Nox &amp; can bola padel</div>
                    </div>
                    <div style="font-family: var(--font-mono, monospace); font-weight: 900; font-size: 1rem; color: #8C6418;">
                        Rp {{ number_format($equipmentRevenue, 0, ',', '.') }}
                    </div>
                </div>

                <div style="display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 1rem; background: #FFFDF5; border-radius: 12px; border: 1px solid #DFC387;">
                    <div>
                        <div style="font-weight: 800; font-size: 0.875rem; color: #1F170D;">Pelatih &amp; Coaching Session</div>
                        <div style="font-size: 0.6875rem; color: #7A643E;">Sesi privat pelatih bersertifikasi WPT</div>
                    </div>
                    <div style="font-family: var(--font-mono, monospace); font-weight: 900; font-size: 1rem; color: #8C6418;">
                        Rp {{ number_format($coachRevenue, 0, ',', '.') }}
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Tabel 10 Mutasi Uang Masuk Terkini (Live Audit Log untuk PM) -->
    <div class="adm-card" style="padding: 1.5rem; margin-bottom: 1.5rem;">
        <div class="adm-card-head" style="margin-bottom: 1rem;">
            <div>
                <div class="adm-card-title">Riwayat Mutasi Uang Masuk Terkini</div>
                <div class="adm-card-sub">Daftar transaksi reservasi berstatus lunas yang telah diterima ke rekening / kas klub</div>
            </div>
            <a href="/admin/kelola-pemesanan" class="adm-pill adm-pill-gold" style="text-decoration: none;">
                Kelola Semua Pemesanan &rarr;
            </a>
        </div>

        <div style="overflow-x: auto;">
            <table class="adm-table-static" style="width: 100%; font-size: 0.8125rem;">
                <thead>
                    <tr style="border-bottom: 1.5px solid #DFC387; text-align: left; color: #8C6418; font-weight: 800;">
                        <th style="padding: 0.75rem;">WAKTU TRANSAKSI</th>
                        <th style="padding: 0.75rem;">KODE TIKET</th>
                        <th style="padding: 0.75rem;">MEMBER / CUSTOMER</th>
                        <th style="padding: 0.75rem;">LAPANGAN &amp; SESI</th>
                        <th style="padding: 0.75rem;">STATUS SETTLEMENT</th>
                        <th style="padding: 0.75rem; text-align: right;">UANG MASUK</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($latestTransactions as $tx)
                        <tr style="border-bottom: 1px solid #FAF2DE;">
                            <td style="padding: 0.75rem; color: #6B7280; font-size: 0.75rem;">
                                {{ $tx->created_at ? $tx->created_at->format('d M Y, H:i') : '-' }} WIB
                            </td>
                            <td style="padding: 0.75rem; font-family: var(--font-mono, monospace); font-weight: 700; color: #8C6418;">
                                {{ $tx->booking_code }}
                            </td>
                            <td style="padding: 0.75rem; font-weight: 800; color: #1F170D;">
                                {{ $tx->user?->name ?? 'Customer' }}
                            </td>
                            <td style="padding: 0.75rem; color: #4B5563;">
                                {{ $tx->court?->name ?? '-' }} ({{ $tx->booking_date->format('d M') }})
                            </td>
                            <td style="padding: 0.75rem;">
                                @if($tx->status === 'PAID')
                                    <span class="adm-pill adm-pill-green">Lunas (Paid)</span>
                                @elseif($tx->status === 'CHECKED_IN')
                                    <span class="adm-pill adm-pill-gold">Sedang Main</span>
                                @elseif($tx->status === 'COMPLETED')
                                    <span class="adm-pill" style="background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; font-weight: 700;">Selesai</span>
                                @elseif($tx->status === 'EXPIRED')
                                    <span class="adm-pill" style="background: #FEF3C7; color: #92400E; border: 1px solid #FDE68A; font-weight: 700;">Expired (Hangus)</span>
                                @else
                                    <span class="adm-pill">{{ $tx->status }}</span>
                                @endif
                            </td>
                            <td style="padding: 0.75rem; text-align: right; font-family: var(--font-mono, monospace); font-weight: 900; color: #047857; font-size: 0.875rem;">
                                + Rp {{ number_format($tx->total_amount, 0, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" style="padding: 2rem; text-align: center; color: #8C7A58;">
                                Belum ada transaksi masuk pada periode ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Tabel Pengembalian Dana Terkini (Refund Log) -->
    @if($latestRefunds->isNotEmpty())
        <div class="adm-card" style="padding: 1.5rem;">
            <div class="adm-card-head" style="margin-bottom: 1rem;">
                <div>
                    <div class="adm-card-title" style="color: #DC2626;">Riwayat Pengembalian Dana (Refund)</div>
                    <div class="adm-card-sub">Log persetujuan refund dan pengembalian kas ke member</div>
                </div>
                <span class="adm-pill" style="background: #FEE2E2; color: #DC2626; border: 1px solid #FECACA; font-weight: 700;">
                    Audit Refund
                </span>
            </div>

            <div style="overflow-x: auto;">
                <table class="adm-table-static" style="width: 100%; font-size: 0.8125rem;">
                    <thead>
                        <tr style="border-bottom: 1.5px solid #FECACA; text-align: left; color: #991B1B; font-weight: 800;">
                            <th style="padding: 0.75rem;">WAKTU REFUND</th>
                            <th style="padding: 0.75rem;">MEMBER</th>
                            <th style="padding: 0.75rem;">ALASAN PEMBATALAN</th>
                            <th style="padding: 0.75rem;">METODE</th>
                            <th style="padding: 0.75rem; text-align: right;">NOMINAL REFUND</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($latestRefunds as $rf)
                            <tr style="border-bottom: 1px solid #FEF2F2;">
                                <td style="padding: 0.75rem; color: #6B7280; font-size: 0.75rem;">
                                    {{ \Carbon\Carbon::parse($rf->created_at)->format('d M Y, H:i') }} WIB
                                </td>
                                <td style="padding: 0.75rem; font-weight: 800; color: #1F170D;">
                                    {{ $rf->customer_name ?? 'Member' }}
                                </td>
                                <td style="padding: 0.75rem; color: #374151; font-size: 0.75rem;">
                                    {{ $rf->reason ?? '-' }}
                                </td>
                                <td style="padding: 0.75rem;">
                                    <span class="adm-pill" style="background: #F3F4F6; color: #374151; border: 1px solid #D1D5DB; font-size: 0.6875rem;">
                                        {{ $rf->status }}
                                    </span>
                                </td>
                                <td style="padding: 0.75rem; text-align: right; font-family: var(--font-mono, monospace); font-weight: 900; color: #DC2626; font-size: 0.875rem;">
                                    - Rp {{ number_format($rf->refund_amount, 0, ',', '.') }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
