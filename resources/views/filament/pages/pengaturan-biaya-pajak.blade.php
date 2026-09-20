<div class="adm-wrap">
    <style>
        .tax-settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        @media (max-width: 1024px) {
            .tax-settings-grid {
                grid-template-columns: 1fr;
            }
        }
        .tax-card {
            background: rgba(255, 255, 255, 0.96);
            border: 1.5px solid #DFC387;
            border-radius: 18px;
            box-shadow: 0 10px 30px -10px rgba(160, 120, 30, 0.12);
            backdrop-filter: blur(16px);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            position: relative;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        .tax-card:hover {
            box-shadow: 0 14px 35px -8px rgba(160, 120, 30, 0.18);
        }
        .tax-card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 1rem;
            border-bottom: 1.5px solid #F3E8CE;
            gap: 1rem;
        }
        .tax-card-title {
            font-family: var(--font-serif);
            font-size: 1.15rem;
            font-weight: 800;
            color: #1F170D;
            line-height: 1.2;
        }
        .tax-card-sub {
            font-size: 0.75rem;
            color: #7A643E;
            margin-top: 0.25rem;
            line-height: 1.4;
        }
        /* Modern Switch Toggle */
        .tax-toggle-wrapper {
            display: flex;
            align-items: center;
            gap: 0.65rem;
            cursor: pointer;
            user-select: none;
        }
        .tax-toggle-label {
            font-size: 0.75rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .tax-toggle-active {
            color: #15803D;
        }
        .tax-toggle-inactive {
            color: #6B7280;
        }
        .tax-switch {
            position: relative;
            width: 48px;
            height: 26px;
            background: #E5E7EB;
            border-radius: 9999px;
            transition: background-color 0.2s ease;
            border: 1px solid #D1D5DB;
        }
        .tax-switch.on {
            background: #16A34A;
            border-color: #15803D;
        }
        .tax-switch-knob {
            position: absolute;
            top: 2px;
            left: 2px;
            width: 20px;
            height: 20px;
            background: #FFFFFF;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .tax-switch.on .tax-switch-knob {
            transform: translateX(22px);
        }
        /* Custom Field Styles */
        .tax-field-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
        }
        .tax-label {
            font-size: 0.725rem;
            font-weight: 800;
            color: #4A3A22;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .tax-hint {
            font-size: 0.6875rem;
            color: #8C754E;
            margin-top: 0.15rem;
        }
        .tax-input {
            width: 100%;
            background: #FFFDF9;
            border: 1.5px solid #DFC387;
            border-radius: 10px;
            padding: 0.6rem 0.85rem;
            font-size: 0.875rem;
            color: #1F170D;
            font-weight: 600;
            outline: none;
            transition: border-color 0.15s, box-shadow 0.15s;
        }
        .tax-input:focus {
            border-color: #B38622;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
            background: #FFFFFF;
        }
        .tax-select {
            width: 100%;
            background: #FFFDF9;
            border: 1.5px solid #DFC387;
            border-radius: 10px;
            padding: 0.6rem 0.85rem;
            font-size: 0.875rem;
            color: #1F170D;
            font-weight: 600;
            outline: none;
            cursor: pointer;
            transition: border-color 0.15s;
        }
        .tax-select:focus {
            border-color: #B38622;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
        }
        /* Segmented Radio Buttons */
        .tax-seg-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.5rem;
        }
        .tax-seg-btn {
            padding: 0.6rem 0.5rem;
            border-radius: 9px;
            border: 1.5px solid #E5E7EB;
            background: #F9FAFB;
            color: #4B5563;
            font-size: 0.75rem;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
            transition: all 0.15s;
        }
        .tax-seg-btn:hover {
            background: #FAF5E8;
            border-color: #DFC387;
            color: #8C6418;
        }
        .tax-seg-btn.active {
            background: #FAF2DE;
            border-color: #D4AF37;
            color: #7A5818;
            font-weight: 800;
            box-shadow: 0 2px 6px rgba(212, 175, 55, 0.15);
        }
        /* Notice box when disabled */
        .tax-disabled-notice {
            background: #F9FAFB;
            border: 1.5px dashed #D1D5DB;
            border-radius: 12px;
            padding: 2rem 1.5rem;
            text-align: center;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }
        /* Simulator Panel */
        .tax-sim-panel {
            background: rgba(255, 255, 255, 0.96);
            border: 1.5px solid #D4AF37;
            border-radius: 20px;
            box-shadow: 0 12px 35px -10px rgba(160, 120, 30, 0.15);
            backdrop-filter: blur(16px);
            padding: 1.5rem 1.75rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .tax-sim-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1.5px solid #F3E8CE;
        }
        .tax-sim-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.25rem;
        }
        @media (max-width: 768px) {
            .tax-sim-grid {
                grid-template-columns: 1fr;
            }
        }
        .tax-slip-card {
            background: #FFFDF9;
            border: 1.5px solid #DFC387;
            border-radius: 14px;
            padding: 1.25rem;
            box-shadow: 0 4px 15px rgba(180, 130, 20, 0.06);
            display: flex;
            flex-direction: column;
            gap: 0.85rem;
        }
        .tax-slip-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-size: 0.65rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .tax-slip-badge-online {
            background: #FEF3C7;
            border: 1px solid #FCD34D;
            color: #92400E;
        }
        .tax-slip-badge-pos {
            background: #E0F2FE;
            border: 1px solid #BAE6FD;
            color: #0369A1;
        }
        .tax-slip-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8125rem;
            color: #5C410F;
            padding: 0.35rem 0;
            border-bottom: 1px dashed rgba(223, 195, 135, 0.4);
        }
        .tax-slip-total {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            font-size: 0.9375rem;
            font-weight: 900;
            color: #1F170D;
            padding-top: 0.5rem;
            border-top: 1.5px solid #DFC387;
        }
        .tax-quick-btn {
            padding: 0.35rem 0.75rem;
            border-radius: 7px;
            border: 1px solid #DFC387;
            background: #FFFFFF;
            font-size: 0.75rem;
            font-weight: 700;
            color: #7A5818;
            cursor: pointer;
            transition: all 0.1s;
        }
        .tax-quick-btn:hover {
            background: #FAF2DE;
            border-color: #D4AF37;
            transform: translateY(-1px);
        }
        /* Sticky Save Bar */
        .tax-save-banner {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(253, 249, 240, 0.96) 100%);
            border: 2px solid #D4AF37;
            border-radius: 18px;
            padding: 1.25rem 1.75rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            box-shadow: 0 10px 30px -5px rgba(180, 130, 20, 0.2);
        }
        .tax-save-btn {
            padding: 0.75rem 2rem;
            border-radius: 12px;
            background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%);
            border: 1px solid #FBF0CE;
            color: #281A05;
            font-weight: 900;
            font-size: 0.875rem;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(184, 134, 11, 0.3);
            transition: all 0.15s ease;
            letter-spacing: 0.03em;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        .tax-save-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(184, 134, 11, 0.4);
            background: linear-gradient(180deg, #F5E5BE 0%, #DFC387 35%, #9E741B 100%);
        }
    </style>

    {{-- HEADER BANNER --}}
    <div class="adm-banner">
        <div>
            <div class="adm-pill adm-pill-gold" style="margin-bottom:0.4rem;">
                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background-color:#D4AF37;"></span>
                <span>Konfigurasi Keuangan &bull; Sentral Finansial</span>
            </div>
            <div class="adm-banner-title">
                Pengaturan Biaya Layanan &amp; Pajak Daerah
            </div>
            <div class="adm-banner-sub">
                Atur tarif Pajak Daerah (PB1 / PPh) dan Biaya Layanan Transaksi (Admin Fee). Perubahan di sini otomatis berlaku untuk seluruh transaksi booking online (Midtrans), kasir walk-in POS, dan modul klub lainnya.
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:0.5rem;">
            <button type="button" wire:click="saveSettings" class="tax-save-btn">
                <span>Simpan Pengaturan</span>
            </button>
        </div>
    </div>

    {{-- DUA KARTU PENGATURAN UTAMA --}}
    <div class="tax-settings-grid">

        {{-- ================= KARTU 1: PAJAK DAERAH ================= --}}
        <div class="tax-card">
            <div class="tax-card-header">
                <div>
                    <div class="tax-card-title">1. Pajak (PB1 / PPh / PPN)</div>
                    <div class="tax-card-sub">Pajak resmi atas fasilitas sewa lapangan padel &amp; layanan klub.</div>
                </div>

                {{-- Toggle Switch --}}
                <div class="tax-toggle-wrapper" wire:click="toggleTax">
                    <span class="tax-toggle-label {{ $isTaxEnabled ? 'tax-toggle-active' : 'tax-toggle-inactive' }}">
                        {{ $isTaxEnabled ? 'AKTIF' : 'NONAKTIF' }}
                    </span>
                    <div class="tax-switch {{ $isTaxEnabled ? 'on' : '' }}">
                        <div class="tax-switch-knob"></div>
                    </div>
                </div>
            </div>

            @if($isTaxEnabled)
                {{-- Nama Label Pajak --}}
                <div class="tax-field-group">
                    <label class="tax-label">Label Nama Pajak di Struk &amp; Invoice</label>
                    <input type="text" wire:model="taxName" class="tax-input" placeholder="Contoh: PB1 Pajak Daerah (10%)">
                    <div class="tax-hint">Nama ini akan tercetak jelas di struk kasir termal dan invoice digital pelanggan.</div>
                </div>

                {{-- Metode Hitung: Persentase vs Nominal Tetap --}}
                <div class="tax-field-group">
                    <label class="tax-label">Metode Perhitungan Pajak</label>
                    <div class="tax-seg-grid">
                        <button type="button" wire:click="setTaxType('PERCENTAGE')"
                            class="tax-seg-btn {{ $taxType === 'PERCENTAGE' ? 'active' : '' }}">
                            Persentase (%)
                        </button>
                        <button type="button" wire:click="setTaxType('FIXED')"
                            class="tax-seg-btn {{ $taxType === 'FIXED' ? 'active' : '' }}">
                            Nominal Tetap (Rp)
                        </button>
                    </div>
                </div>

                {{-- Nilai Tarif --}}
                <div class="tax-field-group">
                    <label class="tax-label">
                        {{ $taxType === 'PERCENTAGE' ? 'Besar Tarif Pajak (%)' : 'Nominal Pajak per Transaksi (Rp)' }}
                    </label>
                    <div style="position:relative;">
                        <input type="number" step="{{ $taxType === 'PERCENTAGE' ? '0.5' : '1000' }}" wire:model.live="taxRate" class="tax-input" style="padding-right:3rem; font-family:var(--font-mono); font-weight:800; font-size:1.1rem; color:#8C6418;">
                        <span style="position:absolute; right:1rem; top:50%; transform:translateY(-50%); font-weight:900; color:#8C6418; font-size:0.875rem;">
                            {{ $taxType === 'PERCENTAGE' ? '%' : 'IDR' }}
                        </span>
                    </div>
                    <div class="tax-hint">
                        {{ $taxType === 'PERCENTAGE' ? 'Pajak dihitung otomatis dari total subtotal sewa lapangan dan peralatan.' : 'Nominal tetap yang ditambahkan ke setiap transaksi tanpa melihat durasi main.' }}
                    </div>
                </div>

                {{-- Target Kanal --}}
                <div class="tax-field-group">
                    <label class="tax-label">Saluran Transaksi yang Dikenakan Pajak</label>
                    <select wire:model.live="taxChannels" class="tax-select">
                        <option value="ALL">Semua Transaksi (Booking Online &amp; Kasir Frontdesk POS)</option>
                        <option value="ONLINE_ONLY">Hanya Booking Online (Website / Mobile via Midtrans)</option>
                        <option value="POS_ONLY">Hanya Transaksi Langsung di Kasir POS Venue</option>
                    </select>
                </div>
            @else
                <div class="tax-disabled-notice">
                    <div style="font-weight:800; color:#4B5563; font-size:0.875rem;">Pajak Sedang Dinonaktifkan</div>
                    <div style="font-size:0.75rem; color:#6B7280; max-width:320px; line-height:1.4;">
                        Pelanggan tidak akan dikenakan biaya pajak. Klik toggle di pojok kanan atas untuk mengaktifkan tarif PB1 / PPh.
                    </div>
                </div>
            @endif
        </div>

        {{-- ================= KARTU 2: BIAYA LAYANAN / ADMIN ================= --}}
        <div class="tax-card">
            <div class="tax-card-header">
                <div>
                    <div class="tax-card-title">2. Biaya Layanan / Admin Fee</div>
                    <div class="tax-card-sub">Biaya pemrosesan transaksi, sistem administrasi, atau payment gateway.</div>
                </div>

                {{-- Toggle Switch --}}
                <div class="tax-toggle-wrapper" wire:click="toggleAdminFee">
                    <span class="tax-toggle-label {{ $isAdminFeeEnabled ? 'tax-toggle-active' : 'tax-toggle-inactive' }}">
                        {{ $isAdminFeeEnabled ? 'AKTIF' : 'NONAKTIF' }}
                    </span>
                    <div class="tax-switch {{ $isAdminFeeEnabled ? 'on' : '' }}">
                        <div class="tax-switch-knob"></div>
                    </div>
                </div>
            </div>

            @if($isAdminFeeEnabled)
                {{-- Nama Label Biaya --}}
                <div class="tax-field-group">
                    <label class="tax-label">Label Nama Biaya di Struk &amp; Invoice</label>
                    <input type="text" wire:model="adminFeeName" class="tax-input" placeholder="Contoh: Biaya Layanan / Admin">
                    <div class="tax-hint">Nama item rincian biaya yang akan terlihat oleh pelanggan saat checkout.</div>
                </div>

                {{-- Metode Hitung: Nominal Tetap vs Persentase --}}
                <div class="tax-field-group">
                    <label class="tax-label">Metode Perhitungan Biaya</label>
                    <div class="tax-seg-grid">
                        <button type="button" wire:click="setAdminFeeType('FIXED')"
                            class="tax-seg-btn {{ $adminFeeType === 'FIXED' ? 'active' : '' }}">
                            Nominal Tetap (Rp)
                        </button>
                        <button type="button" wire:click="setAdminFeeType('PERCENTAGE')"
                            class="tax-seg-btn {{ $adminFeeType === 'PERCENTAGE' ? 'active' : '' }}">
                            Persentase (%)
                        </button>
                    </div>
                </div>

                {{-- Nilai Biaya --}}
                <div class="tax-field-group">
                    <label class="tax-label">
                        {{ $adminFeeType === 'FIXED' ? 'Nominal Biaya Admin per Transaksi (Rp)' : 'Persentase Biaya Admin (%)' }}
                    </label>
                    <div style="position:relative;">
                        <input type="number" step="{{ $adminFeeType === 'FIXED' ? '500' : '0.5' }}" wire:model.live="adminFeeAmount" class="tax-input" style="padding-right:3rem; font-family:var(--font-mono); font-weight:800; font-size:1.1rem; color:#8C6418;">
                        <span style="position:absolute; right:1rem; top:50%; transform:translateY(-50%); font-weight:900; color:#8C6418; font-size:0.875rem;">
                            {{ $adminFeeType === 'FIXED' ? 'IDR' : '%' }}
                        </span>
                    </div>
                    <div class="tax-hint">
                        {{ $adminFeeType === 'FIXED' ? 'Contoh: Rp 2.500 per booking untuk menutupi biaya payment gateway online.' : 'Biaya admin dihitung proporsional dari nilai transaksi.' }}
                    </div>
                </div>

                {{-- Target Kanal --}}
                <div class="tax-field-group">
                    <label class="tax-label">Saluran Transaksi yang Dikenakan Biaya</label>
                    <select wire:model.live="adminFeeChannels" class="tax-select">
                        <option value="ONLINE_ONLY">Hanya Booking Online (Midtrans QRIS / Virtual Account)</option>
                        <option value="POS_ONLY">Hanya Transaksi di Kasir Frontdesk POS</option>
                        <option value="ALL">Semua Transaksi (Online &amp; Kasir POS)</option>
                    </select>
                </div>
            @else
                <div class="tax-disabled-notice">
                    <div style="font-weight:800; color:#4B5563; font-size:0.875rem;">Biaya Layanan Sedang Dinonaktifkan</div>
                    <div style="font-size:0.75rem; color:#6B7280; max-width:320px; line-height:1.4;">
                        Pelanggan tidak dibebankan biaya layanan/admin tambahan. Aktifkan toggle di atas jika ingin menambahkan biaya per transaksi.
                    </div>
                </div>
            @endif
        </div>

    </div>

    {{-- ================= KARTU 3: SIMULATOR TRANSAKSI REAL-TIME ================= --}}
    @php
        $sim = $this->simulationResult;
    @endphp

    <div class="tax-sim-panel">
        <div class="tax-sim-head">
            <div>
                <div style="display:inline-flex; align-items:center; gap:0.35rem; font-size:0.6875rem; font-weight:900; color:#8C6418; text-transform:uppercase; letter-spacing:0.08em; background:#FAF2DE; border:1px solid #D9BE84; border-radius:999px; padding:0.2rem 0.65rem; margin-bottom:0.3rem;">
                    <span>Pratinjau Hasil Nyata</span>
                </div>
                <div style="font-family:var(--font-serif); font-size:1.15rem; font-weight:800; color:#1F170D;">
                    Simulasi Rincian Tagihan Pelanggan (Live Calculator)
                </div>
                <div style="font-size:0.75rem; color:#7A643E; margin-top:0.2rem;">
                    Lihat persis bagaimana angka tagihan dihitung pada layar pelanggan dan kasir dengan pengaturan saat ini.
                </div>
            </div>

            {{-- Pengontrol Nominal Uji Coba --}}
            <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                <span style="font-size:0.75rem; font-weight:800; color:#5C410F;">Pilih Contoh Sewa:</span>
                <button type="button" wire:click="setSimulationSubtotal(200000)" class="tax-quick-btn">Rp 200.000</button>
                <button type="button" wire:click="setSimulationSubtotal(300000)" class="tax-quick-btn">Rp 300.000</button>
                <button type="button" wire:click="setSimulationSubtotal(500000)" class="tax-quick-btn">Rp 500.000</button>
                <div style="display:inline-flex; align-items:center; border:1px solid #DFC387; border-radius:8px; background:#FFF; overflow:hidden; padding-left:0.5rem;">
                    <span style="font-size:0.7rem; color:#8C6418; font-weight:800;">Rp</span>
                    <input type="number" step="10000" wire:model.live="simulationSubtotal" style="width:110px; padding:0.35rem 0.5rem; border:none; outline:none; font-family:var(--font-mono); font-weight:800; font-size:0.8125rem; color:#1F170D;">
                </div>
            </div>
        </div>

        {{-- 2 Komparasi Kartu Struk: Online vs Kasir POS --}}
        <div class="tax-sim-grid">

            {{-- Kolom Kiri: Booking Online --}}
            <div class="tax-slip-card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-weight:900; font-size:0.875rem; color:#1F170D;">Kanal Booking Online</div>
                    <span class="tax-slip-badge tax-slip-badge-online">Website &bull; Mobile &bull; Midtrans</span>
                </div>

                <div>
                    <div class="tax-slip-row">
                        <span>Sewa Lapangan &amp; Peralatan</span>
                        <span style="font-family:var(--font-mono); font-weight:800; color:#1F170D;">Rp {{ number_format($sim['subtotal'], 0, ',', '.') }}</span>
                    </div>
                    <div class="tax-slip-row">
                        <span>
                            {{ $isTaxEnabled ? $taxName : 'Pajak Daerah' }}
                            @if($isTaxEnabled && $sim['online']['tax'] > 0)
                                <span style="font-size:0.65rem; color:#15803D; font-weight:700;">({{ $taxType === 'PERCENTAGE' ? $taxRate.'%' : 'Tetap' }})</span>
                            @endif
                        </span>
                        <span style="font-family:var(--font-mono); font-weight:800; color:{{ $sim['online']['tax'] > 0 ? '#15803D' : '#9CA3AF' }};">
                            {{ $sim['online']['tax'] > 0 ? '+ Rp '.number_format($sim['online']['tax'], 0, ',', '.') : 'Rp 0 (Nonaktif)' }}
                        </span>
                    </div>
                    <div class="tax-slip-row">
                        <span>
                            {{ $isAdminFeeEnabled ? $adminFeeName : 'Biaya Layanan' }}
                            @if($isAdminFeeEnabled && $sim['online']['admin'] > 0)
                                <span style="font-size:0.65rem; color:#8C6418; font-weight:700;">({{ $adminFeeType === 'PERCENTAGE' ? $adminFeeAmount.'%' : 'Tetap' }})</span>
                            @endif
                        </span>
                        <span style="font-family:var(--font-mono); font-weight:800; color:{{ $sim['online']['admin'] > 0 ? '#8C6418' : '#9CA3AF' }};">
                            {{ $sim['online']['admin'] > 0 ? '+ Rp '.number_format($sim['online']['admin'], 0, ',', '.') : 'Rp 0 (Nonaktif)' }}
                        </span>
                    </div>
                </div>

                <div class="tax-slip-total">
                    <div>
                        <div style="font-size:0.875rem; font-weight:900; color:#1F170D;">TOTAL BAYAR CUSTOMER:</div>
                        <div style="font-size:0.65rem; color:#6B7280; font-weight:600; margin-top:0.1rem;">Nominal yang dipotong dari saldo / QRIS Midtrans</div>
                    </div>
                    <span style="font-family:var(--font-serif); font-size:1.35rem; font-weight:900; color:#8C6418;">
                        Rp {{ number_format($sim['online']['grand_total'], 0, ',', '.') }}
                    </span>
                </div>
            </div>

            {{-- Kolom Kanan: Kasir Frontdesk POS --}}
            <div class="tax-slip-card">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div style="font-weight:900; font-size:0.875rem; color:#1F170D;">Kanal Kasir Frontdesk POS</div>
                    <span class="tax-slip-badge tax-slip-badge-pos">Walk-In &bull; Struk Termal POS</span>
                </div>

                <div>
                    <div class="tax-slip-row">
                        <span>Sewa Lapangan &amp; Peralatan</span>
                        <span style="font-family:var(--font-mono); font-weight:800; color:#1F170D;">Rp {{ number_format($sim['subtotal'], 0, ',', '.') }}</span>
                    </div>
                    <div class="tax-slip-row">
                        <span>
                            {{ $isTaxEnabled ? $taxName : 'Pajak Daerah' }}
                            @if($isTaxEnabled && $sim['pos']['tax'] > 0)
                                <span style="font-size:0.65rem; color:#15803D; font-weight:700;">({{ $taxType === 'PERCENTAGE' ? $taxRate.'%' : 'Tetap' }})</span>
                            @endif
                        </span>
                        <span style="font-family:var(--font-mono); font-weight:800; color:{{ $sim['pos']['tax'] > 0 ? '#15803D' : '#9CA3AF' }};">
                            {{ $sim['pos']['tax'] > 0 ? '+ Rp '.number_format($sim['pos']['tax'], 0, ',', '.') : 'Rp 0 (Nonaktif)' }}
                        </span>
                    </div>
                    <div class="tax-slip-row">
                        <span>
                            {{ $isAdminFeeEnabled ? $adminFeeName : 'Biaya Layanan' }}
                            @if($isAdminFeeEnabled && $sim['pos']['admin'] > 0)
                                <span style="font-size:0.65rem; color:#8C6418; font-weight:700;">({{ $adminFeeType === 'PERCENTAGE' ? $adminFeeAmount.'%' : 'Tetap' }})</span>
                            @endif
                        </span>
                        <span style="font-family:var(--font-mono); font-weight:800; color:{{ $sim['pos']['admin'] > 0 ? '#8C6418' : '#9CA3AF' }};">
                            {{ $sim['pos']['admin'] > 0 ? '+ Rp '.number_format($sim['pos']['admin'], 0, ',', '.') : 'Rp 0 (Nonaktif)' }}
                        </span>
                    </div>
                </div>

                <div class="tax-slip-total">
                    <div>
                        <div style="font-size:0.875rem; font-weight:900; color:#1F170D;">TOTAL DITERIMA KASIR:</div>
                        <div style="font-size:0.65rem; color:#6B7280; font-weight:600; margin-top:0.1rem;">Nominal yang wajib dibayar di mesin EDC / Tunai</div>
                    </div>
                    <span style="font-family:var(--font-serif); font-size:1.35rem; font-weight:900; color:#0284C7;">
                        Rp {{ number_format($sim['pos']['grand_total'], 0, ',', '.') }}
                    </span>
                </div>
            </div>

        </div>

        {{-- Info Banner Garansi Pembulatan Eksak --}}
        <div style="background:#FAF8F2; border:1px solid #DFC387; border-radius:10px; padding:0.65rem 0.9rem; font-size:0.75rem; color:#7A5818; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:0.5rem;">
            <div>
                <strong>Garansi Presisi Rupiah:</strong> Seluruh perhitungan menggunakan pembulatan bilangan bulat Rupiah murni tanpa desimal sen, sehingga nilai item Midtrans dan kasir dipastikan cocok 100%.
            </div>
            <span style="font-size:0.7rem; font-weight:800; color:#8C6418; background:#FFF; border:1px solid #DFC387; border-radius:6px; padding:0.2rem 0.5rem;">
                Mata Uang: IDR (Rupiah)
            </span>
        </div>
    </div>

    {{-- BOTTOM SAVE BAR --}}
    <div class="tax-save-banner">
        <div>
            <div style="font-weight:900; font-size:0.9375rem; color:#1F170D;">Simpan dan Terapkan Konfigurasi</div>
            <div style="font-size:0.75rem; color:#7A643E; margin-top:0.15rem;">
                Pastikan seluruh tarif sudah sesuai sebelum mengaktifkan ke sistem operasional venue.
            </div>
        </div>

        <button type="button" wire:click="saveSettings" class="tax-save-btn">
            <span>Simpan Pengaturan Finansial</span>
        </button>
    </div>

</div>
