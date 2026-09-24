<div class="adm-wrap">
    <style>
        .pos-method-selector-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 0.65rem; }
        .pos-method-tab { border: 2px solid #E5E7EB; background: #F9FAFB; border-radius: 10px; padding: 0.7rem 0.5rem; text-align: center; cursor: pointer; transition: all 0.15s ease; user-select: none; }
        .pos-method-tab:hover { border-color: #DFC387; background: #FFFDF5; }
        .pos-method-tab.active { border-color: #B38622; background: linear-gradient(180deg, #FFFDF5 0%, #FAF5E8 100%); box-shadow: 0 4px 12px rgba(179, 134, 34, 0.18); }
        .pos-method-tab-title { font-size: 0.8125rem; font-weight: 900; color: #1F170D; }
        .pos-method-tab.active .pos-method-tab-title { color: #8C6418; }
        .pos-method-tab-sub { font-size: 0.625rem; color: #6B7280; margin-top: 0.15rem; }
        .pos-pay-content-card { background: #FFFDF5; border: 1.5px solid #DFC387; border-radius: 12px; padding: 1.1rem 1.25rem; }
        .pos-input { width: 100%; border: 1px solid #DFC387; border-radius: 7px; padding: 0.35rem 0.6rem; font-size: 0.75rem; font-weight: 700; color: #1F170D; background: #FFFFFF; outline: none; box-sizing: border-box; }
        .pos-input:focus { border-color: #B38622; box-shadow: 0 0 0 2px rgba(180,134,11,0.15); }
        .pos-terminal-card { background: #FFFFFF; border: 1.5px solid #DFC387; border-radius: 14px; display: flex; flex-direction: column; overflow: hidden; min-height: 0; }
        .pos-terminal-header { padding: 0.75rem 1.2rem; background: linear-gradient(135deg, #FAF5E8 0%, #F5E8C7 100%); border-bottom: 1.5px solid #DFC387; display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; flex-wrap: wrap; gap: 0.5rem; }
        .pos-terminal-body { flex: 1; overflow-y: auto; padding: 1.2rem 1.4rem; display: flex; flex-direction: column; gap: 1.1rem; }
    </style>

    @include('filament.partials.pos-subnav', ['activePos' => 'membership'])

    {{-- ============================
         TOP BAR: Judul Loket (selaras gaya pos-topbar di POS Walk-In Booking)
         ============================ --}}
    <div
        style="display:flex; align-items:center; justify-content:space-between; gap:0.75rem; padding:0.6rem 1rem; background:#FFFDF5; border:1.5px solid #DFC387; border-radius:14px; margin-bottom:0.75rem; flex-wrap:wrap;">
        <div style="display:flex; align-items:center; gap:0.6rem; flex-wrap:wrap;">
            <span style="font-size:0.8125rem; font-weight:900; color:#8C6418;">Kasir Penjualan Membership</span>
            <span style="font-size:0.6875rem; color:#7A643E;">Terbitkan keanggotaan multi-fasilitas (Padel, Gym, Sauna) untuk pelanggan secara instan.</span>
        </div>
        <div style="display:flex; align-items:center; gap:0.5rem; background:#FAF5E8; border:1.5px solid #DFC387; padding:0.25rem 0.65rem; border-radius:8px;">
            <span style="font-size:0.6875rem; font-weight:800; color:#8C6418;">
                {{ $this->plans->count() }} Paket Aktif Tersedia
            </span>
        </div>
    </div>

    <!-- POS Grid (2 Columns) -->
    <div style="display: grid; grid-template-columns: 1fr 340px; gap: 0.75rem;">

        @if ($posStep === 'selection')
        {{-- ==================== KIRI: PILIH PELANGGAN & PAKET ==================== --}}
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <!-- Customer Card (TANPA overflow:hidden — dropdown hasil pencarian member butuh render
                 di luar batas card lewat position:absolute, jadi rounded-corner header dibuat manual
                 pakai border-radius di header-nya sendiri, bukan clip dari parent) -->
            <div style="background: #FFFFFF; border: 1.5px solid #DFC387; border-radius: 14px;">
                <div
                    style="padding: 0.65rem 1rem; background: #FAF5E8; border-bottom: 1.5px solid #DFC387; border-top-left-radius: 12.5px; border-top-right-radius: 12.5px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
                    <span style="font-size: 0.8125rem; font-weight: 900; color: #1F170D;">1. Data Pelanggan / Member</span>
                    <div style="display: flex; gap: 0.5rem; font-size: 0.75rem;">
                        <button type="button" wire:click="$set('customerMode', 'quick_create')"
                            style="padding: 0.25rem 0.75rem; border-radius: 6px; font-weight: 700; cursor: pointer; border: 1px solid #DFC387; {{ $customerMode === 'quick_create' ? 'background: #D4AF37; color: #1F170D;' : 'background: #FFFFFF; color: #7A643E;' }}">
                            Walk-In Baru
                        </button>
                        <button type="button" wire:click="$set('customerMode', 'search')"
                            style="padding: 0.25rem 0.75rem; border-radius: 6px; font-weight: 700; cursor: pointer; border: 1px solid #DFC387; {{ $customerMode === 'search' ? 'background: #D4AF37; color: #1F170D;' : 'background: #FFFFFF; color: #7A643E;' }}">
                            Cari Member Lama
                        </button>
                    </div>
                </div>
                <div style="padding: 1rem 1.25rem;">

                @if ($selectedCustomerId)
                    <div
                        style="background: #FAF5E8; border: 1.5px solid #D4AF37; border-radius: 10px; padding: 0.75rem 1rem; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <div style="font-weight: 900; color: #1F170D; font-size: 0.9375rem;">
                                {{ $selectedCustomerName }}</div>
                            <div style="font-size: 0.75rem; color: #8C6418;">WhatsApp:
                                {{ $selectedCustomerPhone ?? '-' }}</div>
                        </div>
                        <button type="button" wire:click="clearCustomer"
                            style="background: #FEF2F2; border: 1px solid #FECACA; color: #DC2626; font-size: 0.75rem; font-weight: 800; padding: 0.35rem 0.65rem; border-radius: 6px; cursor: pointer;">
                            Ganti
                        </button>
                    </div>
                @elseif($customerMode === 'search')
                    <div style="position: relative;">
                        <input type="text" wire:model.live.debounce.300ms="customerSearch"
                            placeholder="Ketik nama, no whatsapp, atau email customer..."
                            style="width: 100%; border: 1.5px solid #DFC387; border-radius: 8px; padding: 0.6rem 0.85rem; font-size: 0.875rem; outline: none;">
                        @if (count($this->searchResults) > 0)
                            <div
                                style="position: absolute; top: 100%; left: 0; right: 0; z-index: 50; background: #FFFFFF; border: 1.5px solid #D4AF37; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); margin-top: 0.25rem; max-height: 200px; overflow-y: auto;">
                                @foreach ($this->searchResults as $res)
                                    <button type="button" wire:click="selectCustomer('{{ $res['id'] }}')"
                                        style="width: 100%; text-align: left; padding: 0.5rem 0.85rem; border: none; border-bottom: 1px solid #FAF2DE; background: #FFFFFF; cursor: pointer;">
                                        <div style="font-weight: 800; color: #1F170D; font-size: 0.8125rem;">
                                            {{ $res['name'] }}</div>
                                        <div style="font-size: 0.6875rem; color: #8C6418;">{{ $res['phone'] ?? '-' }}
                                            &bull; {{ $res['email'] }}</div>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; font-weight: 700; color: #7A643E; margin-bottom: 0.25rem;">Nomor
                                WhatsApp *</label>
                            <input type="text" wire:model="walkInPhone" placeholder="0812xxxxxxxx"
                                style="width: 100%; border: 1.5px solid #DFC387; border-radius: 8px; padding: 0.5rem 0.75rem; font-size: 0.8125rem;">
                        </div>
                        <div>
                            <label
                                style="display: block; font-size: 0.75rem; font-weight: 700; color: #7A643E; margin-bottom: 0.25rem;">Nama
                                Lengkap</label>
                            <input type="text" wire:model="walkInName" placeholder="Nama Pelanggan"
                                style="width: 100%; border: 1.5px solid #DFC387; border-radius: 8px; padding: 0.5rem 0.75rem; font-size: 0.8125rem;">
                        </div>
                    </div>
                @endif
                </div>
            </div>

            <!-- Plan Selection Card -->
            <div style="background: #FFFFFF; border: 1.5px solid #DFC387; border-radius: 14px; overflow: hidden;">
                <div style="padding: 0.65rem 1rem; background: #FAF5E8; border-bottom: 1.5px solid #DFC387;">
                    <span style="font-size: 0.8125rem; font-weight: 900; color: #1F170D;">2. Pilih Paket Membership Club 61</span>
                </div>
                <div style="padding: 1.25rem;">

                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 1rem;">
                    @foreach ($this->plans as $plan)
                        @php $isSelected = $selectedPlanId === $plan->id; @endphp
                        <div wire:click="selectPlan('{{ $plan->id }}')"
                            style="border: 2px solid {{ $isSelected ? '#D4AF37' : '#E8DCBF' }}; border-radius: 12px; padding: 1rem; cursor: pointer; transition: all 0.2s; background: {{ $isSelected ? '#FAF5E8' : '#FFFFFF' }};">
                            <div
                                style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem;">
                                <div>
                                    <div
                                        style="font-size: 0.6875rem; font-weight: 800; color: #8C6418; text-transform: uppercase;">
                                        {{ $plan->code }}</div>
                                    <div style="font-size: 1rem; font-weight: 900; color: #1F170D;">{{ $plan->name }}
                                    </div>
                                </div>
                                <span
                                    style="background: {{ $isSelected ? '#D4AF37' : '#FAF2DE' }}; color: #1F170D; font-size: 0.625rem; font-weight: 800; padding: 0.2rem 0.45rem; border-radius: 4px;">
                                    {{ $plan->duration_days }} Hari
                                </span>
                            </div>

                            <div style="font-size: 1.125rem; font-weight: 900; color: #7A5818; margin-bottom: 0.75rem;">
                                Rp {{ number_format($plan->price, 0, ',', '.') }}
                            </div>

                            <div
                                style="border-top: 1px dashed #DFC387; padding-top: 0.5rem; display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.6875rem; color: #665033;">
                                @foreach ($plan->benefits as $b)
                                    <div>
                                        <strong>{{ $b->facility }}:</strong>
                                        @if ($b->quota_type === 'HOURS')
                                            {{ (float) $b->quota_value }} Jam Bermain
                                        @elseif($b->quota_type === 'VISITS')
                                            {{ $b->quota_value ? (float) $b->quota_value . ' Sesi' : 'Unlimited' }}
                                        @else
                                            Diskon {{ $b->discount_percent }}%
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                </div>
            </div>
        </div>

        @elseif ($posStep === 'payment')
        {{-- ==================== KIRI: TERMINAL PEMBAYARAN KASIR IN-PAGE (sama persis dengan
             POS Walk-In Booking) ==================== --}}
        <div class="pos-terminal-card">
            <div class="pos-terminal-header">
                <div>
                    <div style="font-size:0.625rem; font-weight:900; color:#8C6418; text-transform:uppercase; letter-spacing:0.06em; background:#FAF5E8; border:1px solid #DFC387; border-radius:5px; padding:0.1rem 0.45rem; display:inline-block;">TERMINAL KASIR LOKET</div>
                    <div style="font-size:1.0625rem; font-weight:900; color:#1F170D; margin-top:0.2rem;">Layar Pembayaran &amp; Penyelesaian Transaksi</div>
                </div>
                <button type="button" wire:click="backToSelection"
                    style="padding:0.4rem 0.85rem; font-size:0.75rem; font-weight:800; border:1.5px solid #DFC387; background:#FFFFFF; border-radius:8px; cursor:pointer; color:#1F170D; display:flex; align-items:center; gap:0.3rem;">
                    &larr; Ubah Pilihan Paket
                </button>
            </div>

            <div class="pos-terminal-body">
                {{-- Tabs Metode Bayar --}}
                <div>
                    <div style="font-size:0.75rem; font-weight:900; color:#1F170D; margin-bottom:0.45rem;">Pilih Metode Pembayaran:</div>
                    <div class="pos-method-selector-grid">
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
                {{-- Form KARTU DEBIT --}}
                @if(in_array($paymentMethod, ['DEBIT_CARD', 'DEBIT']) || ($paymentMethod === 'EDC_BCA' && $edcCardType === 'DEBIT'))
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
                        &larr; Kembali ke Pilih Paket
                    </button>
                    <button type="button" wire:click="submitSale" wire:loading.attr="disabled"
                        style="flex:1; padding:0.75rem 1.5rem; border-radius:10px; background:linear-gradient(180deg,#F0DB9D 0%,#D4AF37 30%,#B38622 100%); color:#281A05; border:1px solid #FBF0CE; font-weight:900; font-size:0.9375rem; cursor:pointer; box-shadow:0 4px 14px rgba(184,134,11,0.35); text-transform:uppercase; letter-spacing:0.05em;">
                        <span wire:loading.remove wire:target="submitSale">Terbitkan &amp; Lunaskan</span>
                        <span wire:loading wire:target="submitSale">Memproses Transaksi...</span>
                    </button>
                </div>
            </div>
        </div>
        @endif

        <!-- Right: Order Summary (persisten di kedua langkah, sama seperti panel kanan Walk-In) -->
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            <div style="background: #FFFFFF; border: 1.5px solid #DFC387; border-radius: 14px; overflow: hidden; display: flex; flex-direction: column;">
                <div
                    style="padding: 0.65rem 1rem; background: linear-gradient(135deg, #FAF5E8 0%, #F5E8C7 100%); border-bottom: 1.5px solid #DFC387; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.5rem;">
                    <div>
                        @if ($posStep === 'payment')
                            <div style="font-size:0.625rem; font-weight:900; color:#8C6418; text-transform:uppercase; letter-spacing:0.06em; background:#FAF5E8; border:1px solid #DFC387; border-radius:5px; padding:0.1rem 0.45rem; display:inline-block;">LANGKAH 2 DARI 2</div>
                            <div style="font-size:0.9375rem; font-weight:900; color:#1F170D; margin-top:0.2rem;">Ringkasan Tagihan</div>
                        @else
                            <span style="font-size: 0.8125rem; font-weight: 900; color: #1F170D;">3. Rincian &amp; Pembayaran</span>
                        @endif
                    </div>
                    @if ($this->selectedPlan)
                        <div style="text-align:right;">
                            <div style="font-size:1.0625rem; font-weight:900; color:#B38622;">Rp {{ number_format($this->grandTotal, 0, ',', '.') }}</div>
                        </div>
                    @endif
                </div>
                <div style="padding: 1rem 1.25rem;">

                @if ($this->selectedPlan)
                    {{-- Data Customer (ringkasan, persisten di kedua langkah) --}}
                    <div style="background:#FAF5E8; border:1px solid #DFC387; border-radius:8px; padding:0.65rem 0.85rem; margin-bottom:1rem; font-size:0.75rem;">
                        <div style="font-size:0.625rem; font-weight:800; color:#7A5818; text-transform:uppercase; margin-bottom:0.2rem;">Data Customer</div>
                        @if ($selectedCustomerId)
                            <div style="font-weight:900; color:#1F170D;">{{ $selectedCustomerName }}</div>
                            <div style="color:#8C6418;">{{ $selectedCustomerPhone ?? '-' }}</div>
                        @elseif (trim($walkInName) !== '' || trim($walkInPhone) !== '')
                            <div style="font-weight:900; color:#1F170D;">{{ $walkInName ?: '(Nama belum diisi)' }}</div>
                            <div style="color:#8C6418;">{{ $walkInPhone ?: '(Nomor belum diisi)' }}</div>
                        @else
                            <div style="color:#9CA3AF; font-style:italic;">Belum diisi</div>
                        @endif
                    </div>

                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8125rem; color: #7A643E;">Paket Terpilih</div>
                        <div style="font-size: 0.9375rem; font-weight: 900; color: #1F170D;">
                            {{ $this->selectedPlan->name }}</div>
                        <div style="font-size: 0.75rem; color: #8C6418;">Rp
                            {{ number_format($this->selectedPlan->price, 0, ',', '.') }} &bull; Masa Aktif
                            {{ $this->selectedPlan->duration_days }} Hari</div>
                    </div>

                    <!-- Ringkasan Finansial -->
                    <div
                        style="display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.75rem; border-top: 1px solid #FAF2DE; padding-top: 0.75rem;">
                        <div style="display: flex; justify-content: space-between; color: #665033;">
                            <span>Subtotal</span>
                            <span>Rp {{ number_format($this->subtotal, 0, ',', '.') }}</span>
                        </div>
                        @if ($this->financeCalculation['tax_amount'] > 0)
                            <div style="display: flex; justify-content: space-between; color: #665033;">
                                <span>{{ $this->financeCalculation['tax_name'] ?: 'Pajak PB1' }}</span>
                                <span>Rp
                                    {{ number_format($this->financeCalculation['tax_amount'], 0, ',', '.') }}</span>
                            </div>
                        @endif
                        @if ($this->financeCalculation['admin_fee_amount'] > 0)
                            <div style="display: flex; justify-content: space-between; color: #665033;">
                                <span>Biaya Layanan</span>
                                <span>Rp
                                    {{ number_format($this->financeCalculation['admin_fee_amount'], 0, ',', '.') }}</span>
                            </div>
                        @endif
                        <div
                            style="display: flex; justify-content: space-between; font-size: 1rem; font-weight: 900; color: #1F170D; border-top: 1.5px solid #DFC387; padding-top: 0.5rem; margin-top: 0.25rem;">
                            <span>Grand Total</span>
                            <span style="color: #8C6418;">Rp {{ number_format($this->grandTotal, 0, ',', '.') }}</span>
                        </div>
                    </div>

                    @if ($posStep === 'payment')
                        <div style="margin-top:1rem; background:#FFFDF5; border:1px solid #DFC387; border-radius:8px; padding:0.65rem 0.85rem; font-size:0.75rem;">
                            <div style="color:#7A643E;">Metode Pembayaran</div>
                            <div style="font-weight:900; color:#1F170D;">{{ str_replace('_', ' ', $paymentMethod) }}</div>
                        </div>
                    @endif
                @else
                    <div style="text-align: center; padding: 2rem 1rem; color: #8C7A58; font-size: 0.8125rem;">
                        Pilih paket membership di kolom kiri untuk melanjutkan pembayaran.
                    </div>
                @endif
                </div>

                {{-- Footer: Action Button --}}
                @if ($this->selectedPlan)
                    <div style="padding: 0.85rem 1.25rem; background:#FAF5E8; border-top:1px solid #DFC387;">
                        @if ($posStep === 'selection')
                            <button type="button" wire:click="proceedToPayment"
                                style="width: 100%; background: linear-gradient(135deg, #D4AF37 0%, #B89327 100%); color: #1F170D; font-size: 0.875rem; font-weight: 900; text-transform: uppercase; padding: 0.75rem; border-radius: 10px; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(212,175,55,0.3);">
                                Lanjut ke Pembayaran &rarr;
                            </button>
                        @elseif ($posStep === 'payment')
                            <button type="button" wire:click="backToSelection"
                                style="width:100%; padding:0.65rem; border-radius:10px; border:1.5px solid #DFC387; background:#FFFFFF; color:#1F170D; font-weight:800; font-size:0.8125rem; cursor:pointer;">
                                &larr; Ubah Pilihan Paket
                            </button>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>

    <!-- Success Modal & Thermal Struk -->
    @if ($showSuccessModal && $completedMembershipData)
        <div
            style="position: fixed; inset: 0; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 1000; padding: 1rem;">
            <div
                style="background: #FFFFFF; border: 2px solid #D4AF37; border-radius: 20px; width: 100%; max-width: 440px; padding: 1.5rem; box-shadow: 0 20px 40px rgba(0,0,0,0.3);">
                <div
                    style="text-align: center; border-bottom: 1.5px dashed #DFC387; padding-bottom: 1rem; margin-bottom: 1rem;">
                    <div
                        style="font-size: 0.6875rem; font-weight: 800; color: #8C6418; letter-spacing: 0.1em; text-transform: uppercase;">
                        Struk Aktivasi Membership</div>
                    <div
                        style="font-size: 1.375rem; font-weight: 900; color: #1F170D; font-family: serif; margin-top: 0.25rem;">
                        CLUB 61 MEDAN</div>
                    <div style="font-size: 0.75rem; color: #665033; margin-top: 0.25rem;">Nomor:
                        {{ $completedMembershipData['membership_code'] }}</div>
                </div>

                <div
                    style="display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.75rem; color: #1F170D; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #7A643E;">Member</span>
                        <strong>{{ $completedMembershipData['customer_name'] }}</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #7A643E;">Paket</span>
                        <strong>{{ $completedMembershipData['plan_name'] }}</strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #7A643E;">Masa Aktif</span>
                        <span>{{ $completedMembershipData['start_date'] }} s/d
                            {{ $completedMembershipData['end_date'] }}</span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #7A643E;">Total Bayar</span>
                        <strong>Rp {{ number_format($completedMembershipData['grand_total'], 0, ',', '.') }}</strong>
                    </div>
                </div>

                <!-- Saldo Kuota Aktif -->
                <div
                    style="background: #FAF5E8; border: 1px solid #DFC387; border-radius: 8px; padding: 0.75rem; margin-bottom: 1.25rem;">
                    <div
                        style="font-size: 0.6875rem; font-weight: 800; color: #7A5818; margin-bottom: 0.35rem; text-transform: uppercase;">
                        Saldo Kuota Terisi (Top-Up)</div>
                    @foreach ($completedMembershipData['balances'] as $b)
                        <div
                            style="display: flex; justify-content: space-between; font-size: 0.75rem; color: #1F170D; padding: 0.15rem 0;">
                            <span>{{ $b['facility'] }}</span>
                            <strong>
                                @if ($b['quota_type'] === 'HOURS')
                                    {{ (float) $b['remaining_quota'] }} Jam
                                @elseif($b['quota_type'] === 'VISITS')
                                    {{ (float) $b['remaining_quota'] }} Sesi
                                @else
                                    Diskon {{ $b['discount_percent'] }}%
                                @endif
                            </strong>
                        </div>
                    @endforeach
                </div>

                <div style="display: flex; gap: 0.5rem;">
                    <button type="button" onclick="window.print()"
                        style="flex: 1; background: #FAF5E8; border: 1.5px solid #DFC387; color: #7A5818; padding: 0.6rem; border-radius: 8px; font-weight: 800; font-size: 0.75rem; cursor: pointer;">
                        Cetak Struk
                    </button>
                    <button type="button" wire:click="resetSale"
                        style="flex: 1; background: #D4AF37; border: none; color: #1F170D; padding: 0.6rem; border-radius: 8px; font-weight: 900; font-size: 0.75rem; cursor: pointer;">
                        Selesai &amp; Transaksi Baru
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
