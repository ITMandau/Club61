<div class="adm-wrap">
    <style>
        .cms-field-group {
            display: flex;
            flex-direction: column;
            gap: 0.4rem;
            margin-bottom: 1rem;
        }
        .cms-label {
            font-size: 0.75rem;
            font-weight: 700;
            color: #5C410F;
        }
        .cms-hint {
            font-size: 0.6875rem;
            color: #8C7A58;
        }
        .cms-input, .cms-select, .cms-textarea {
            background: #FDFBF5;
            border: 1.5px solid #DFC387;
            border-radius: 10px;
            padding: 0.6rem 0.85rem;
            font-size: 0.8125rem;
            font-weight: 600;
            color: #1C150B;
            outline: none;
            width: 100%;
            transition: border-color 0.15s ease;
        }
        .cms-input:focus, .cms-select:focus, .cms-textarea:focus {
            border-color: #B8860B;
            box-shadow: 0 0 0 3px rgba(212,175,55,0.2);
        }
        .cms-textarea {
            resize: vertical;
            min-height: 80px;
        }
        .cms-grid-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        .cms-grid-3 {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 1rem;
        }
        @media (max-width: 768px) {
            .cms-grid-2, .cms-grid-3 {
                grid-template-columns: 1fr;
            }
        }
        .cms-card-item {
            background: rgba(255, 255, 255, 0.92);
            border: 1.5px solid #DFC387;
            border-radius: 14px;
            padding: 1rem;
            margin-bottom: 0.85rem;
        }
        .cms-card-item-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            gap: 0.5rem;
            flex-wrap: wrap;
        }
        .cms-icon-btn {
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1.5px solid #DFC387;
            background: #FDFBF5;
            color: #7A5818;
            cursor: pointer;
            transition: background 0.15s ease;
            flex-shrink: 0;
        }
        .cms-icon-btn:hover {
            background: #FAF2DE;
        }
        .cms-icon-btn.danger {
            border-color: #F3B6B6;
            color: #B42318;
        }
        .cms-icon-btn.danger:hover {
            background: #FEF2F2;
        }
        .cms-add-btn {
            width: 100%;
            padding: 0.75rem;
            border-radius: 12px;
            border: 1.5px dashed #D4AF37;
            background: #FDFBF5;
            color: #8C6418;
            font-weight: 700;
            font-size: 0.8125rem;
            cursor: pointer;
            transition: background 0.15s ease;
        }
        .cms-add-btn:hover {
            background: #FAF2DE;
        }
        .cms-save-btn {
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
        }
        .cms-save-btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(184, 134, 11, 0.4);
        }
        .cms-lang-pair {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.6rem;
            align-items: start;
        }
        @media (max-width: 768px) {
            .cms-lang-pair {
                grid-template-columns: 1fr;
            }
        }
        .cms-lang-tag {
            display: inline-block;
            font-size: 0.625rem;
            font-weight: 800;
            padding: 0.05rem 0.4rem;
            border-radius: 5px;
            margin-left: 0.35rem;
            letter-spacing: 0.03em;
        }
        .cms-lang-tag-id {
            background: #FAF2DE;
            color: #8C6418;
        }
        .cms-lang-tag-en {
            background: #E7F0FA;
            color: #2A5C8C;
        }
    </style>

    {{-- HEADER BANNER --}}
    <div class="adm-banner">
        <div>
            <div class="adm-pill adm-pill-gold" style="margin-bottom:0.4rem;">
                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background-color:#D4AF37;"></span>
                <span>Company Profile &bull; Konten Publik</span>
            </div>
            <div class="adm-banner-title">
                Konten Halaman Depan &amp; Panel Login
            </div>
            <div class="adm-banner-sub">
                Teks di sini langsung dipakai ulang di halaman depan publik (<code>/</code>) dan panel kiri halaman login — jumlah lapangan, daftar fasilitas, alamat &amp; jam operasional cuma ada 1 sumber, tidak akan beda-beda lagi antar halaman.
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:0.5rem;">
            <button type="button" wire:click="save" class="cms-save-btn">
                <span>Simpan Perubahan</span>
            </button>
        </div>
    </div>

    {{-- KARTU 1: HERO HALAMAN DEPAN --}}
    <div class="adm-card">
        <div class="adm-card-head">
            <div>
                <div class="adm-card-title">1. Hero Halaman Depan (Company Profile)</div>
                <div class="adm-card-sub">Judul besar &amp; badge di halaman depan publik (welcome page).</div>
            </div>
        </div>

        <div class="cms-lang-pair" style="margin-bottom:1rem;">
            <div class="cms-field-group" style="margin-bottom:0;">
                <label class="cms-label">Badge Status (di atas judul) <span class="cms-lang-tag cms-lang-tag-id">ID</span></label>
                <input type="text" wire:model="heroBadgeText" class="cms-input" placeholder="Contoh: Medan Flagship Venue &bull; Gedung Indosat &bull; Open Daily">
            </div>
            <div class="cms-field-group" style="margin-bottom:0;">
                <label class="cms-label">Badge Status <span class="cms-lang-tag cms-lang-tag-en">EN</span></label>
                <input type="text" wire:model="heroBadgeTextEn" class="cms-input" placeholder="Kosongkan = pakai teks ID di atas">
            </div>
        </div>

        <div class="cms-lang-pair" style="margin-bottom:1rem;">
            <div class="cms-field-group" style="margin-bottom:0;">
                <label class="cms-label">Judul Baris 1 <span class="cms-lang-tag cms-lang-tag-id">ID</span></label>
                <input type="text" wire:model="heroHeadlineLine1" class="cms-input" placeholder="The Sanctuary for">
            </div>
            <div class="cms-field-group" style="margin-bottom:0;">
                <label class="cms-label">Judul Baris 1 <span class="cms-lang-tag cms-lang-tag-en">EN</span></label>
                <input type="text" wire:model="heroHeadlineLine1En" class="cms-input" placeholder="Kosongkan = pakai teks ID di atas">
            </div>
        </div>

        <div class="cms-lang-pair" style="margin-bottom:1rem;">
            <div class="cms-field-group" style="margin-bottom:0;">
                <label class="cms-label">Judul Bagian Emas (Highlight) <span class="cms-lang-tag cms-lang-tag-id">ID</span></label>
                <input type="text" wire:model="heroHeadlineHighlight" class="cms-input" placeholder="Padel Athletes">
            </div>
            <div class="cms-field-group" style="margin-bottom:0;">
                <label class="cms-label">Judul Bagian Emas <span class="cms-lang-tag cms-lang-tag-en">EN</span></label>
                <input type="text" wire:model="heroHeadlineHighlightEn" class="cms-input" placeholder="Kosongkan = pakai teks ID di atas">
            </div>
        </div>

        <div class="cms-lang-pair" style="margin-bottom:1rem;">
            <div class="cms-field-group" style="margin-bottom:0;">
                <label class="cms-label">Judul Baris Penutup <span class="cms-lang-tag cms-lang-tag-id">ID</span></label>
                <input type="text" wire:model="heroHeadlineLine2" class="cms-input" placeholder="in Medan.">
            </div>
            <div class="cms-field-group" style="margin-bottom:0;">
                <label class="cms-label">Judul Baris Penutup <span class="cms-lang-tag cms-lang-tag-en">EN</span></label>
                <input type="text" wire:model="heroHeadlineLine2En" class="cms-input" placeholder="Kosongkan = pakai teks ID di atas">
            </div>
        </div>

        <div class="cms-lang-pair">
            <div class="cms-field-group" style="margin-bottom:0;">
                <label class="cms-label">Subtitle / Deskripsi Fasilitas <span class="cms-lang-tag cms-lang-tag-id">ID</span></label>
                <textarea wire:model="heroSubtitle" class="cms-textarea" placeholder="Fasilitas terpadu berstandar internasional..."></textarea>
            </div>
            <div class="cms-field-group" style="margin-bottom:0;">
                <label class="cms-label">Subtitle / Deskripsi Fasilitas <span class="cms-lang-tag cms-lang-tag-en">EN</span></label>
                <textarea wire:model="heroSubtitleEn" class="cms-textarea" placeholder="Kosongkan = pakai teks ID di samping"></textarea>
            </div>
        </div>
        <div class="cms-hint" style="margin-top:0.4rem;">Boleh sisipkan <code>{court_count}</code> di mana pun (ID maupun EN) — otomatis diganti angka dari field "Jumlah Lapangan" di bawah.</div>
    </div>

    {{-- KARTU 2: FAKTA VENUE (DIPAKAI ULANG DI 2 HALAMAN) --}}
    <div class="adm-card">
        <div class="adm-card-head">
            <div>
                <div class="adm-card-title">2. Fakta Venue</div>
                <div class="adm-card-sub">Dipakai ulang persis sama di halaman depan DAN panel kiri halaman login — sumbernya cuma satu di sini.</div>
            </div>
        </div>

        <div class="cms-grid-2">
            <div class="cms-field-group">
                <label class="cms-label">Jumlah Lapangan</label>
                <input type="number" min="1" wire:model="courtCount" class="cms-input" placeholder="3">
            </div>
            <div class="cms-field-group">
                <label class="cms-label">Jam Operasional <span class="cms-lang-tag cms-lang-tag-id">ID</span></label>
                <input type="text" wire:model="operatingHoursText" class="cms-input" placeholder="Open 06:00 – 23:00">
            </div>
        </div>

        <div class="cms-field-group">
            <label class="cms-label">Jam Operasional <span class="cms-lang-tag cms-lang-tag-en">EN</span></label>
            <input type="text" wire:model="operatingHoursTextEn" class="cms-input" placeholder="Kosongkan = pakai teks ID di atas">
        </div>

        <div class="cms-grid-2">
            <div class="cms-field-group">
                <label class="cms-label">Alamat</label>
                <input type="text" wire:model="addressLine" class="cms-input" placeholder="Gedung Indosat, Jl. ...">
            </div>
            <div class="cms-field-group">
                <label class="cms-label">Domain Portal (tampil di panel login)</label>
                <input type="text" wire:model="portalDomainText" class="cms-input" placeholder="portal.club61padel.com">
            </div>
        </div>
    </div>

    {{-- KARTU 3: KARTU FASILITAS (REPEATER) --}}
    <div class="adm-card">
        <div class="adm-card-head">
            <div>
                <div class="adm-card-title">3. Kartu Fasilitas Ringkas (Hero &amp; Panel Login)</div>
                <div class="adm-card-sub">Kartu kecil ber-ikon, muncul di kedua halaman. Urutan di sini = urutan tampil. Maksimal 8 kartu.</div>
            </div>
        </div>

        @foreach($facilityCards as $index => $card)
            <div class="cms-card-item" wire:key="facility-card-{{ $index }}">
                <div class="cms-card-item-head">
                    <span style="font-size:0.6875rem; font-weight:800; color:#8C6418; text-transform:uppercase;">Kartu #{{ $index + 1 }}</span>
                    <div style="display:flex; gap:0.4rem;">
                        <button type="button" class="cms-icon-btn" wire:click="moveFacilityCardUp({{ $index }})" title="Naikkan urutan">↑</button>
                        <button type="button" class="cms-icon-btn" wire:click="moveFacilityCardDown({{ $index }})" title="Turunkan urutan">↓</button>
                        <button type="button" class="cms-icon-btn danger" wire:click="removeFacilityCard({{ $index }})" title="Hapus kartu">&times;</button>
                    </div>
                </div>

                <div class="cms-field-group" style="margin-bottom:0.6rem;">
                    <label class="cms-label">Ikon</label>
                    <select wire:model="facilityCards.{{ $index }}.icon_key" class="cms-select">
                        @foreach(\App\Models\Setting\CompanyProfileSetting::ALLOWED_ICON_KEYS as $iconKey)
                            <option value="{{ $iconKey }}">{{ ucfirst($iconKey) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="cms-lang-pair">
                    <div class="cms-field-group" style="margin-bottom:0;">
                        <label class="cms-label">Judul <span class="cms-lang-tag cms-lang-tag-id">ID</span></label>
                        <input type="text" wire:model="facilityCards.{{ $index }}.title" class="cms-input" placeholder="Padel Arena" maxlength="40">
                    </div>
                    <div class="cms-field-group" style="margin-bottom:0;">
                        <label class="cms-label">Judul <span class="cms-lang-tag cms-lang-tag-en">EN</span></label>
                        <input type="text" wire:model="facilityCards.{{ $index }}.title_en" class="cms-input" placeholder="Kosongkan = pakai ID" maxlength="40">
                    </div>
                </div>
                <div class="cms-lang-pair" style="margin-top:0.6rem;">
                    <div class="cms-field-group" style="margin-bottom:0;">
                        <label class="cms-label">Sub-teks <span class="cms-lang-tag cms-lang-tag-id">ID</span></label>
                        <input type="text" wire:model="facilityCards.{{ $index }}.subtitle" class="cms-input" placeholder="+ Panoramic Courts" maxlength="60">
                    </div>
                    <div class="cms-field-group" style="margin-bottom:0;">
                        <label class="cms-label">Sub-teks <span class="cms-lang-tag cms-lang-tag-en">EN</span></label>
                        <input type="text" wire:model="facilityCards.{{ $index }}.subtitle_en" class="cms-input" placeholder="Kosongkan = pakai ID" maxlength="60">
                    </div>
                </div>
            </div>
        @endforeach

        <button type="button" class="cms-add-btn" wire:click="addFacilityCard">+ Tambah Kartu Fasilitas</button>
    </div>

    {{-- KARTU 4: FACILITIES SHOWCASE (DENGAN FOTO) --}}
    <div class="adm-card">
        <div class="adm-card-head">
            <div>
                <div class="adm-card-title">4. Facilities Showcase (Detail + Foto)</div>
                <div class="adm-card-sub">Section lebih besar di halaman depan, tiap fasilitas boleh punya foto &amp; daftar amenity. Maksimal 6.</div>
            </div>
        </div>

        @foreach($facilities as $index => $facility)
            <div class="cms-card-item" wire:key="facility-{{ $facility['id'] ?? 'new-'.$index }}">
                <div class="cms-card-item-head">
                    <span style="font-size:0.6875rem; font-weight:800; color:#8C6418; text-transform:uppercase;">Fasilitas #{{ $index + 1 }}</span>
                    <div style="display:flex; gap:0.4rem;">
                        <button type="button" class="cms-icon-btn" wire:click="moveFacilityUp({{ $index }})" title="Naikkan urutan">↑</button>
                        <button type="button" class="cms-icon-btn" wire:click="moveFacilityDown({{ $index }})" title="Turunkan urutan">↓</button>
                        <button type="button" class="cms-icon-btn danger" wire:click="removeFacility({{ $index }})" title="Hapus fasilitas">&times;</button>
                    </div>
                </div>

                <div class="cms-lang-pair">
                    <div class="cms-field-group" style="margin-bottom:0;">
                        <label class="cms-label">Judul <span class="cms-lang-tag cms-lang-tag-id">ID</span></label>
                        <input type="text" wire:model="facilities.{{ $index }}.title" class="cms-input" placeholder="Padel Arena" maxlength="80">
                    </div>
                    <div class="cms-field-group" style="margin-bottom:0;">
                        <label class="cms-label">Judul <span class="cms-lang-tag cms-lang-tag-en">EN</span></label>
                        <input type="text" wire:model="facilities.{{ $index }}.title_en" class="cms-input" placeholder="Kosongkan = pakai ID" maxlength="80">
                    </div>
                </div>

                <div class="cms-field-group" style="margin-top:0.6rem;">
                    <label class="cms-label">Foto (JPG/PNG/WEBP, maks 2MB)</label>
                    <input type="file" wire:model="facilityUploads.{{ $index }}" class="cms-input" accept="image/png,image/jpeg,image/webp">
                    @if($facility['photo_path'] ?? null)
                        <div style="display:flex; align-items:center; gap:0.5rem; margin-top:0.4rem;">
                            <img src="{{ '/storage/'.$facility['photo_path'] }}" style="width:48px; height:48px; object-fit:cover; border-radius:8px; border:1.5px solid #DFC387;">
                            <button type="button" class="cms-icon-btn danger" wire:click="removeFacilityPhoto({{ $index }})" title="Hapus foto ini">&times;</button>
                        </div>
                    @endif
                    @error("facilityUploads.{$index}") <div class="cms-hint" style="color:#B42318;">{{ $message }}</div> @enderror
                </div>

                <div class="cms-lang-pair" style="margin-top:0.6rem;">
                    <div class="cms-field-group" style="margin-bottom:0;">
                        <label class="cms-label">Deskripsi Singkat <span class="cms-lang-tag cms-lang-tag-id">ID</span></label>
                        <textarea wire:model="facilities.{{ $index }}.description" class="cms-textarea" style="min-height:60px;" placeholder="Lapangan padel panoramic full indoor ber-AC..."></textarea>
                    </div>
                    <div class="cms-field-group" style="margin-bottom:0;">
                        <label class="cms-label">Deskripsi Singkat <span class="cms-lang-tag cms-lang-tag-en">EN</span></label>
                        <textarea wire:model="facilities.{{ $index }}.description_en" class="cms-textarea" style="min-height:60px;" placeholder="Kosongkan = pakai teks ID"></textarea>
                    </div>
                </div>

                <div class="cms-lang-pair" style="margin-top:0.6rem;">
                    <div class="cms-field-group" style="margin-bottom:0;">
                        <label class="cms-label">Daftar Amenity (1 baris = 1 item) <span class="cms-lang-tag cms-lang-tag-id">ID</span></label>
                        <textarea wire:model="facilities.{{ $index }}.amenities_text" class="cms-textarea" style="min-height:70px;" placeholder="Lantai profesional&#10;Pencahayaan LED&#10;Kaca panoramic"></textarea>
                    </div>
                    <div class="cms-field-group" style="margin-bottom:0;">
                        <label class="cms-label">Daftar Amenity <span class="cms-lang-tag cms-lang-tag-en">EN</span></label>
                        <textarea wire:model="facilities.{{ $index }}.amenities_en_text" class="cms-textarea" style="min-height:70px;" placeholder="Kosongkan = pakai daftar ID"></textarea>
                    </div>
                </div>
            </div>
        @endforeach

        <button type="button" class="cms-add-btn" wire:click="addFacility">+ Tambah Fasilitas</button>
    </div>

    {{-- KARTU 5: KENAPA PILIH CLUB61 --}}
    <div class="adm-card">
        <div class="adm-card-head">
            <div>
                <div class="adm-card-title">5. Kenapa Pilih Club61</div>
                <div class="adm-card-sub">Grid keunggulan (ikon + judul singkat). Maksimal 6 kotak.</div>
            </div>
        </div>

        @foreach($valueProps as $index => $prop)
            <div class="cms-card-item" wire:key="value-prop-{{ $prop['id'] ?? 'new-'.$index }}">
                <div class="cms-card-item-head">
                    <span style="font-size:0.6875rem; font-weight:800; color:#8C6418; text-transform:uppercase;">Poin #{{ $index + 1 }}</span>
                    <div style="display:flex; gap:0.4rem;">
                        <button type="button" class="cms-icon-btn" wire:click="moveValuePropUp({{ $index }})" title="Naikkan urutan">↑</button>
                        <button type="button" class="cms-icon-btn" wire:click="moveValuePropDown({{ $index }})" title="Turunkan urutan">↓</button>
                        <button type="button" class="cms-icon-btn danger" wire:click="removeValueProp({{ $index }})" title="Hapus poin">&times;</button>
                    </div>
                </div>

                <div class="cms-field-group" style="margin-bottom:0.6rem;">
                    <label class="cms-label">Ikon</label>
                    <select wire:model="valueProps.{{ $index }}.icon_key" class="cms-select">
                        @foreach(\App\Models\Setting\CompanyProfileValueProp::ALLOWED_ICON_KEYS as $iconKey)
                            <option value="{{ $iconKey }}">{{ ucfirst($iconKey) }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="cms-lang-pair">
                    <div class="cms-field-group" style="margin-bottom:0;">
                        <label class="cms-label">Judul <span class="cms-lang-tag cms-lang-tag-id">ID</span></label>
                        <input type="text" wire:model="valueProps.{{ $index }}.title" class="cms-input" placeholder="Booking Online Real-Time" maxlength="60">
                    </div>
                    <div class="cms-field-group" style="margin-bottom:0;">
                        <label class="cms-label">Judul <span class="cms-lang-tag cms-lang-tag-en">EN</span></label>
                        <input type="text" wire:model="valueProps.{{ $index }}.title_en" class="cms-input" placeholder="Kosongkan = pakai ID" maxlength="60">
                    </div>
                </div>
                <div class="cms-lang-pair" style="margin-top:0.6rem;">
                    <div class="cms-field-group" style="margin-bottom:0;">
                        <label class="cms-label">Deskripsi Singkat <span class="cms-lang-tag cms-lang-tag-id">ID</span></label>
                        <input type="text" wire:model="valueProps.{{ $index }}.description" class="cms-input" placeholder="Cek slot & bayar langsung dari HP" maxlength="150">
                    </div>
                    <div class="cms-field-group" style="margin-bottom:0;">
                        <label class="cms-label">Deskripsi Singkat <span class="cms-lang-tag cms-lang-tag-en">EN</span></label>
                        <input type="text" wire:model="valueProps.{{ $index }}.description_en" class="cms-input" placeholder="Kosongkan = pakai ID" maxlength="150">
                    </div>
                </div>
            </div>
        @endforeach

        <button type="button" class="cms-add-btn" wire:click="addValueProp">+ Tambah Poin</button>
    </div>

    {{-- KARTU 6: LOKASI & KONTAK --}}
    <div class="adm-card">
        <div class="adm-card-head">
            <div>
                <div class="adm-card-title">6. Lokasi &amp; Kontak</div>
                <div class="adm-card-sub">Tombol WhatsApp &amp; peta di halaman depan.</div>
            </div>
        </div>

        <div class="cms-grid-2">
            <div class="cms-field-group">
                <label class="cms-label">Nomor WhatsApp (format: 62812xxxxxxx)</label>
                <input type="text" wire:model="whatsappNumber" class="cms-input" placeholder="6281234567890">
            </div>
            <div class="cms-field-group">
                <label class="cms-label">URL Embed Google Maps (opsional)</label>
                <input type="text" wire:model="mapsEmbedUrl" class="cms-input" placeholder="https://www.google.com/maps/embed?...">
            </div>
        </div>
    </div>

    {{-- KARTU 7: FOOTER --}}
    <div class="adm-card">
        <div class="adm-card-head">
            <div>
                <div class="adm-card-title">7. Footer</div>
                <div class="adm-card-sub">Tagline singkat &amp; tautan sosial media.</div>
            </div>
        </div>

        <div class="cms-lang-pair" style="margin-bottom:1rem;">
            <div class="cms-field-group" style="margin-bottom:0;">
                <label class="cms-label">Tagline Footer <span class="cms-lang-tag cms-lang-tag-id">ID</span></label>
                <input type="text" wire:model="footerTagline" class="cms-input" placeholder="Sanctuary padel premium di jantung kota Medan.">
            </div>
            <div class="cms-field-group" style="margin-bottom:0;">
                <label class="cms-label">Tagline Footer <span class="cms-lang-tag cms-lang-tag-en">EN</span></label>
                <input type="text" wire:model="footerTaglineEn" class="cms-input" placeholder="Kosongkan = pakai teks ID di samping">
            </div>
        </div>

        <div class="cms-grid-3">
            <div class="cms-field-group" style="margin-bottom:0;">
                <label class="cms-label">Instagram (URL)</label>
                <input type="text" wire:model="footerInstagram" class="cms-input" placeholder="https://instagram.com/club61">
            </div>
            <div class="cms-field-group" style="margin-bottom:0;">
                <label class="cms-label">Facebook (URL)</label>
                <input type="text" wire:model="footerFacebook" class="cms-input" placeholder="https://facebook.com/club61">
            </div>
            <div class="cms-field-group" style="margin-bottom:0;">
                <label class="cms-label">TikTok (URL)</label>
                <input type="text" wire:model="footerTiktok" class="cms-input" placeholder="https://tiktok.com/@club61">
            </div>
        </div>
    </div>

    <div style="display:flex; justify-content:flex-end;">
        <button type="button" wire:click="save" class="cms-save-btn">
            <span>Simpan Perubahan</span>
        </button>
    </div>
</div>
