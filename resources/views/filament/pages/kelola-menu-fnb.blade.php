<div class="adm-wrap">
    <style>
        .fnb-tabs { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 1.5rem; border-bottom: 2px solid #E5E7EB; padding-bottom: 0.5rem; flex-wrap: wrap; }
        .fnb-tab-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.65rem 1.25rem; border-radius: 12px; font-size: 0.875rem; font-weight: 800; color: #6B7280; background: transparent; border: 1.5px solid transparent; cursor: pointer; transition: all 0.15s ease; }
        .fnb-tab-btn:hover { color: #8C6418; background: #FAF5E8; }
        .fnb-tab-btn.active { color: #281A05; background: #FAF2DE; border-color: #D4AF37; box-shadow: 0 4px 12px rgba(212, 175, 55, 0.15); }
        .fnb-card { background: rgba(255, 255, 255, 0.98); border: 1.5px solid #DFC387; border-radius: 18px; box-shadow: 0 10px 30px -10px rgba(160, 120, 30, 0.12); padding: 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; margin-bottom: 1.5rem; }
        .fnb-card-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 1rem; padding-bottom: 1rem; border-bottom: 1.5px solid #F3E8CE; }
        .fnb-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 0.875rem; }
        .fnb-table th { background: #FAF5E8; color: #5C410F; font-weight: 800; text-transform: uppercase; font-size: 0.725rem; letter-spacing: 0.05em; padding: 0.85rem 1rem; border-top: 1px solid #E5E7EB; border-bottom: 1.5px solid #DFC387; text-align: left; }
        .fnb-table td { padding: 1rem; border-bottom: 1px solid #F3F4F6; vertical-align: middle; color: #1F170D; }
        .fnb-table tr:hover td { background-color: #FFFDF9; }
        .fnb-badge { display: inline-block; padding: 0.2rem 0.6rem; border-radius: 6px; font-size: 0.7rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.04em; }
        .fnb-badge-bar { background: #E0F2FE; color: #0369A1; border: 1px solid #BAE6FD; }
        .fnb-badge-kitchen { background: #FEF3C7; color: #92400E; border: 1px solid #FCD34D; }
        .fnb-badge-cat { background: #F3E8FF; color: #7E22CE; border: 1px solid #E9D5FF; }
        .fnb-btn-gold { padding: 0.65rem 1.25rem; border-radius: 10px; background: linear-gradient(180deg, #F0DB9D 0%, #D4AF37 35%, #B38622 100%); border: 1px solid #FBF0CE; color: #281A05; font-weight: 800; font-size: 0.8125rem; cursor: pointer; box-shadow: 0 4px 12px rgba(184, 134, 11, 0.25); transition: all 0.15s ease; display: inline-flex; align-items: center; gap: 0.35rem; }
        .fnb-btn-gold:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(184, 134, 11, 0.35); }
        .fnb-btn-action { padding: 0.4rem 0.75rem; border-radius: 7px; font-size: 0.75rem; font-weight: 800; cursor: pointer; transition: all 0.1s; border: 1px solid transparent; display: inline-flex; align-items: center; gap: 0.25rem; }
        .fnb-btn-edit { background: #FAF2DE; border-color: #D4AF37; color: #7A5818; }
        .fnb-btn-edit:hover { background: #F3E5BE; }
        .fnb-btn-danger { background: #FEE2E2; border-color: #FCA5A5; color: #991B1B; }
        .fnb-btn-danger:hover { background: #FECACA; }
        .fnb-switch { position: relative; width: 44px; height: 24px; background: #E5E7EB; border-radius: 9999px; transition: background-color 0.2s ease; border: 1px solid #D1D5DB; cursor: pointer; display: inline-block; }
        .fnb-switch.on { background: #16A34A; border-color: #15803D; }
        .fnb-switch-knob { position: absolute; top: 1px; left: 1px; width: 20px; height: 20px; background: #FFFFFF; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,0.2); transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
        .fnb-switch.on .fnb-switch-knob { transform: translateX(20px); }
        .fnb-modal-backdrop { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.65); backdrop-filter: blur(6px); z-index: 9999; display: flex; align-items: center; justify-content: center; padding: 1.5rem; overflow-y: auto; }
        .fnb-modal-dialog { background: #FFFFFF; border: 2px solid #D4AF37; border-radius: 20px; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35); width: 100%; max-width: 640px; padding: 1.75rem; display: flex; flex-direction: column; gap: 1.25rem; max-height: 90vh; overflow-y: auto; }
        .fnb-modal-dialog.fnb-modal-sm { max-width: 480px; }
        .fnb-form-group { display: flex; flex-direction: column; gap: 0.35rem; }
        .fnb-form-label { font-size: 0.775rem; font-weight: 800; color: #374151; text-transform: uppercase; letter-spacing: 0.04em; }
        .fnb-form-input, .fnb-form-select, .fnb-form-textarea { width: 100%; padding: 0.65rem 0.85rem; border-radius: 9px; border: 1.5px solid #D1D5DB; font-size: 0.875rem; color: #1F170D; background: #FAF9F6; outline: none; transition: border-color 0.15s; }
        .fnb-form-input:focus, .fnb-form-select:focus, .fnb-form-textarea:focus { border-color: #D4AF37; background: #FFFFFF; }
        .fnb-form-textarea { resize: vertical; min-height: 70px; }
        .fnb-option-row { display: grid; grid-template-columns: 1fr 160px 32px; gap: 0.6rem; align-items: start; }
        .fnb-error { font-size: 0.75rem; color: #DC2626; }
    </style>

    {{-- HEADER BANNER --}}
    <div class="adm-banner">
        <div>
            <div class="adm-pill adm-pill-gold" style="margin-bottom:0.4rem;">
                <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background-color:#D4AF37;"></span>
                <span>F&amp;B Cafe &amp; Kitchen &bull; Katalog Menu</span>
            </div>
            <div class="adm-banner-title">Kelola Menu F&amp;B &amp; Tambahan</div>
            <div class="adm-banner-sub">
                Kategori, menu makanan/minuman (lengkap dengan foto), dan grup tambahan/modifier (mis. pilihan susu, level gula) — semuanya di 1 halaman ini, dipakai bareng sumber data yang sama.
            </div>
        </div>

        <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
            @if($activeTab === 'categories')
                <button type="button" wire:click="openCreateCategoryModal" class="fnb-btn-gold">+ Tambah Kategori</button>
            @elseif($activeTab === 'menus')
                <button type="button" wire:click="openCreateMenuModal" class="fnb-btn-gold">+ Tambah Menu</button>
            @else
                <button type="button" wire:click="openCreateModifierGroupModal" class="fnb-btn-gold">+ Tambah Grup Tambahan</button>
            @endif
        </div>
    </div>

    {{-- NAVIGASI TAB --}}
    <div class="fnb-tabs">
        <button type="button" wire:click="requestTabChange('categories')" class="fnb-tab-btn {{ $activeTab === 'categories' ? 'active' : '' }}">Kategori Menu</button>
        <button type="button" wire:click="requestTabChange('menus')" class="fnb-tab-btn {{ $activeTab === 'menus' ? 'active' : '' }}">Menu F&amp;B</button>
        <button type="button" wire:click="requestTabChange('modifiers')" class="fnb-tab-btn {{ $activeTab === 'modifiers' ? 'active' : '' }}">Tambahan / Modifier</button>
    </div>

    {{-- ================= TAB 1: KATEGORI ================= --}}
    @if($activeTab === 'categories')
        <div class="fnb-card">
            <div class="fnb-card-head">
                <div>
                    <div style="font-family:var(--font-serif); font-size:1.15rem; font-weight:800; color:#1F170D;">Daftar Kategori Menu</div>
                    <div style="font-size:0.75rem; color:#7A643E; margin-top:0.2rem;">Urutan di sini menentukan urutan kategori di layar kasir POS.</div>
                </div>
                <span class="adm-pill adm-pill-gold">Total: {{ $this->categories->count() }} Kategori</span>
            </div>

            <div style="overflow-x:auto;">
                <table class="fnb-table">
                    <thead>
                        <tr>
                            <th>Nama Kategori</th>
                            <th>Urutan</th>
                            <th>Jumlah Menu</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->categories as $category)
                            <tr>
                                <td style="font-weight:800;">{{ $category->name }}</td>
                                <td>{{ $category->sort_order }}</td>
                                <td>{{ $category->menus_count }}</td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex; gap:0.35rem;">
                                        <button type="button" wire:click="openEditCategoryModal('{{ $category->id }}')" class="fnb-btn-action fnb-btn-edit">Edit</button>
                                        @if($category->menus_count > 0)
                                            <button type="button" class="fnb-btn-action" style="background:#F3F4F6; color:#9CA3AF; cursor:not-allowed;" title="Masih dipakai {{ $category->menus_count }} menu" disabled>Hapus</button>
                                        @else
                                            <button type="button" wire:click="deleteCategory('{{ $category->id }}')" wire:confirm="Yakin ingin menghapus kategori ini?" class="fnb-btn-action fnb-btn-danger">Hapus</button>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" style="text-align:center; padding:2.5rem; color:#6B7280;">Belum ada kategori. Klik "+ Tambah Kategori".</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ================= TAB 2: MENU F&B ================= --}}
    @if($activeTab === 'menus')
        <div class="fnb-card">
            <div class="fnb-card-head">
                <div>
                    <div style="font-family:var(--font-serif); font-size:1.15rem; font-weight:800; color:#1F170D;">Katalog Menu F&amp;B</div>
                    <div style="font-size:0.75rem; color:#7A643E; margin-top:0.2rem;">{{ $this->menus->count() }} menu ditampilkan.</div>
                </div>
                <div style="display:flex; align-items:center; gap:0.5rem; flex-wrap:wrap;">
                    <input type="text" wire:model.live.debounce.400ms="menuSearch" class="fnb-form-input" style="width:220px;" placeholder="Cari nama menu...">
                    <select wire:model.live="menuCategoryFilter" class="fnb-form-select" style="width:180px;">
                        <option value="ALL">Semua Kategori</option>
                        @foreach($this->categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div style="overflow-x:auto;">
                <table class="fnb-table">
                    <thead>
                        <tr>
                            <th>Foto</th>
                            <th>Nama Menu</th>
                            <th>Kategori</th>
                            <th>Stasiun</th>
                            <th>Harga</th>
                            <th>Tambahan</th>
                            <th style="text-align:center;">Tersedia</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->menus as $menu)
                            <tr>
                                <td>
                                    @if($menu->image_url)
                                        <img src="{{ '/storage/'.$menu->image_url }}" style="width:44px; height:44px; object-fit:cover; border-radius:8px; border:1.5px solid #DFC387;">
                                    @else
                                        <div style="width:44px; height:44px; border-radius:8px; background:#FAF5E8; border:1.5px dashed #DFC387;"></div>
                                    @endif
                                </td>
                                <td style="font-weight:800;">{{ $menu->name }}</td>
                                <td><span class="fnb-badge fnb-badge-cat">{{ $menu->category?->name }}</span></td>
                                <td><span class="fnb-badge {{ $menu->station === 'KITCHEN' ? 'fnb-badge-kitchen' : 'fnb-badge-bar' }}">{{ $menu->station }}</span></td>
                                <td style="font-weight:800; color:#8C6418;">Rp {{ number_format($menu->base_price, 0, ',', '.') }}</td>
                                <td style="font-size:0.75rem; color:#6B7280;">{{ $menu->modifierGroups->pluck('name')->implode(', ') ?: '-' }}</td>
                                <td style="text-align:center;">
                                    <div style="display:inline-flex; align-items:center; gap:0.5rem;">
                                        <div class="fnb-switch {{ $menu->is_available ? 'on' : '' }}"><div class="fnb-switch-knob"></div></div>
                                    </div>
                                </td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex; gap:0.35rem;">
                                        <button type="button" wire:click="openEditMenuModal('{{ $menu->id }}')" class="fnb-btn-action fnb-btn-edit">Edit</button>
                                        <button type="button" wire:click="deleteMenu('{{ $menu->id }}')" wire:confirm="Yakin ingin menghapus menu ini?" class="fnb-btn-action fnb-btn-danger">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="8" style="text-align:center; padding:2.5rem; color:#6B7280;">Belum ada menu. Klik "+ Tambah Menu".</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ================= TAB 3: TAMBAHAN / MODIFIER ================= --}}
    @if($activeTab === 'modifiers')
        <div class="fnb-card">
            <div class="fnb-card-head">
                <div>
                    <div style="font-family:var(--font-serif); font-size:1.15rem; font-weight:800; color:#1F170D;">Grup Tambahan / Modifier</div>
                    <div style="font-size:0.75rem; color:#7A643E; margin-top:0.2rem;">Contoh: "Milk Option", "Sugar Level". Dipasangkan ke menu lewat form Menu di tab sebelah.</div>
                </div>
                <span class="adm-pill adm-pill-gold">Total: {{ $this->modifierGroups->count() }} Grup</span>
            </div>

            <div style="overflow-x:auto;">
                <table class="fnb-table">
                    <thead>
                        <tr>
                            <th>Nama Grup</th>
                            <th>Wajib?</th>
                            <th>Maks. Pilihan</th>
                            <th>Jumlah Opsi</th>
                            <th>Dipakai di Menu</th>
                            <th style="text-align:right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($this->modifierGroups as $group)
                            <tr>
                                <td style="font-weight:800;">{{ $group->name }}</td>
                                <td>{{ $group->is_required ? 'Ya' : 'Tidak' }}</td>
                                <td>{{ $group->max_selection }}</td>
                                <td>{{ $group->options_count }}</td>
                                <td>{{ $group->menus_count }}</td>
                                <td style="text-align:right;">
                                    <div style="display:inline-flex; gap:0.35rem;">
                                        <button type="button" wire:click="openEditModifierGroupModal('{{ $group->id }}')" class="fnb-btn-action fnb-btn-edit">Edit</button>
                                        <button type="button" wire:click="deleteModifierGroup('{{ $group->id }}')" wire:confirm="Menghapus grup ini akan melepas keterkaitannya dari semua menu yang memakainya. Lanjutkan?" class="fnb-btn-action fnb-btn-danger">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="text-align:center; padding:2.5rem; color:#6B7280;">Belum ada grup tambahan. Klik "+ Tambah Grup Tambahan".</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ================= MODAL: KATEGORI ================= --}}
    @if($showCategoryModal)
        <div class="fnb-modal-backdrop">
            <div class="fnb-modal-dialog fnb-modal-sm">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid #F3E8CE; padding-bottom:0.75rem;">
                    <div style="font-family:var(--font-serif); font-size:1.15rem; font-weight:800;">{{ $editingCategoryId ? 'Edit Kategori' : 'Tambah Kategori' }}</div>
                    <button type="button" wire:click="closeCategoryModal" style="background:none; border:none; font-size:1.25rem; font-weight:800; color:#9CA3AF; cursor:pointer;">&times;</button>
                </div>

                <div class="fnb-form-group">
                    <label class="fnb-form-label">Nama Kategori</label>
                    <input type="text" wire:model="categoryName" class="fnb-form-input" placeholder="Contoh: Coffee & Drinks">
                    @error('categoryName') <span class="fnb-error">{{ $message }}</span> @enderror
                </div>

                <div class="fnb-form-group">
                    <label class="fnb-form-label">Urutan Tampil</label>
                    <input type="number" wire:model="categorySortOrder" class="fnb-form-input">
                    @error('categorySortOrder') <span class="fnb-error">{{ $message }}</span> @enderror
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.75rem; border-top:1.5px solid #F3E8CE; padding-top:1rem;">
                    <button type="button" wire:click="closeCategoryModal" class="fnb-btn-action" style="background:#F3F4F6; color:#4B5563;">Batal</button>
                    <button type="button" wire:click="saveCategory" class="fnb-btn-gold">Simpan Kategori</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ================= MODAL: MENU ================= --}}
    @if($showMenuModal)
        <div class="fnb-modal-backdrop">
            <div class="fnb-modal-dialog">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid #F3E8CE; padding-bottom:0.75rem;">
                    <div style="font-family:var(--font-serif); font-size:1.15rem; font-weight:800;">{{ $editingMenuId ? 'Edit Menu' : 'Tambah Menu' }}</div>
                    <button type="button" wire:click="closeMenuModal" style="background:none; border:none; font-size:1.25rem; font-weight:800; color:#9CA3AF; cursor:pointer;">&times;</button>
                </div>

                <div style="display:flex; flex-direction:column; gap:1rem;">
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="fnb-form-group">
                            <label class="fnb-form-label">Kategori</label>
                            <select wire:model="menuCategoryId" class="fnb-form-select">
                                @foreach($this->categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @error('menuCategoryId') <span class="fnb-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="fnb-form-group">
                            <label class="fnb-form-label">Nama Menu</label>
                            <input type="text" wire:model="menuName" class="fnb-form-input" placeholder="Iced Spanish Latte">
                            @error('menuName') <span class="fnb-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="fnb-form-group">
                        <label class="fnb-form-label">Deskripsi</label>
                        <textarea wire:model="menuDescription" class="fnb-form-textarea" placeholder="Espresso double shot dengan susu segar dingin."></textarea>
                        @error('menuDescription') <span class="fnb-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="fnb-form-group">
                        <label class="fnb-form-label">Foto Menu (JPG/PNG/WEBP, maks 2MB)</label>
                        <input type="file" wire:model="menuPhotoUpload" class="fnb-form-input" accept="image/png,image/jpeg,image/webp">
                        @if($existingMenuPhotoPath && ! $removeMenuPhoto)
                            <div style="display:flex; align-items:center; gap:0.5rem; margin-top:0.4rem;">
                                <img src="{{ '/storage/'.$existingMenuPhotoPath }}" style="width:48px; height:48px; object-fit:cover; border-radius:8px; border:1.5px solid #DFC387;">
                                <button type="button" wire:click="removeMenuPhotoNow" class="fnb-btn-action fnb-btn-danger">Hapus Foto</button>
                            </div>
                        @endif
                        @error('menuPhotoUpload') <span class="fnb-error">{{ $message }}</span> @enderror
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="fnb-form-group">
                            <label class="fnb-form-label">Harga Dasar (Rp)</label>
                            <input type="number" wire:model="menuBasePrice" class="fnb-form-input" placeholder="38000">
                            @error('menuBasePrice') <span class="fnb-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="fnb-form-group">
                            <label class="fnb-form-label">Stasiun Produksi</label>
                            <select wire:model="menuStation" class="fnb-form-select">
                                <option value="BAR">Bar (Minuman)</option>
                                <option value="KITCHEN">Kitchen (Makanan)</option>
                            </select>
                        </div>
                    </div>

                    <div class="fnb-form-group">
                        <label class="fnb-form-label">Status</label>
                        <div style="display:flex; align-items:center; gap:1rem;">
                            <label style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.875rem; font-weight:700; cursor:pointer;">
                                <input type="radio" wire:model="menuIsAvailable" value="1">
                                <span style="color:#15803D;">Tersedia untuk Dijual</span>
                            </label>
                            <label style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.875rem; font-weight:700; cursor:pointer;">
                                <input type="radio" wire:model="menuIsAvailable" value="0">
                                <span style="color:#6B7280;">Habis / Disembunyikan</span>
                            </label>
                        </div>
                    </div>

                    <div class="fnb-form-group">
                        <label class="fnb-form-label">Grup Tambahan / Modifier (Opsional)</label>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                            @forelse($this->modifierGroups as $group)
                                <label style="display:inline-flex; align-items:center; gap:0.4rem; font-size:0.8125rem; font-weight:600; cursor:pointer;">
                                    <input type="checkbox" wire:model="menuModifierGroupIds" value="{{ $group->id }}">
                                    <span>{{ $group->name }}</span>
                                </label>
                            @empty
                                <span style="font-size:0.75rem; color:#9CA3AF;">Belum ada grup tambahan — buat dulu di tab "Tambahan / Modifier".</span>
                            @endforelse
                        </div>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.75rem; border-top:1.5px solid #F3E8CE; padding-top:1rem;">
                    <button type="button" wire:click="closeMenuModal" class="fnb-btn-action" style="background:#F3F4F6; color:#4B5563;">Batal</button>
                    <button type="button" wire:click="saveMenu" class="fnb-btn-gold">Simpan Menu</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ================= MODAL: GRUP TAMBAHAN / MODIFIER ================= --}}
    @if($showModifierGroupModal)
        <div class="fnb-modal-backdrop">
            <div class="fnb-modal-dialog">
                <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1.5px solid #F3E8CE; padding-bottom:0.75rem;">
                    <div style="font-family:var(--font-serif); font-size:1.15rem; font-weight:800;">{{ $editingModifierGroupId ? 'Edit Grup Tambahan' : 'Tambah Grup Tambahan' }}</div>
                    <button type="button" wire:click="closeModifierGroupModal" style="background:none; border:none; font-size:1.25rem; font-weight:800; color:#9CA3AF; cursor:pointer;">&times;</button>
                </div>

                <div style="display:flex; flex-direction:column; gap:1rem;">
                    <div class="fnb-form-group">
                        <label class="fnb-form-label">Nama Grup</label>
                        <input type="text" wire:model="modifierGroupName" class="fnb-form-input" placeholder="Milk Option">
                        @error('modifierGroupName') <span class="fnb-error">{{ $message }}</span> @enderror
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem;">
                        <div class="fnb-form-group">
                            <label class="fnb-form-label">Wajib Dipilih Customer?</label>
                            <select wire:model="modifierGroupIsRequired" class="fnb-form-select">
                                <option value="0">Tidak</option>
                                <option value="1">Ya</option>
                            </select>
                        </div>
                        <div class="fnb-form-group">
                            <label class="fnb-form-label">Maksimal Opsi Dipilih</label>
                            <input type="number" wire:model="modifierGroupMaxSelection" class="fnb-form-input" min="1">
                            @error('modifierGroupMaxSelection') <span class="fnb-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="fnb-form-group">
                        <label class="fnb-form-label">Daftar Opsi</label>
                        <div style="display:flex; flex-direction:column; gap:0.5rem;">
                            @foreach($modifierOptions as $index => $option)
                                <div class="fnb-option-row" wire:key="modifier-option-{{ $index }}">
                                    <div>
                                        <input type="text" wire:model="modifierOptions.{{ $index }}.name" class="fnb-form-input" placeholder="Contoh: Oat Milk">
                                        @error("modifierOptions.{$index}.name") <span class="fnb-error">{{ $message }}</span> @enderror
                                    </div>
                                    <div>
                                        <input type="number" wire:model="modifierOptions.{{ $index }}.extra_price" class="fnb-form-input" placeholder="Harga tambahan">
                                    </div>
                                    <button type="button" wire:click="removeModifierOptionRow({{ $index }})" class="fnb-btn-action fnb-btn-danger" style="height:fit-content;">&times;</button>
                                </div>
                            @endforeach
                        </div>
                        <button type="button" wire:click="addModifierOptionRow" class="fnb-btn-action fnb-btn-edit" style="margin-top:0.25rem; align-self:flex-start;">+ Tambah Opsi</button>
                    </div>
                </div>

                <div style="display:flex; justify-content:flex-end; gap:0.75rem; border-top:1.5px solid #F3E8CE; padding-top:1rem;">
                    <button type="button" wire:click="closeModifierGroupModal" class="fnb-btn-action" style="background:#F3F4F6; color:#4B5563;">Batal</button>
                    <button type="button" wire:click="saveModifierGroup" class="fnb-btn-gold">Simpan Grup</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ================= MODAL: KONFIRMASI PERUBAHAN BELUM DISIMPAN ================= --}}
    @if($showUnsavedChangesModal)
        <div class="fnb-modal-backdrop" style="z-index:10000;">
            <div class="fnb-modal-dialog fnb-modal-sm">
                <div style="font-family:var(--font-serif); font-size:1.1rem; font-weight:800; color:#1F170D;">Ada Perubahan Belum Disimpan</div>
                <div style="font-size:0.875rem; color:#5C410F;">Form yang sedang dibuka belum disimpan. Simpan dulu sebelum pindah tab, atau buang perubahannya?</div>
                <div style="display:flex; justify-content:flex-end; gap:0.6rem; flex-wrap:wrap;">
                    <button type="button" wire:click="cancelTabChange" class="fnb-btn-action" style="background:#F3F4F6; color:#4B5563;">Batal Pindah</button>
                    <button type="button" wire:click="discardAndSwitchTab" class="fnb-btn-action fnb-btn-danger">Buang Perubahan</button>
                    <button type="button" wire:click="saveAndSwitchTab" class="fnb-btn-gold">Simpan &amp; Lanjut</button>
                </div>
            </div>
        </div>
    @endif
</div>
