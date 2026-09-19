<div class="adm-wrap">
    <style>
        .md-tabs {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #E5E7EB;
            padding-bottom: 0.5rem;
        }
        .md-tab-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.65rem 1.25rem;
            border-radius: 12px;
            font-size: 0.875rem;
            font-weight: 800;
            color: #6B7280;
            background: transparent;
            border: 1.5px solid transparent;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .md-tab-btn:hover {
            color: #8C6418;
            background: #FAF5E8;
        }
        .md-tab-btn.active {
            color: #281A05;
            background: #FAF2DE;
            border-color: #D4AF37;
            box-shadow: 0 4px 12px rgba(212, 175, 55, 0.15);
        }
        .md-card {
            background: rgba(255, 255, 255, 0.98);
            border: 1.5px solid #DFC387;
            border-radius: 18px;
            box-shadow: 0 10px 30px -10px rgba(160, 120, 30, 0.12);
            backdrop-filter: blur(16px);
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            margin-bottom: 1.5rem;
        }
        .md-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1.5px solid #F3E8CE;
        }
        .md-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            font-size: 0.875rem;
        }
        .md-table th {
            background: #FAF5E8;
            color: #5C410F;
            font-weight: 800;
            text-transform: uppercase;
            font-size: 0.725rem;
            letter-spacing: 0.05em;
            padding: 0.85rem 1rem;
            border-top: 1px solid #E5E7EB;
            border-bottom: 1.5px solid #DFC387;
            text-align: left;
        }
        .md-table td {
            padding: 1rem;
            border-bottom: 1px solid #F3F4F6;
            vertical-align: middle;
            color: #1F170D;
        }
        .md-table tr:hover td {
            background-color: #FFFDF9;
        }
        .md-badge {
            display: inline-block;
            padding: 0.2rem 0.6rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .md-badge-indoor {
            background: #E0F2FE;
            color: #0369A1;
            border: 1px solid #BAE6FD;
        }
        .md-badge-outdoor {
            background: #FEF3C7;
            color: #92400E;
            border: 1px solid #FCD34D;
        }
        .md-badge-racket {
            background: #F3E8FF;
            color: #7E22CE;
            border: 1px solid #E9D5FF;
        }
        .md-badge-ball {
            background: #ECFCCB;
            color: #4D7C0F;
            border: 1px solid #D9F99D;
        }
        .md-badge-towel {
            background: #E0E7FF;
            color: #4338CA;
            border: 1px solid #C7D2FE;
        }
        .md-badge-coach {
            background: #FCE7F3;
            color: #BE185D;
            border: 1px solid #FBCFE8;
        }
        .md-badge-other {
            background: #F3F4F6;
            color: #4B5563;
            border: 1px solid #E5E7EB;
        }
        .md-btn-gold {
            padding: 0.65rem 1.25rem;
            border-radius: 10px;
            background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%);
            border: 1px solid #FBF0CE;
            color: #281A05;
            font-weight: 800;
            font-size: 0.8125rem;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(184, 134, 11, 0.25);
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }
        .md-btn-gold:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(184, 134, 11, 0.35);
        }
        .md-btn-action {
            padding: 0.4rem 0.75rem;
            border-radius: 7px;
            font-size: 0.75rem;
            font-weight: 800;
            cursor: pointer;
            transition: all 0.1s;
            border: 1px solid transparent;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }
        .md-btn-edit {
            background: #FAF2DE;
            border-color: #D4AF37;
            color: #7A5818;
        }
        .md-btn-edit:hover {
            background: #F3E5BE;
        }
        .md-btn-danger {
            background: #FEE2E2;
            border-color: #FCA5A5;
            color: #991B1B;
        }
        .md-btn-danger:hover {
            background: #FECACA;
        }
        .md-btn-warning {
            background: #FEF3C7;
            border-color: #FCD34D;
            color: #92400E;
        }
        .md-btn-warning:hover {
            background: #FDE68A;
        }
        /* Switch */
        .md-switch {
            position: relative;
            width: 44px;
            height: 24px;
            background: #E5E7EB;
            border-radius: 9999px;
            transition: background-color 0.2s ease;
            border: 1px solid #D1D5DB;
            cursor: pointer;
            display: inline-block;
        }
        .md-switch.on {
            background: #16A34A;
            border-color: #15803D;
        }
        .md-switch-knob {
            position: absolute;
            top: 1px;
            left: 1px;
            width: 20px;
            height: 20px;
            background: #FFFFFF;
            border-radius: 50%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .md-switch.on .md-switch-knob {
            transform: translateX(20px);
        }
        /* Modal Overlay */
        .md-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(6px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
        }
        .md-modal-dialog {
            background: #FFFFFF;
            border: 2px solid #D4AF37;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            width: 100%;
            max-width: 540px;
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .md-form-group {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
        }
        .md-form-label {
            font-size: 0.775rem;
            font-weight: 800;
            color: #374151;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .md-form-input {
            width: 100%;
            padding: 0.65rem 0.85rem;
            border-radius: 9px;
            border: 1.5px solid #D1D5DB;
            font-size: 0.875rem;
            color: #1F170D;
            background: #FAF9F6;
            outline: none;
            transition: border-color 0.15s;
        }
        .md-form-input:focus {
            border-color: #D4AF37;
            background: #FFFFFF;
        }
        .md-form-select {
            width: 100%;
            padding: 0.65rem 0.85rem;
            border-radius: 9px;
            border: 1.5px solid #D1D5DB;
            font-size: 0.875rem;
            color: #1F170D;
            background: #FAF9F6;
            outline: none;
        }
    </style>

    {{-- HEADER BANNER --}}
    <div class="adm-banner">
        <div>
            <div class="adm-pill adm-pill-gold" style="margin-bottom:0.4rem;">
                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background-color:#D4AF37;"></span>
                <span>Konfigurasi Arena &bull; Master Data &amp; Tarif</span>
            </div>
            <div class="adm-banner-title">
                Master Data, Tarif Lapangan &amp; Add-ons
            </div>
            <div class="adm-banner-sub">
                Kelola tarif sewa per jam untuk seluruh lapangan padel (Jam Reguler vs Jam Ramai/Prime Time) serta manajemen persediaan dan tarif rental Add-ons (raket, bola, handuk, pelatih).
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:0.5rem;">
            @if($activeTab === 'courts')
                <button type="button" wire:click="openCreateCourtModal" class="md-btn-gold">
                    <span>+ Tambah Lapangan Baru</span>
                </button>
            @else
                <button type="button" wire:click="openCreateEquipmentModal" class="md-btn-gold">
                    <span>+ Tambah Add-on Baru</span>
                </button>
            @endif
        </div>
    </div>

    {{-- NAVIGASI TAB --}}
    <div class="md-tabs">
        <button type="button" wire:click="setActiveTab('courts')" class="md-tab-btn {{ $activeTab === 'courts' ? 'active' : '' }}">
            <span>Tarif Lapangan &amp; Jam Ramai</span>
        </button>
        <button type="button" wire:click="setActiveTab('equipments')" class="md-tab-btn {{ $activeTab === 'equipments' ? 'active' : '' }}">
            <span>Add-ons &amp; Peralatan Sewa</span>
        </button>
    </div>

    {{-- ================= TAB 1: LAPANGAN & TARIF ================= --}}
    @if($activeTab === 'courts')
        <div class="md-card">
            <div class="md-card-head">
                <div>
                    <div style="font-family:var(--font-serif); font-size:1.15rem; font-weight:800; color:#1F170D;">
                        Daftar Lapangan Padel &amp; Matriks Tarif
                    </div>
                    <div style="font-size:0.75rem; color:#7A643E; margin-top:0.2rem;">
                        Jam Reguler: Senin - Jumat 06:00 - 16:00 WIB &bull; Jam Ramai (Prime Time): Senin - Jumat 17:00 - 23:00 WIB &amp; Seharian Akhir Pekan (Sabtu - Minggu).
                    </div>
                </div>
                <div style="display:inline-flex; align-items:center; gap:0.5rem;">
                    <span class="adm-pill adm-pill-gold">Total: {{ $this->courts->count() }} Lapangan</span>
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table class="md-table">
                    <thead>
                        <tr>
                            <th>Nama Lapangan</th>
                            <th>Tipe Venue</th>
                            <th>Tarif Reguler (06:00 - 16:00)</th>
                            <th>Tarif Prime Time (17:00 - 23:00 &amp; Wknd)</th>
                            <th style="text-align:center;">Status Aktif</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->courts as $court)
                            <tr>
                                <td>
                                    <div style="font-weight:800; font-size:0.925rem; color:#1F170D;">{{ $court->name }}</div>
                                    <div style="font-size:0.75rem; color:#8C6418; font-weight:600; margin-top:0.15rem;">
                                        {{ $court->description ?: ($court->type === 'INDOOR' ? 'Indoor • Central AC' : 'Outdoor • Open Air Court') }}
                                    </div>
                                    <div style="font-size:0.7rem; color:#6B7280; font-family:var(--font-mono); margin-top:0.1rem;">ID: {{ $court->id }}</div>
                                </td>
                                <td>
                                    @if(strtoupper($court->type) === 'INDOOR')
                                        <span class="md-badge md-badge-indoor">Indoor Panoramic</span>
                                    @else
                                        <span class="md-badge md-badge-outdoor">Outdoor Court</span>
                                    @endif
                                </td>
                                <td>
                                    <div style="font-family:var(--font-mono); font-weight:800; color:#8C6418; font-size:0.95rem;">
                                        Rp {{ number_format($court->hourly_rate_regular, 0, ',', '.') }}
                                    </div>
                                    <div style="font-size:0.7rem; color:#9CA3AF;">per jam sesi</div>
                                </td>
                                <td>
                                    <div style="font-family:var(--font-mono); font-weight:900; color:#B45309; font-size:0.95rem;">
                                        Rp {{ number_format($court->hourly_rate_prime, 0, ',', '.') }}
                                    </div>
                                    <div style="font-size:0.7rem; color:#9CA3AF;">per jam prime time</div>
                                </td>
                                <td style="text-align:center;">
                                    <div style="display:inline-flex; align-items:center; gap:0.5rem; cursor:pointer;"
                                        wire:click="toggleCourtStatus('{{ $court->id }}')">
                                        <div class="md-switch {{ $court->is_active ? 'on' : '' }}">
                                            <div class="md-switch-knob"></div>
                                        </div>
                                        <span style="font-size:0.75rem; font-weight:800; color:{{ $court->is_active ? '#15803D' : '#6B7280' }};">
                                            {{ $court->is_active ? 'AKTIF' : 'OFF' }}
                                        </span>
                                    </div>
                                </td>
                                <td style="text-align:right;">
                                    <button type="button" wire:click="openEditCourtModal('{{ $court->id }}')" class="md-btn-action md-btn-edit">
                                        <span>Edit Tarif</span>
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" style="text-align:center; padding:2.5rem; color:#6B7280;">
                                    Belum ada data lapangan. Silakan klik tombol "+ Tambah Lapangan Baru".
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ================= TAB 2: ADD-ONS & PERALATAN ================= --}}
    @if($activeTab === 'equipments')
        <div class="md-card">
            <div class="md-card-head">
                <div>
                    <div style="font-family:var(--font-serif); font-size:1.15rem; font-weight:800; color:#1F170D;">
                        Katalog Add-ons &amp; Peralatan Sewa Padel
                    </div>
                    <div style="font-size:0.75rem; color:#7A643E; margin-top:0.2rem;">
                        Peralatan yang dapat disewa pelanggan saat checkout online maupun walk-in di kasir POS.
                    </div>
                </div>

                {{-- Filter Status --}}
                <div style="display:flex; align-items:center; gap:0.5rem;">
                    <span style="font-size:0.75rem; font-weight:800; color:#5C410F;">Filter:</span>
                    <button type="button" wire:click="setEquipmentFilter('ALL')"
                        class="md-btn-action {{ $equipmentFilter === 'ALL' ? 'md-btn-edit' : '' }}" style="border:1px solid #DFC387;">
                        Semua ({{ $this->equipments->count() }})
                    </button>
                    <button type="button" wire:click="setEquipmentFilter('ACTIVE')"
                        class="md-btn-action {{ $equipmentFilter === 'ACTIVE' ? 'md-btn-edit' : '' }}" style="border:1px solid #DFC387;">
                        Aktif Saja
                    </button>
                    <button type="button" wire:click="setEquipmentFilter('INACTIVE')"
                        class="md-btn-action {{ $equipmentFilter === 'INACTIVE' ? 'md-btn-edit' : '' }}" style="border:1px solid #DFC387;">
                        Nonaktif
                    </button>
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table class="md-table">
                    <thead>
                        <tr>
                            <th>Nama Add-on / Alat</th>
                            <th>Kategori</th>
                            <th>Tarif Sewa per Sesi</th>
                            <th>Stok Unit</th>
                            <th>Riwayat Rental</th>
                            <th style="text-align:center;">Status Katalog</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->equipments as $eq)
                            <tr>
                                <td>
                                    <div style="font-weight:800; font-size:0.925rem; color:#1F170D;">{{ $eq->name }}</div>
                                    <div style="font-size:0.7rem; color:#6B7280; font-family:var(--font-mono);">ID: {{ $eq->id }}</div>
                                </td>
                                <td>
                                    @switch(strtoupper($eq->type))
                                        @case('RACKET')
                                            <span class="md-badge md-badge-racket">Raket Padel</span>
                                            @break
                                        @case('BALL')
                                            <span class="md-badge md-badge-ball">Bola Padel</span>
                                            @break
                                        @case('TOWEL')
                                            <span class="md-badge md-badge-towel">Handuk</span>
                                            @break
                                        @case('COACH')
                                            <span class="md-badge md-badge-coach">Pelatih</span>
                                            @break
                                        @default
                                            <span class="md-badge md-badge-other">{{ $eq->type }}</span>
                                    @endswitch
                                </td>
                                <td>
                                    <div style="font-family:var(--font-mono); font-weight:800; color:#8C6418; font-size:0.95rem;">
                                        Rp {{ number_format($eq->rental_price, 0, ',', '.') }}
                                    </div>
                                    <div style="font-size:0.7rem; color:#9CA3AF;">per sesi booking</div>
                                </td>
                                <td>
                                    <div style="font-family:var(--font-mono); font-weight:800; color:{{ $eq->stock_quantity > 0 ? '#1F170D' : '#DC2626' }}; font-size:0.925rem;">
                                        {{ $eq->stock_quantity }} Unit
                                    </div>
                                </td>
                                <td>
                                    @if($eq->historical_rentals_count > 0)
                                        <span class="md-badge md-badge-towel" title="Item ini pernah disewa dan tercatat di invoice pelanggan.">
                                            {{ $eq->historical_rentals_count }}x Disewa (Terkunci Historis)
                                        </span>
                                    @else
                                        <span style="font-size:0.75rem; color:#9CA3AF;">Belum ada sewa</span>
                                    @endif
                                </td>
                                <td style="text-align:center;">
                                    <div style="display:inline-flex; align-items:center; gap:0.5rem; cursor:pointer;"
                                        wire:click="toggleEquipmentStatus('{{ $eq->id }}')">
                                        <div class="md-switch {{ $eq->is_active ? 'on' : '' }}">
                                            <div class="md-switch-knob"></div>
                                        </div>
                                        <span style="font-size:0.75rem; font-weight:800; color:{{ $eq->is_active ? '#15803D' : '#6B7280' }};">
                                            {{ $eq->is_active ? 'AKTIF' : 'OFF' }}
                                        </span>
                                    </div>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex; align-items:center; gap:0.35rem;">
                                        <button type="button" wire:click="openEditEquipmentModal('{{ $eq->id }}')" class="md-btn-action md-btn-edit">
                                            <span>Edit</span>
                                        </button>

                                        @if($eq->historical_rentals_count > 0)
                                            <button type="button"
                                                wire:click="deleteEquipment('{{ $eq->id }}')"
                                                wire:confirm="Item ini memiliki riwayat penyewaan pada invoice pelanggan. Menghapus item ini akan mengalihkannya ke status NONAKTIF agar tidak dapat disewa lagi, dengan tetap menjaga keutuhan struk/invoice masa lalu. Lanjutkan?"
                                                class="md-btn-action md-btn-warning"
                                                title="Nonaktifkan item dengan menjaga invoice historis tetap utuh">
                                                <span>Nonaktifkan</span>
                                            </button>
                                        @else
                                            <button type="button"
                                                wire:click="deleteEquipment('{{ $eq->id }}')"
                                                wire:confirm="Item ini belum pernah disewa sama sekali. Yakin ingin menghapusnya secara permanen dari sistem?"
                                                class="md-btn-action md-btn-danger"
                                                title="Hapus permanen dari database">
                                                <span>Hapus</span>
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" style="text-align:center; padding:2.5rem; color:#6B7280;">
                                    Belum ada data Add-on. Silakan klik tombol "+ Tambah Add-on Baru".
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ================= MODAL EDIT/TAMBAH LAPANGAN ================= --}}
    @if($showCourtModal)
        <div class="md-modal-backdrop">
            <div class="md-modal-dialog">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid #F3E8CE; padding-bottom:0.75rem;">
                    <div style="font-family:var(--font-serif); font-size:1.15rem; font-weight:800; color:#1F170D;">
                        {{ $editingCourtId ? 'Edit Tarif & Detail Lapangan' : 'Tambah Lapangan Padel Baru' }}
                    </div>
                    <button type="button" wire:click="closeCourtModal" style="background:none; border:none; font-size:1.25rem; font-weight:800; color:#9CA3AF; cursor:pointer;">
                        &times;
                    </button>
                </div>

                <div style="display:flex; flex-direction:column; gap:1rem;">
                    <div class="md-form-group">
                        <label class="md-form-label">Nama Lapangan</label>
                        <input type="text" wire:model="courtName" class="md-form-input" placeholder="Contoh: Court 1 - Panoramic Indoor">
                        @error('courtName') <span style="font-size:0.75rem; color:#DC2626;">{{ $message }}</span> @enderror
                    </div>

                    <div class="md-form-group">
                        <label class="md-form-label">Jenis &amp; Keterangan Fasilitas</label>
                        <input type="text" wire:model="courtDescription" class="md-form-input" placeholder="Contoh: Indoor • Central AC atau Outdoor • Open Air Court">
                        <span style="font-size:0.7rem; color:#6B7280;">Teks ini ditampilkan pada sub-judul kartu lapangan di halaman booking pelanggan.</span>
                        @error('courtDescription') <span style="font-size:0.75rem; color:#DC2626;">{{ $message }}</span> @enderror
                    </div>

                    <div class="md-form-group">
                        <label class="md-form-label">Tipe Lapangan</label>
                        <select wire:model="courtType" class="md-form-select">
                            <option value="INDOOR">INDOOR (Full AC / Panoramic Glass)</option>
                            <option value="OUTDOOR">OUTDOOR (Open Air Stadium)</option>
                        </select>
                        @error('courtType') <span style="font-size:0.75rem; color:#DC2626;">{{ $message }}</span> @enderror
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="md-form-group">
                            <label class="md-form-label">Tarif Reguler (Rp/Jam)</label>
                            <input type="number" step="10000" wire:model="hourlyRateRegular" class="md-form-input" placeholder="300000">
                            <span style="font-size:0.7rem; color:#6B7280;">Senin - Jumat (06:00 - 16:00)</span>
                            @error('hourlyRateRegular') <span style="font-size:0.75rem; color:#DC2626;">{{ $message }}</span> @enderror
                        </div>

                        <div class="md-form-group">
                            <label class="md-form-label">Tarif Prime Time (Rp/Jam)</label>
                            <input type="number" step="10000" wire:model="hourlyRatePrime" class="md-form-input" placeholder="450000">
                            <span style="font-size:0.7rem; color:#6B7280;">Malam (17:00 - 23:00) &amp; Weekend</span>
                            @error('hourlyRatePrime') <span style="font-size:0.75rem; color:#DC2626;">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="md-form-group">
                        <label class="md-form-label">Status Lapangan di Jadwal Publik</label>
                        <div style="display:flex; align-items:center; gap:0.65rem; margin-top:0.25rem;">
                            <label style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.875rem; font-weight:700; cursor:pointer;">
                                <input type="radio" wire:model="courtIsActive" value="1">
                                <span style="color:#15803D;">Aktif (Bisa Dipesan Publik)</span>
                            </label>
                            <label style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.875rem; font-weight:700; cursor:pointer; margin-left:1rem;">
                                <input type="radio" wire:model="courtIsActive" value="0">
                                <span style="color:#6B7280;">Nonaktif (Maintenance / Tutup)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.75rem; border-top:1.5px solid #F3E8CE; padding-top:1rem;">
                    <button type="button" wire:click="closeCourtModal" class="md-btn-action" style="background:#F3F4F6; color:#4B5563;">
                        Batal
                    </button>
                    <button type="button" wire:click="saveCourt" class="md-btn-gold">
                        Simpan Lapangan
                    </button>
                </div>
            </div>
        </div>
    @endif

    {{-- ================= MODAL EDIT/TAMBAH ADD-ON ================= --}}
    @if($showEquipmentModal)
        <div class="md-modal-backdrop">
            <div class="md-modal-dialog">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid #F3E8CE; padding-bottom:0.75rem;">
                    <div style="font-family:var(--font-serif); font-size:1.15rem; font-weight:800; color:#1F170D;">
                        {{ $editingEquipmentId ? 'Edit Add-on / Peralatan' : 'Tambah Add-on Peralatan Baru' }}
                    </div>
                    <button type="button" wire:click="closeEquipmentModal" style="background:none; border:none; font-size:1.25rem; font-weight:800; color:#9CA3AF; cursor:pointer;">
                        &times;
                    </button>
                </div>

                <div style="display:flex; flex-direction:column; gap:1rem;">
                    <div class="md-form-group">
                        <label class="md-form-label">Nama Add-on / Alat Sewa</label>
                        <input type="text" wire:model="equipmentName" class="md-form-input" placeholder="Contoh: Raket Babolat Counter Viper">
                        @error('equipmentName') <span style="font-size:0.75rem; color:#DC2626;">{{ $message }}</span> @enderror
                    </div>

                    <div class="md-form-group">
                        <label class="md-form-label">Kategori Tipe</label>
                        <select wire:model="equipmentType" class="md-form-select">
                            <option value="RACKET">RACKET (Raket Padel)</option>
                            <option value="BALL">BALL (Bola Padel / Can)</option>
                            <option value="TOWEL">TOWEL (Handuk Olahraga)</option>
                            <option value="COACH">COACH (Pelatih / Private Trainer)</option>
                            <option value="OTHER">OTHER (Perlengkapan Lainnya)</option>
                        </select>
                        @error('equipmentType') <span style="font-size:0.75rem; color:#DC2626;">{{ $message }}</span> @enderror
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="md-form-group">
                            <label class="md-form-label">Tarif Sewa per Sesi (Rp)</label>
                            <input type="number" step="5000" wire:model="equipmentRentalPrice" class="md-form-input" placeholder="50000">
                            @error('equipmentRentalPrice') <span style="font-size:0.75rem; color:#DC2626;">{{ $message }}</span> @enderror
                        </div>

                        <div class="md-form-group">
                            <label class="md-form-label">Jumlah Stok Unit</label>
                            <input type="number" step="1" wire:model="equipmentStock" class="md-form-input" placeholder="20">
                            @error('equipmentStock') <span style="font-size:0.75rem; color:#DC2626;">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="md-form-group">
                        <label class="md-form-label">Status di Katalog Sewa</label>
                        <div style="display:flex; align-items:center; gap:0.65rem; margin-top:0.25rem;">
                            <label style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.875rem; font-weight:700; cursor:pointer;">
                                <input type="radio" wire:model="equipmentIsActive" value="1">
                                <span style="color:#15803D;">Aktif (Muncul di Checkout &amp; Kasir)</span>
                            </label>
                            <label style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.875rem; font-weight:700; cursor:pointer; margin-left:1rem;">
                                <input type="radio" wire:model="equipmentIsActive" value="0">
                                <span style="color:#6B7280;">Nonaktif (Disembunyikan)</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.75rem; border-top:1.5px solid #F3E8CE; padding-top:1rem;">
                    <button type="button" wire:click="closeEquipmentModal" class="md-btn-action" style="background:#F3F4F6; color:#4B5563;">
                        Batal
                    </button>
                    <button type="button" wire:click="saveEquipment" class="md-btn-gold">
                        Simpan Add-on
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>
