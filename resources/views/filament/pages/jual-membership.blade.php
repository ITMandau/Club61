<div class="adm-wrap" style="max-width: 1400px; margin: 0 auto; padding: 1rem;">
    <!-- Header Banner -->
    <div
        style="background: linear-gradient(135deg, #1F170D 0%, #2D2314 100%); border: 1.5px solid #DFC387; border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center;">
        <div>
            <div
                style="display: inline-flex; align-items: center; gap: 0.5rem; background: rgba(212, 175, 55, 0.15); border: 1px solid rgba(212, 175, 55, 0.4); border-radius: 9999px; padding: 0.25rem 0.75rem; font-size: 0.75rem; color: #F5E6BE; font-weight: 700; margin-bottom: 0.5rem;">
                <span style="width: 6px; height: 6px; border-radius: 50%; background: #D4AF37;"></span>
                <span>POS Frontdesk &bull; Club 61 Membership</span>
            </div>
            <div style="font-size: 1.5rem; font-weight: 900; color: #FAF5E6; font-family: serif;">
                Kasir Penjualan Membership
            </div>
            <div style="font-size: 0.8125rem; color: #D4AF37; margin-top: 0.25rem;">
                Terbitkan keanggotaan multi-fasilitas (Padel, Gym, Sauna) untuk pelanggan secara instan dan terhubung ke
                shift kasir.
            </div>
        </div>
    </div>

    <!-- POS Grid (2 Columns) -->
    <div style="display: grid; grid-template-columns: 1fr 380px; gap: 1.5rem;">
        <!-- Left: Customer & Plan Selection -->
        <div style="display: flex; flex-direction: column; gap: 1.5rem;">
            <!-- Customer Card -->
            <div
                style="background: #FFFFFF; border: 1.5px solid #DFC387; border-radius: 16px; padding: 1.25rem; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                <div
                    style="font-size: 1rem; font-weight: 800; color: #1F170D; margin-bottom: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                    <span>1. Data Pelanggan / Member</span>
                    <div style="display: flex; gap: 0.5rem; font-size: 0.75rem;">
                        <button type="button" wire:click="$set('customerMode', 'quick_create')"
                            style="padding: 0.25rem 0.75rem; border-radius: 6px; font-weight: 700; cursor: pointer; border: 1px solid #DFC387; {{ $customerMode === 'quick_create' ? 'background: #D4AF37; color: #1F170D;' : 'background: #FAF5E8; color: #7A643E;' }}">
                            Walk-In Baru
                        </button>
                        <button type="button" wire:click="$set('customerMode', 'search')"
                            style="padding: 0.25rem 0.75rem; border-radius: 6px; font-weight: 700; cursor: pointer; border: 1px solid #DFC387; {{ $customerMode === 'search' ? 'background: #D4AF37; color: #1F170D;' : 'background: #FAF5E8; color: #7A643E;' }}">
                            Cari Member Lama
                        </button>
                    </div>
                </div>

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

            <!-- Plan Selection Card -->
            <div
                style="background: #FFFFFF; border: 1.5px solid #DFC387; border-radius: 16px; padding: 1.25rem; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                <div style="font-size: 1rem; font-weight: 800; color: #1F170D; margin-bottom: 1rem;">
                    2. Pilih Paket Membership Club 61
                </div>

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

        <!-- Right: Order Summary & Settlement -->
        <div style="display: flex; flex-direction: column; gap: 1.25rem;">
            <div
                style="background: #FFFFFF; border: 1.5px solid #DFC387; border-radius: 16px; padding: 1.25rem; box-shadow: 0 4px 12px rgba(0,0,0,0.03);">
                <div
                    style="font-size: 1rem; font-weight: 800; color: #1F170D; margin-bottom: 1rem; border-bottom: 1px solid #FAF2DE; padding-bottom: 0.5rem;">
                    3. Rincian &amp; Pembayaran
                </div>

                @if ($this->selectedPlan)
                    <div style="margin-bottom: 1rem;">
                        <div style="font-size: 0.8125rem; color: #7A643E;">Paket Terpilih</div>
                        <div style="font-size: 0.9375rem; font-weight: 900; color: #1F170D;">
                            {{ $this->selectedPlan->name }}</div>
                        <div style="font-size: 0.75rem; color: #8C6418;">Rp
                            {{ number_format($this->selectedPlan->price, 0, ',', '.') }} &bull; Masa Aktif
                            {{ $this->selectedPlan->duration_days }} Hari</div>
                    </div>

                    <!-- Diskon Manual Admin -->
                    <div
                        style="background: #FAF5E8; border: 1px solid #DFC387; border-radius: 8px; padding: 0.75rem; margin-bottom: 1rem;">
                        <label
                            style="display: block; font-size: 0.6875rem; font-weight: 800; color: #7A5818; margin-bottom: 0.25rem;">Diskon
                            Manual Kasir (%)</label>
                        <div style="display: flex; gap: 0.5rem; margin-bottom: 0.5rem;">
                            <input type="number" step="1" min="0" max="100"
                                wire:model.live="manualDiscountPercent" placeholder="0"
                                style="width: 80px; border: 1.5px solid #DFC387; border-radius: 6px; padding: 0.35rem 0.5rem; font-size: 0.8125rem; text-align: right;">
                            <input type="text" wire:model="manualDiscountReason"
                                placeholder="Alasan diskon (wajib jika > 0%)..."
                                style="flex: 1; border: 1.5px solid #DFC387; border-radius: 6px; padding: 0.35rem 0.5rem; font-size: 0.75rem;">
                        </div>
                        @if ($this->manualDiscountPercent > 0)
                            <div style="font-size: 0.6875rem; color: #047857; font-weight: 700;">
                                Potongan: -Rp {{ number_format($this->discountAmount, 0, ',', '.') }}
                            </div>
                        @endif
                    </div>

                    <!-- Ringkasan Finansial -->
                    <div
                        style="display: flex; flex-direction: column; gap: 0.35rem; font-size: 0.75rem; border-top: 1px solid #FAF2DE; padding-top: 0.75rem; margin-bottom: 1rem;">
                        <div style="display: flex; justify-content: space-between; color: #665033;">
                            <span>Subtotal</span>
                            <span>Rp {{ number_format($this->subtotal, 0, ',', '.') }}</span>
                        </div>
                        @if ($this->discountAmount > 0)
                            <div style="display: flex; justify-content: space-between; color: #047857;">
                                <span>Diskon Kasir</span>
                                <span>-Rp {{ number_format($this->discountAmount, 0, ',', '.') }}</span>
                            </div>
                        @endif
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

                    <!-- Metode Pembayaran -->
                    <div style="margin-bottom: 1rem;">
                        <label
                            style="display: block; font-size: 0.75rem; font-weight: 800; color: #7A5818; margin-bottom: 0.35rem;">Metode
                            Pembayaran</label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.4rem;">
                            @foreach (['CASH' => 'Tunai (Cash)', 'DEBIT_CARD' => 'EDC Debit', 'CREDIT_CARD' => 'EDC Kredit', 'QRIS' => 'QRIS Dinamis'] as $k => $label)
                                <button type="button" wire:click="$set('paymentMethod', '{{ $k }}')"
                                    style="padding: 0.45rem 0.5rem; border-radius: 6px; font-size: 0.6875rem; font-weight: 700; cursor: pointer; border: 1.5px solid #DFC387; {{ $paymentMethod === $k ? 'background: #D4AF37; color: #1F170D;' : 'background: #FAF5E8; color: #665033;' }}">
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    @if ($paymentMethod === 'CASH')
                        <div style="margin-bottom: 1rem;">
                            <label
                                style="display: block; font-size: 0.75rem; font-weight: 800; color: #7A5818; margin-bottom: 0.25rem;">Uang
                                Tunai Diterima (Rp)</label>
                            <input type="number" wire:model.live="cashReceived" placeholder="Nominal tunai..."
                                style="width: 100%; border: 1.5px solid #DFC387; border-radius: 8px; padding: 0.5rem 0.75rem; font-size: 0.875rem; font-weight: 700;">
                            @if ($this->cashReceived >= $this->grandTotal)
                                <div style="font-size: 0.75rem; font-weight: 800; color: #047857; margin-top: 0.25rem;">
                                    Kembalian: Rp {{ number_format($this->cashChange, 0, ',', '.') }}
                                </div>
                            @endif
                        </div>
                    @endif

                    <button type="button" wire:click="submitSale"
                        style="width: 100%; background: linear-gradient(135deg, #D4AF37 0%, #B89327 100%); color: #1F170D; font-size: 0.875rem; font-weight: 900; text-transform: uppercase; padding: 0.75rem; border-radius: 10px; border: none; cursor: pointer; box-shadow: 0 4px 12px rgba(212,175,55,0.3);">
                        Terbitkan &amp; Lunaskan
                    </button>
                @else
                    <div style="text-align: center; padding: 2rem 1rem; color: #8C7A58; font-size: 0.8125rem;">
                        Pilih paket membership di kolom kiri untuk melanjutkan pembayaran.
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
