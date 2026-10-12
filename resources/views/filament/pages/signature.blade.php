<div class="c61">
    @include('filament.partials.c61-admin-style')
    <style>
        .sg-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 1rem; }
        .sg-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0 1rem; }
        @media (max-width: 720px) { .sg-grid-2, .sg-grid-3 { grid-template-columns: 1fr; } }
        .sg-cols { display: grid; grid-template-columns: minmax(0, 1.4fr) minmax(0, 1fr); gap: 1rem; align-items: start; }
        @media (max-width: 1100px) { .sg-cols { grid-template-columns: 1fr; } }
        .sg-link { display: flex; gap: 0.5rem; align-items: center; }
        .sg-link input { font-family: var(--font-mono); font-size: 0.75rem; }
        .sg-error { display: block; font-size: 0.6875rem; font-weight: 600; color: #B42318; margin-top: 0.35rem; }
        .sg-switch { display: inline-flex; align-items: center; gap: 0.55rem; font-size: 0.8125rem; font-weight: 700; color: var(--c-brown); cursor: pointer; }
        .sg-switch input { width: 16px; height: 16px; accent-color: var(--c-terra); }
        .sg-actions { display: flex; flex-wrap: wrap; gap: 0.5rem; justify-content: flex-end; }
        .sg-cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 1rem; }
        .sg-card { background: #FFFFFF; border: 1px solid var(--c-line); border-radius: 16px; padding: 1rem 1.1rem; display: flex; flex-direction: column; gap: 0.75rem; min-width: 0; }
        .sg-card-title { font-family: var(--font-serif); font-size: 1.1rem; font-weight: 600; color: var(--c-brown); }
        .sg-mini { position: relative; aspect-ratio: 16 / 9; border-radius: 10px; overflow: hidden; border: 1px solid var(--c-line); }
        .sg-mini div { position: absolute; display: flex; align-items: center; justify-content: center; font-size: 0.625rem; font-weight: 800; letter-spacing: 0.06em; text-transform: uppercase; box-sizing: border-box; border: 1px solid rgba(255,255,255,0.15); }
        .sg-swatches { display: flex; gap: 0.35rem; }
        .sg-swatches span { width: 18px; height: 18px; border-radius: 5px; border: 1px solid rgba(0,0,0,0.12); }
        .sg-layouts { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.6rem; }
        .sg-layout { border: 1.5px solid var(--c-line); border-radius: 12px; padding: 0.5rem; cursor: pointer; display: flex; flex-direction: column; gap: 0.4rem; font-size: 0.6875rem; font-weight: 700; color: var(--c-brown); background: #FFFFFF; }
        .sg-layout.is-on { border-color: var(--c-terra); box-shadow: 0 0 0 3px rgba(102,39,33,0.12); }
        .sg-color { display: flex; gap: 0.4rem; align-items: center; }
        .sg-color input[type=color] { width: 38px; height: 34px; padding: 2px; border: 1px solid var(--c-line); border-radius: 8px; background: #FFFFFF; flex-shrink: 0; }
        .sg-section { font-size: 0.6875rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: var(--c-muted); margin: 1rem 0 0.6rem; }
        .sg-playlist { display: flex; flex-direction: column; gap: 0.4rem; }
        .sg-pl-row { display: grid; grid-template-columns: 28px minmax(0, 1fr) 96px auto; gap: 0.5rem; align-items: center; padding: 0.45rem 0.6rem; border: 1px solid var(--c-line-soft); border-radius: 10px; background: var(--c-paper); }
        .sg-pl-no { font-family: var(--font-mono); font-weight: 700; color: var(--c-terra); font-size: 0.75rem; }
        .sg-pl-name { font-size: 0.8125rem; font-weight: 700; color: var(--c-brown); }
        .sg-pl-type { font-size: 0.6875rem; color: var(--c-muted); }
        .sg-pl-btns { display: flex; gap: 0.25rem; }
        .sg-add { display: flex; gap: 0.5rem; margin-top: 0.5rem; }
        .sg-thumb { width: 52px; height: 30px; object-fit: cover; border-radius: 6px; border: 1px solid var(--c-line); background: var(--c-paper); display: block; }
        .sg-preview-box { position: relative; width: 100%; aspect-ratio: 16 / 9; background: #000; border-radius: 12px; overflow: hidden; }
        .sg-preview-box iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0; }
        .sg-tokens { display: flex; flex-wrap: wrap; gap: 0.35rem; margin-top: 0.45rem; }
        .sg-tokens code { font-family: var(--font-mono); font-size: 0.6875rem; background: var(--c-cream); color: var(--c-terra); border-radius: 6px; padding: 0.1rem 0.4rem; }
        .sg-modal-wide { max-width: 860px; }
        .sg-modal-xl { max-width: 1120px; }
    </style>

    @php
        $screen = $this->screen;
        $published = $screen->publishedVersion;
        $layoutBoxes = fn (string $layout) => \App\Services\Signature\SignatureService::LAYOUTS[$layout]['zones'] ?? [];
        $zoneLabel = ['playlist' => 'Konten', 'side_panel' => 'Panel', 'ticker' => 'Running text'];
    @endphp

    {{-- HEADER --}}
    <div class="c61-hero">
        <div>
            <div class="c61-eyebrow"><span class="dot"></span><span>Digital &bull; TV Venue</span></div>
            <div class="c61-hero-title">Digital Signage</div>
            <div class="c61-hero-sub">Atur tampilan TV: template, konten bergantian, monitoring lapangan, running text, dan sponsor. Semua perubahan tersimpan sebagai draft — TV baru berubah setelah ditekan <strong>Tayangkan</strong>.</div>
        </div>
        <div class="c61-hero-actions">
            <button type="button" wire:click="previewScreen" class="c61-btn c61-btn-outline-cream c61-btn-lg">Preview</button>
            @if($this->canPublish)
                <button type="button" class="c61-btn c61-btn-cream c61-btn-lg"
                    x-on:click="$dispatch('club61-confirm', { title: 'Tayangkan ke TV?', message: 'Template, konten, running text, sponsor, dan pengaturan layar saat ini dikirim ke TV (±15 detik).', confirmLabel: 'Ya, Tayangkan', onConfirm: () => $wire.publish() })">Tayangkan</button>
            @endif
        </div>
    </div>

    <div>
        <div class="c61-seg">
            @foreach(\App\Filament\Pages\Signature::TABS as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')" class="c61-seg-btn {{ $activeTab === $key ? 'is-active' : '' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    {{-- ===================== LAYAR & TAYANGAN ===================== --}}
    @if($activeTab === 'screen')
        <div class="sg-cols">
            <div class="c61-card">
                <div class="c61-card-head">
                    <div>
                        <div class="c61-card-title">Layar TV</div>
                        <div class="c61-card-sub">Buka link di browser Smart TV lalu biarkan layar penuh. Link ini tanpa login — jangan dibagikan.</div>
                    </div>
                    @if($screen->isOnline())
                        <span class="c61-pill c61-pill-ok">Online</span>
                    @else
                        <span class="c61-pill c61-pill-gray">{{ $screen->last_seen_at ? 'Offline sejak '.$screen->last_seen_at->setTimezone('Asia/Jakarta')->format('d/m H:i') : 'Belum pernah dibuka' }}</span>
                    @endif
                </div>
                <div class="c61-card-body">
                    <div class="c61-field">
                        <label class="c61-label" for="sg-player-url">Link layar</label>
                        <div class="sg-link" x-data="{ copied: false }">
                            <input id="sg-player-url" type="text" readonly value="{{ $this->playerUrl }}" class="c61-input" x-ref="url" x-on:focus="$el.select()">
                            <button type="button" class="c61-btn c61-btn-ghost c61-btn-sm" x-on:click="navigator.clipboard ? navigator.clipboard.writeText($refs.url.value).then(() => { copied = true; setTimeout(() => copied = false, 1800) }) : $refs.url.select()" x-text="copied ? 'Tersalin' : 'Salin'">Salin</button>
                            <a href="{{ $this->playerUrl }}" target="_blank" rel="noopener" class="c61-btn c61-btn-ghost c61-btn-sm">Buka</a>
                        </div>
                    </div>

                    <div class="sg-grid-2">
                        <div class="c61-field">
                            <label class="c61-label" for="sg-screen-name">Nama layar</label>
                            <input id="sg-screen-name" type="text" wire:model="screenName" class="c61-input" @disabled(! $this->canManage)>
                            @error('screenName') <span class="sg-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="c61-field">
                            <label class="c61-label" for="sg-screen-template">Template yang ditayangkan</label>
                            <select id="sg-screen-template" wire:model="screenTemplateId" class="c61-select" @disabled(! $this->canManage)>
                                @foreach($this->templates as $template)
                                    <option value="{{ $template->id }}">{{ $template->name }}</option>
                                @endforeach
                            </select>
                            @error('screenTemplateId') <span class="sg-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="c61-field">
                        <label class="c61-label" for="sg-name-display">Nama customer di monitoring lapangan</label>
                        <select id="sg-name-display" wire:model="screenNameDisplay" class="c61-select" @disabled(! $this->canManage)>
                            @foreach(\App\Models\Signature\SignatureScreen::NAME_DISPLAYS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        <span class="c61-hint">No. HP &amp; email customer tidak pernah tampil di TV.</span>
                    </div>

                    <div style="display:flex; flex-direction:column; gap:0.6rem;">
                        <label class="sg-switch" for="sg-sound"><input id="sg-sound" type="checkbox" wire:model="screenSound" @disabled(! $this->canManage)> Izinkan suara video (video yang diatur bersuara)</label>
                        <label class="sg-switch" for="sg-hours"><input id="sg-hours" type="checkbox" wire:model="screenHoursOnly" @disabled(! $this->canManage)> Tayang hanya di jam operasional — di luar jam tampil logo + "Sampai jumpa besok"</label>
                    </div>
                </div>
                <div class="c61-card-foot sg-actions">
                    @if($this->canPublish)
                        <button type="button" class="c61-btn c61-btn-ghost c61-btn-sm"
                            x-on:click="$dispatch('club61-confirm', { title: 'Buat ulang link layar?', message: 'Link lama langsung tidak berlaku. Buka link baru di TV setelah ini.', confirmLabel: 'Ya, Buat Ulang', tone: 'danger', onConfirm: () => $wire.regenerateToken() })">Buat Ulang Link</button>
                    @endif
                    <button type="button" wire:click="previewScreen" class="c61-btn c61-btn-ghost">Preview</button>
                    @if($this->canManage)
                        <button type="button" wire:click="saveScreen" class="c61-btn c61-btn-primary">Simpan Draft</button>
                    @endif
                </div>
            </div>

            <div style="display:flex; flex-direction:column; gap:1rem;">
                <div class="c61-card">
                    <div class="c61-card-head">
                        <div>
                            <div class="c61-card-title">Sedang Tayang di TV</div>
                            <div class="c61-card-sub">{{ $published ? 'Versi '.$published->version.' · '.($published->payload['template']['name'] ?? '-') : 'Belum ada yang ditayangkan' }}</div>
                        </div>
                    </div>
                    <div class="c61-card-body">
                        @if($published)
                            <div class="c61-hint" style="margin-top:0;">Ditayangkan {{ $published->created_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} oleh {{ $published->publishedBy?->name ?? '-' }} · {{ count($published->payload['playlists']['main'] ?? []) }} konten di playlist.</div>
                        @else
                            <div class="c61-hint" style="margin-top:0;">TV menampilkan pesan "Belum ada tampilan" sampai Tayangkan ditekan.</div>
                        @endif
                    </div>
                </div>

                <div class="c61-card">
                    <div class="c61-card-head"><div class="c61-card-title">Riwayat Versi</div></div>
                    <div class="c61-table-wrap">
                        <table class="c61-table">
                            <thead><tr><th>Versi</th><th>Keterangan</th><th>Aksi</th></tr></thead>
                            <tbody>
                                @forelse($this->versions as $version)
                                    <tr wire:key="sg-version-{{ $version->id }}">
                                        <td class="code">v{{ $version->version }}</td>
                                        <td>
                                            <div class="strong">{{ $version->note }}</div>
                                            <div class="sub">{{ $version->created_at->setTimezone('Asia/Jakarta')->format('d/m/Y H:i') }} · {{ $version->publishedBy?->name ?? '-' }}</div>
                                        </td>
                                        <td>
                                            @if($published && $published->id === $version->id)
                                                <span class="c61-pill c61-pill-ok">Tayang</span>
                                            @elseif($this->canPublish)
                                                <button type="button" class="c61-btn c61-btn-ghost c61-btn-sm"
                                                    x-on:click="$dispatch('club61-confirm', { title: @js('Tayangkan ulang versi '.$version->version.'?'), message: 'Tampilan versi itu dikirim lagi ke TV sebagai versi baru.', confirmLabel: 'Ya, Kembalikan', onConfirm: () => $wire.restoreVersion(@js($version->id)) })">Kembalikan</button>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="c61-empty">Belum ada versi.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="c61-note">
                    <strong>Smart TV:</strong> video bersuara baru keluar suaranya setelah tombol <strong>OK</strong> di remote ditekan sekali tiap TV dinyalakan (aturan browser). Matikan screensaver &amp; auto power off di pengaturan TV.
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== TEMPLATE ===================== --}}
    @if($activeTab === 'templates')
        <div class="c61-card">
            <div class="c61-card-head">
                <div>
                    <div class="c61-card-title">Template</div>
                    <div class="c61-card-sub">Susunan layar, warna, font, logo, running text, dan playlist konten. Template custom drag &amp; drop menyusul di Tahap 2.</div>
                </div>
                @if($this->canManage)
                    <button type="button" wire:click="openCreateTemplate" class="c61-btn c61-btn-primary">+ Tambah Template</button>
                @endif
            </div>
            <div class="c61-card-body">
                <div class="sg-cards">
                    @foreach($this->templates as $template)
                        @php $theme = array_merge(\App\Services\Signature\SignatureService::DEFAULT_THEME, $template->theme ?? []); $tick = array_merge(\App\Services\Signature\SignatureService::DEFAULT_TICKER, $template->ticker ?? []); @endphp
                        <div class="sg-card" wire:key="sg-template-{{ $template->id }}">
                            <div class="sg-mini" style="background: {{ $theme['background'] }};">
                                @foreach($layoutBoxes($template->layout) as $zone)
                                    <div style="left:{{ $zone['x'] }}%; top:{{ $zone['y'] }}%; width:{{ $zone['w'] }}%; height:{{ $zone['h'] }}%; background: {{ $zone['kind'] === 'ticker' ? $tick['background'] : ($zone['kind'] === 'side_panel' ? $theme['panel'] : $theme['background']) }}; color: {{ $zone['kind'] === 'ticker' ? $tick['color'] : $theme['text'] }};">{{ $zoneLabel[$zone['kind']] ?? '' }}</div>
                                @endforeach
                            </div>
                            <div>
                                <div class="sg-card-title">{{ $template->name }}</div>
                                <div class="c61-hint" style="margin-top:0.15rem;">{{ \App\Services\Signature\SignatureService::LAYOUTS[$template->layout]['label'] ?? $template->layout }} · {{ $template->playlist_items_count }} konten</div>
                            </div>
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:0.5rem;">
                                <div class="sg-swatches">
                                    @foreach([$theme['background'], $theme['panel'], $theme['accent'], $theme['text']] as $swatch)
                                        <span style="background: {{ $swatch }};"></span>
                                    @endforeach
                                </div>
                                @if($screen->template_id === $template->id)
                                    <span class="c61-pill c61-pill-terra">Dipakai layar</span>
                                @endif
                            </div>
                            @if($this->canManage)
                                <div class="c61-actions">
                                    <button type="button" wire:click="openEditTemplate('{{ $template->id }}')" class="c61-btn c61-btn-ghost c61-btn-sm">Edit</button>
                                    <button type="button" wire:click="duplicateTemplate('{{ $template->id }}')" class="c61-btn c61-btn-ghost c61-btn-sm">Duplikat</button>
                                    <button type="button" class="c61-btn c61-btn-danger c61-btn-sm"
                                        x-on:click="$dispatch('club61-confirm', { title: @js('Hapus template '.$template->name.'?'), message: 'Template & playlist-nya dihapus. Versi yang sudah ditayangkan tidak terpengaruh.', confirmLabel: 'Ya, Hapus', tone: 'danger', onConfirm: () => $wire.deleteTemplate(@js($template->id)) })">Hapus</button>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== KONTEN ===================== --}}
    @if($activeTab === 'contents')
        <div class="c61-card">
            <div class="c61-card-head">
                <div>
                    <div class="c61-card-title">Library Konten</div>
                    <div class="c61-card-sub">Gambar & video MP4 yang di-upload, plus widget dari sistem. Konten dipasang ke template lewat playlist.</div>
                </div>
                @if($this->canManage)
                    <button type="button" wire:click="openCreateContent" class="c61-btn c61-btn-primary">+ Tambah Konten</button>
                @endif
            </div>
            <div class="c61-table-wrap">
                <table class="c61-table" style="min-width:760px;">
                    <thead><tr><th></th><th>Nama</th><th>Jenis</th><th>Durasi</th><th>Suara</th><th>Dipakai</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach($this->contents as $content)
                            <tr wire:key="sg-content-{{ $content->id }}">
                                <td>
                                    @if($content->type === 'IMAGE' && $content->file_path)
                                        <img src="{{ Storage::disk('public')->url($content->file_path) }}" alt="" class="sg-thumb">
                                    @elseif($content->type === 'VIDEO')
                                        <div class="sg-thumb" style="display:flex; align-items:center; justify-content:center; font-size:0.625rem; font-weight:800; color:var(--c-terra);">MP4</div>
                                    @else
                                        <div class="sg-thumb" style="display:flex; align-items:center; justify-content:center; font-size:0.625rem; font-weight:800; color:var(--c-muted);">LIVE</div>
                                    @endif
                                </td>
                                <td class="strong">{{ $content->name }}</td>
                                <td><span class="c61-pill {{ in_array($content->type, \App\Models\Signature\SignatureContent::FILE_TYPES, true) ? 'c61-pill-cream' : 'c61-pill-info' }}">{{ \App\Models\Signature\SignatureContent::TYPES[$content->type] ?? $content->type }}</span></td>
                                <td>{{ $content->type === 'VIDEO' ? 'Sampai selesai' : $content->duration_seconds.' detik' }}</td>
                                <td>{{ $content->type === 'VIDEO' ? ($content->sound ? 'Ya · '.$content->volume.'%' : 'Mute') : '—' }}</td>
                                <td>{{ $content->playlist_items_count }} template</td>
                                <td><span class="c61-pill {{ $content->is_active ? 'c61-pill-ok' : 'c61-pill-gray' }}">{{ $content->is_active ? 'Aktif' : 'Nonaktif' }}</span></td>
                                <td>
                                    @if($this->canManage)
                                        <div class="c61-actions">
                                            <button type="button" wire:click="openEditContent('{{ $content->id }}')" class="c61-btn c61-btn-ghost c61-btn-sm">Edit</button>
                                            @if(in_array($content->type, \App\Models\Signature\SignatureContent::FILE_TYPES, true))
                                                <button type="button" class="c61-btn c61-btn-danger c61-btn-sm"
                                                    x-on:click="$dispatch('club61-confirm', { title: @js('Hapus '.$content->name.'?'), message: 'File dihapus dan konten dikeluarkan dari semua playlist template (draft).', confirmLabel: 'Ya, Hapus', tone: 'danger', onConfirm: () => $wire.deleteContent(@js($content->id)) })">Hapus</button>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ===================== RUNNING TEXT ===================== --}}
    @if($activeTab === 'texts')
        <div class="c61-card">
            <div class="c61-card-head">
                <div>
                    <div class="c61-card-title">Running Text</div>
                    <div class="c61-card-sub">Teks manual + kalimat otomatis dari sistem. Font, ukuran, warna, dan kecepatan diatur per template.</div>
                </div>
                @if($this->canManage)
                    <button type="button" wire:click="openCreateText" class="c61-btn c61-btn-primary">+ Tambah Teks</button>
                @endif
            </div>
            <div class="c61-table-wrap">
                <table class="c61-table" style="min-width:720px;">
                    <thead><tr><th>Jenis</th><th>Teks</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @foreach($this->texts as $text)
                            <tr wire:key="sg-text-{{ $text->id }}">
                                <td><span class="c61-pill {{ $text->kind === 'MANUAL' ? 'c61-pill-cream' : 'c61-pill-info' }}">{{ \App\Models\Signature\SignatureRunningText::KINDS[$text->kind] ?? $text->kind }}</span></td>
                                <td style="white-space:normal; max-width:520px;">{{ $text->text }}</td>
                                <td>
                                    <button type="button" wire:click="toggleText('{{ $text->id }}')" @disabled(! $this->canManage) class="c61-pill {{ $text->is_active ? 'c61-pill-ok' : 'c61-pill-gray' }}" style="cursor:pointer;">{{ $text->is_active ? 'Aktif' : 'Mati' }}</button>
                                </td>
                                <td>
                                    @if($this->canManage)
                                        <div class="c61-actions">
                                            <button type="button" wire:click="openEditText('{{ $text->id }}')" class="c61-btn c61-btn-ghost c61-btn-sm">Edit</button>
                                            @if($text->kind === 'MANUAL')
                                                <button type="button" class="c61-btn c61-btn-danger c61-btn-sm"
                                                    x-on:click="$dispatch('club61-confirm', { title: 'Hapus running text ini?', message: @js($text->text), confirmLabel: 'Ya, Hapus', tone: 'danger', onConfirm: () => $wire.deleteText(@js($text->id)) })">Hapus</button>
                                            @endif
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ===================== SPONSOR ===================== --}}
    @if($activeTab === 'sponsors')
        <div class="c61-card">
            <div class="c61-card-head">
                <div>
                    <div class="c61-card-title">Sponsor</div>
                    <div class="c61-card-sub">Sponsor brand (input manual) atau dari Sponsor Corporate. Tampil di panel samping, widget Sponsor, dan running text otomatis.</div>
                </div>
                @if($this->canManage)
                    <button type="button" wire:click="openCreateSponsor" class="c61-btn c61-btn-primary">+ Tambah Sponsor</button>
                @endif
            </div>
            <div class="c61-table-wrap">
                <table class="c61-table" style="min-width:760px;">
                    <thead><tr><th></th><th>Nama</th><th>Sumber</th><th>Periode</th><th>Durasi</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        @forelse($this->sponsors as $sponsor)
                            <tr wire:key="sg-sponsor-{{ $sponsor->id }}">
                                <td>
                                    @if($sponsor->logo_path)
                                        <img src="{{ Storage::disk('public')->url($sponsor->logo_path) }}" alt="" class="sg-thumb" style="object-fit:contain; background:#FFFFFF;">
                                    @else
                                        <div class="sg-thumb"></div>
                                    @endif
                                </td>
                                <td class="strong">{{ $sponsor->displayName() ?: '—' }}</td>
                                <td><span class="c61-pill {{ $sponsor->kind === 'CORPORATE' ? 'c61-pill-info' : 'c61-pill-cream' }}">{{ $sponsor->kind === 'CORPORATE' ? 'Sponsor Corporate' : 'Brand' }}</span></td>
                                <td>{{ $sponsor->starts_on || $sponsor->ends_on ? ($sponsor->starts_on?->format('d/m/Y') ?? '…').' – '.($sponsor->ends_on?->format('d/m/Y') ?? '…') : 'Tanpa batas' }}</td>
                                <td>{{ $sponsor->duration_seconds }} detik</td>
                                <td><span class="c61-pill {{ $sponsor->isShowing() ? 'c61-pill-ok' : 'c61-pill-gray' }}">{{ $sponsor->isShowing() ? 'Tampil' : ($sponsor->is_active ? 'Di luar periode' : 'Nonaktif') }}</span></td>
                                <td>
                                    @if($this->canManage)
                                        <div class="c61-actions">
                                            <button type="button" wire:click="openEditSponsor('{{ $sponsor->id }}')" class="c61-btn c61-btn-ghost c61-btn-sm">Edit</button>
                                            <button type="button" class="c61-btn c61-btn-danger c61-btn-sm"
                                                x-on:click="$dispatch('club61-confirm', { title: @js('Hapus sponsor '.($sponsor->displayName() ?: '').'?'), message: 'Sponsor tidak tampil lagi setelah Tayangkan berikutnya.', confirmLabel: 'Ya, Hapus', tone: 'danger', onConfirm: () => $wire.deleteSponsor(@js($sponsor->id)) })">Hapus</button>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="c61-empty">Belum ada sponsor.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- ===================== MODAL: TEMPLATE ===================== --}}
    @if($showTemplateModal)
        <div class="c61-modal-backdrop">
            <div class="c61-modal sg-modal-wide">
                <div class="c61-modal-head">
                    <div class="c61-modal-title" style="margin-top:0;">{{ $editingTemplateId ? 'Edit Template' : 'Tambah Template' }}</div>
                    <button type="button" wire:click="closeTemplateModal" class="c61-modal-close">&times;</button>
                </div>
                <div class="c61-modal-body" style="gap:0;">
                    <div class="c61-field">
                        <label class="c61-label" for="sg-tpl-name">Nama template</label>
                        <input id="sg-tpl-name" type="text" wire:model="tplName" class="c61-input" maxlength="80">
                        @error('tplName') <span class="sg-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="sg-section">Susunan layar</div>
                    <div class="sg-layouts">
                        @foreach(\App\Services\Signature\SignatureService::LAYOUTS as $key => $layout)
                            <label class="sg-layout {{ $tplLayout === $key ? 'is-on' : '' }}">
                                <input type="radio" wire:model.live="tplLayout" value="{{ $key }}" style="display:none;">
                                <div class="sg-mini" style="background: {{ $tplTheme['background'] ?? '#1E120F' }};">
                                    @foreach($layout['zones'] as $zone)
                                        <div style="left:{{ $zone['x'] }}%; top:{{ $zone['y'] }}%; width:{{ $zone['w'] }}%; height:{{ $zone['h'] }}%; background: {{ $zone['kind'] === 'ticker' ? ($tplTicker['background'] ?? '#662721') : ($zone['kind'] === 'side_panel' ? ($tplTheme['panel'] ?? '#2A1A16') : ($tplTheme['background'] ?? '#1E120F')) }}; color: {{ $tplTheme['text'] ?? '#F7F0DB' }};">{{ $zoneLabel[$zone['kind']] ?? '' }}</div>
                                    @endforeach
                                </div>
                                <span>{{ $layout['label'] }}</span>
                            </label>
                        @endforeach
                    </div>

                    <div class="sg-section">Warna &amp; font</div>
                    <div class="sg-grid-2">
                        @foreach(['background' => 'Latar', 'panel' => 'Panel / kartu', 'accent' => 'Aksen', 'text' => 'Teks'] as $key => $label)
                            <div class="c61-field">
                                <label class="c61-label" for="sg-theme-{{ $key }}">{{ $label }}</label>
                                <div class="sg-color">
                                    <input type="color" wire:model.live="tplTheme.{{ $key }}" aria-label="{{ $label }}">
                                    <input id="sg-theme-{{ $key }}" type="text" wire:model.live.debounce.500ms="tplTheme.{{ $key }}" class="c61-input" maxlength="7">
                                </div>
                                @error('tplTheme.'.$key) <span class="sg-error">{{ $message }}</span> @enderror
                            </div>
                        @endforeach
                    </div>
                    <div class="sg-grid-2">
                        <div class="c61-field">
                            <label class="c61-label" for="sg-theme-font">Font</label>
                            <select id="sg-theme-font" wire:model="tplTheme.font" class="c61-select">
                                @foreach(\App\Services\Signature\SignatureService::FONTS as $font)
                                    <option value="{{ $font }}">{{ $font }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="c61-field">
                            <label class="c61-label" for="sg-tpl-logo">Logo (kosong = logo Club 61)</label>
                            <input id="sg-tpl-logo" type="file" wire:model="tplLogoUpload" accept="image/png,image/jpeg,image/webp" class="c61-input" style="height:auto; padding:0.4rem;">
                            @if($tplLogoPath && ! $tplRemoveLogo && ! $tplLogoUpload)
                                <label class="sg-switch" style="margin-top:0.4rem;"><input type="checkbox" wire:model="tplRemoveLogo"> Hapus logo, pakai logo Club 61</label>
                            @endif
                            @error('tplLogoUpload') <span class="sg-error">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div class="sg-section">Running text</div>
                    <div class="sg-grid-3">
                        <div class="c61-field">
                            <label class="c61-label" for="sg-tick-font">Font</label>
                            <select id="sg-tick-font" wire:model="tplTicker.font" class="c61-select">
                                @foreach(\App\Services\Signature\SignatureService::FONTS as $font)
                                    <option value="{{ $font }}">{{ $font }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="c61-field">
                            <label class="c61-label" for="sg-tick-size">Ukuran huruf (px)</label>
                            <input id="sg-tick-size" type="number" min="20" max="120" wire:model="tplTicker.font_size" class="c61-input">
                            @error('tplTicker.font_size') <span class="sg-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="c61-field">
                            <label class="c61-label" for="sg-tick-speed">Kecepatan (px / detik)</label>
                            <input id="sg-tick-speed" type="number" min="20" max="600" wire:model="tplTicker.speed" class="c61-input">
                            @error('tplTicker.speed') <span class="sg-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="c61-field">
                            <label class="c61-label" for="sg-tick-dir">Arah</label>
                            <select id="sg-tick-dir" wire:model="tplTicker.direction" class="c61-select">
                                <option value="left">Ke kiri</option>
                                <option value="right">Ke kanan</option>
                            </select>
                        </div>
                        @foreach(['background' => 'Latar running text', 'color' => 'Warna teks'] as $key => $label)
                            <div class="c61-field">
                                <label class="c61-label" for="sg-tick-{{ $key }}">{{ $label }}</label>
                                <div class="sg-color">
                                    <input type="color" wire:model.live="tplTicker.{{ $key }}" aria-label="{{ $label }}">
                                    <input id="sg-tick-{{ $key }}" type="text" wire:model.live.debounce.500ms="tplTicker.{{ $key }}" class="c61-input" maxlength="7">
                                </div>
                                @error('tplTicker.'.$key) <span class="sg-error">{{ $message }}</span> @enderror
                            </div>
                        @endforeach
                    </div>

                    <div class="sg-section">Playlist konten (zona utama) — tayang bergantian sesuai urutan</div>
                    @php $contentMap = $this->contents->keyBy('id'); @endphp
                    <div class="sg-playlist">
                        @forelse($tplPlaylist as $i => $row)
                            @php $item = $contentMap[$row['content_id']] ?? null; @endphp
                            <div class="sg-pl-row" wire:key="sg-pl-{{ $i }}-{{ $row['content_id'] }}">
                                <span class="sg-pl-no">{{ $i + 1 }}</span>
                                <div style="min-width:0;">
                                    <div class="sg-pl-name c61-truncate">{{ $item?->name ?? 'Konten terhapus' }}</div>
                                    <div class="sg-pl-type">{{ $item ? (\App\Models\Signature\SignatureContent::TYPES[$item->type] ?? $item->type) : '' }}{{ $item && ! $item->is_active ? ' · nonaktif (dilewati)' : '' }}</div>
                                </div>
                                <div>
                                    @if($item?->type === 'VIDEO')
                                        <span class="c61-hint" style="margin-top:0;">Sampai selesai</span>
                                    @else
                                        <input type="number" min="3" max="3600" wire:model="tplPlaylist.{{ $i }}.duration" placeholder="{{ $item?->duration_seconds ?? 15 }} dtk" class="c61-input" style="height:32px;" aria-label="Durasi detik">
                                    @endif
                                    @error('tplPlaylist.'.$i.'.duration') <span class="sg-error">{{ $message }}</span> @enderror
                                </div>
                                <div class="sg-pl-btns">
                                    <button type="button" wire:click="movePlaylistItem({{ $i }}, -1)" class="c61-icon-btn" @disabled($i === 0) aria-label="Naik">&uarr;</button>
                                    <button type="button" wire:click="movePlaylistItem({{ $i }}, 1)" class="c61-icon-btn" @disabled($i === count($tplPlaylist) - 1) aria-label="Turun">&darr;</button>
                                    <button type="button" wire:click="removePlaylistItem({{ $i }})" class="c61-icon-btn is-danger" aria-label="Hapus">&times;</button>
                                </div>
                            </div>
                        @empty
                            <div class="c61-note">Playlist kosong — TV menampilkan Monitoring Lapangan terus-menerus.</div>
                        @endforelse
                    </div>
                    <div class="sg-add">
                        <select wire:model="tplAddContentId" class="c61-select" aria-label="Pilih konten">
                            <option value="">Pilih konten untuk ditambahkan…</option>
                            @foreach($this->contents as $content)
                                <option value="{{ $content->id }}">{{ $content->name }} ({{ \App\Models\Signature\SignatureContent::TYPES[$content->type] ?? $content->type }})</option>
                            @endforeach
                        </select>
                        <button type="button" wire:click="addPlaylistItem" class="c61-btn c61-btn-ghost">+ Tambah</button>
                    </div>
                </div>
                <div class="c61-modal-foot">
                    <button type="button" wire:click="closeTemplateModal" class="c61-btn c61-btn-ghost">Batal</button>
                    <button type="button" wire:click="previewTemplate" class="c61-btn c61-btn-ghost">Preview</button>
                    <button type="button" wire:click="saveTemplate" class="c61-btn c61-btn-primary">Simpan Template</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== MODAL: KONTEN ===================== --}}
    @if($showContentModal)
        @php $isFile = in_array($contentType, \App\Models\Signature\SignatureContent::FILE_TYPES, true); @endphp
        <div class="c61-modal-backdrop">
            <div class="c61-modal" style="max-width:560px;">
                <div class="c61-modal-head">
                    <div class="c61-modal-title" style="margin-top:0;">{{ $editingContentId ? 'Edit Konten' : 'Tambah Konten' }}</div>
                    <button type="button" wire:click="closeContentModal" class="c61-modal-close">&times;</button>
                </div>
                <div class="c61-modal-body" style="gap:0;">
                    <div class="c61-field">
                        <label class="c61-label" for="sg-content-name">Nama</label>
                        <input id="sg-content-name" type="text" wire:model="contentName" class="c61-input" maxlength="100" placeholder="Contoh: Promo Membership Oktober">
                        @error('contentName') <span class="sg-error">{{ $message }}</span> @enderror
                    </div>
                    @if(! $editingContentId)
                        <div class="c61-field">
                            <label class="c61-label" for="sg-content-type">Jenis</label>
                            <select id="sg-content-type" wire:model.live="contentType" class="c61-select">
                                <option value="IMAGE">Gambar (JPG / PNG / WEBP, maks 10 MB)</option>
                                <option value="VIDEO">Video MP4 (maks 200 MB)</option>
                            </select>
                        </div>
                    @else
                        <div class="c61-field"><span class="c61-pill c61-pill-cream">{{ \App\Models\Signature\SignatureContent::TYPES[$contentType] ?? $contentType }}</span></div>
                    @endif

                    @if($isFile)
                        <div class="c61-field">
                            <label class="c61-label" for="sg-content-file">{{ $contentFilePath ? 'Ganti file (opsional)' : 'File' }}</label>
                            <input id="sg-content-file" type="file" wire:model="contentUpload" accept="{{ $contentType === 'VIDEO' ? 'video/mp4' : 'image/png,image/jpeg,image/webp' }}" class="c61-input" style="height:auto; padding:0.4rem;">
                            <div wire:loading wire:target="contentUpload" class="c61-hint">Mengunggah… (video besar bisa beberapa menit)</div>
                            @error('contentUpload') <span class="sg-error">{{ $message }}</span> @enderror
                            @if($contentFilePath && ! $contentUpload)
                                @if($contentType === 'IMAGE')
                                    <img src="{{ Storage::disk('public')->url($contentFilePath) }}" alt="" style="margin-top:0.6rem; max-width:100%; max-height:160px; border-radius:10px; border:1px solid var(--c-line);">
                                @else
                                    <video src="{{ Storage::disk('public')->url($contentFilePath) }}" controls muted style="margin-top:0.6rem; max-width:100%; max-height:180px; border-radius:10px;"></video>
                                @endif
                            @endif
                        </div>
                    @endif

                    <div class="sg-grid-2">
                        <div class="c61-field">
                            <label class="c61-label" for="sg-content-duration">{{ $contentType === 'VIDEO' ? 'Durasi (cadangan, detik)' : 'Durasi tampil (detik)' }}</label>
                            <input id="sg-content-duration" type="number" min="3" max="3600" wire:model="contentDuration" class="c61-input">
                            @if($contentType === 'VIDEO') <span class="c61-hint">Video diputar sampai selesai.</span> @endif
                            @error('contentDuration') <span class="sg-error">{{ $message }}</span> @enderror
                        </div>
                        @if($contentType === 'VIDEO')
                            <div class="c61-field">
                                <label class="c61-label" for="sg-content-volume">Volume (%)</label>
                                <input id="sg-content-volume" type="number" min="0" max="100" wire:model="contentVolume" class="c61-input">
                                @error('contentVolume') <span class="sg-error">{{ $message }}</span> @enderror
                            </div>
                        @endif
                    </div>
                    <div style="display:flex; flex-direction:column; gap:0.6rem;">
                        @if($contentType === 'VIDEO')
                            <label class="sg-switch" for="sg-content-sound"><input id="sg-content-sound" type="checkbox" wire:model="contentSound"> Video ini bersuara</label>
                        @endif
                        <label class="sg-switch" for="sg-content-active"><input id="sg-content-active" type="checkbox" wire:model="contentActive"> Aktif (konten nonaktif dilewati playlist)</label>
                    </div>
                </div>
                <div class="c61-modal-foot">
                    <button type="button" wire:click="closeContentModal" class="c61-btn c61-btn-ghost">Batal</button>
                    <button type="button" wire:click="{{ $isFile ? 'saveContent' : 'saveWidget' }}" wire:loading.attr="disabled" wire:target="contentUpload,saveContent" class="c61-btn c61-btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== MODAL: RUNNING TEXT ===================== --}}
    @if($showTextModal)
        <div class="c61-modal-backdrop">
            <div class="c61-modal" style="max-width:560px;">
                <div class="c61-modal-head">
                    <div class="c61-modal-title" style="margin-top:0;">{{ $editingTextId ? 'Edit Running Text' : 'Tambah Running Text' }}</div>
                    <button type="button" wire:click="closeTextModal" class="c61-modal-close">&times;</button>
                </div>
                <div class="c61-modal-body" style="gap:0;">
                    <div class="c61-field"><span class="c61-pill {{ $textKind === 'MANUAL' ? 'c61-pill-cream' : 'c61-pill-info' }}">{{ \App\Models\Signature\SignatureRunningText::KINDS[$textKind] ?? $textKind }}</span></div>
                    <div class="c61-field">
                        <label class="c61-label" for="sg-text-body">Teks</label>
                        <textarea id="sg-text-body" wire:model="textBody" rows="3" maxlength="300" class="c61-textarea"></textarea>
                        @if(isset(\App\Models\Signature\SignatureRunningText::TOKENS[$textKind]))
                            <div class="sg-tokens">
                                <span class="c61-hint" style="margin:0;">Isian otomatis:</span>
                                @foreach(\App\Models\Signature\SignatureRunningText::TOKENS[$textKind] as $token)
                                    <code>{{ $token }}</code>
                                @endforeach
                            </div>
                        @endif
                        @error('textBody') <span class="sg-error">{{ $message }}</span> @enderror
                    </div>
                    <label class="sg-switch" for="sg-text-active"><input id="sg-text-active" type="checkbox" wire:model="textActive"> Aktif</label>
                </div>
                <div class="c61-modal-foot">
                    <button type="button" wire:click="closeTextModal" class="c61-btn c61-btn-ghost">Batal</button>
                    <button type="button" wire:click="saveText" class="c61-btn c61-btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== MODAL: SPONSOR ===================== --}}
    @if($showSponsorModal)
        <div class="c61-modal-backdrop">
            <div class="c61-modal" style="max-width:600px;">
                <div class="c61-modal-head">
                    <div class="c61-modal-title" style="margin-top:0;">{{ $editingSponsorId ? 'Edit Sponsor' : 'Tambah Sponsor' }}</div>
                    <button type="button" wire:click="closeSponsorModal" class="c61-modal-close">&times;</button>
                </div>
                <div class="c61-modal-body" style="gap:0;">
                    <div class="c61-field">
                        <label class="c61-label" for="sg-sponsor-kind">Sumber</label>
                        <select id="sg-sponsor-kind" wire:model.live="sponsorKind" class="c61-select">
                            @foreach(\App\Models\Signature\SignatureSponsor::KINDS as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    @if($sponsorKind === 'CORPORATE')
                        <div class="c61-field">
                            <label class="c61-label" for="sg-sponsor-org">Perusahaan</label>
                            <select id="sg-sponsor-org" wire:model="sponsorOrgId" class="c61-select">
                                <option value="">Pilih Sponsor Corporate…</option>
                                @foreach($this->organizations as $org)
                                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                                @endforeach
                            </select>
                            @error('sponsorOrgId') <span class="sg-error">{{ $message }}</span> @enderror
                        </div>
                    @else
                        <div class="c61-field">
                            <label class="c61-label" for="sg-sponsor-name">Nama sponsor</label>
                            <input id="sg-sponsor-name" type="text" wire:model="sponsorName" maxlength="100" class="c61-input">
                            @error('sponsorName') <span class="sg-error">{{ $message }}</span> @enderror
                        </div>
                    @endif
                    <div class="c61-field">
                        <label class="c61-label" for="sg-sponsor-logo">Logo (PNG transparan paling bagus, maks 4 MB)</label>
                        <input id="sg-sponsor-logo" type="file" wire:model="sponsorLogoUpload" accept="image/png,image/jpeg,image/webp" class="c61-input" style="height:auto; padding:0.4rem;">
                        @if($sponsorLogoPath && ! $sponsorLogoUpload)
                            <img src="{{ Storage::disk('public')->url($sponsorLogoPath) }}" alt="" style="margin-top:0.6rem; max-height:80px; max-width:220px; object-fit:contain;">
                        @endif
                        @error('sponsorLogoUpload') <span class="sg-error">{{ $message }}</span> @enderror
                    </div>
                    <div class="sg-grid-3">
                        <div class="c61-field">
                            <label class="c61-label" for="sg-sponsor-duration">Durasi tampil (detik)</label>
                            <input id="sg-sponsor-duration" type="number" min="3" max="600" wire:model="sponsorDuration" class="c61-input">
                            @error('sponsorDuration') <span class="sg-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="c61-field">
                            <label class="c61-label" for="sg-sponsor-start">Mulai tayang</label>
                            <input id="sg-sponsor-start" type="date" wire:model="sponsorStarts" class="c61-input">
                        </div>
                        <div class="c61-field">
                            <label class="c61-label" for="sg-sponsor-end">Selesai tayang</label>
                            <input id="sg-sponsor-end" type="date" wire:model="sponsorEnds" class="c61-input">
                            @error('sponsorEnds') <span class="sg-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <label class="sg-switch" for="sg-sponsor-active"><input id="sg-sponsor-active" type="checkbox" wire:model="sponsorActive"> Aktif</label>
                </div>
                <div class="c61-modal-foot">
                    <button type="button" wire:click="closeSponsorModal" class="c61-btn c61-btn-ghost">Batal</button>
                    <button type="button" wire:click="saveSponsor" wire:loading.attr="disabled" wire:target="sponsorLogoUpload,saveSponsor" class="c61-btn c61-btn-primary">Simpan</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===================== MODAL: PREVIEW ===================== --}}
    @if($previewUrl)
        <div class="c61-modal-backdrop" style="z-index:10001;">
            <div class="c61-modal sg-modal-xl">
                <div class="c61-modal-head">
                    <div>
                        <div class="c61-modal-eyebrow">Belum ditayangkan</div>
                        <div class="c61-modal-title" style="margin-top:0;">Preview Layar TV</div>
                    </div>
                    <button type="button" wire:click="closePreview" class="c61-modal-close">&times;</button>
                </div>
                <div class="c61-modal-body">
                    <div class="sg-preview-box"><iframe src="{{ $previewUrl }}" title="Preview layar TV" allow="autoplay"></iframe></div>
                    <div class="c61-hint" style="margin-top:0;">Persis seperti di TV (kanvas 16:9). Data monitoring lapangan asli. Klik di dalam preview untuk mendengar suara video.</div>
                </div>
                <div class="c61-modal-foot">
                    <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="c61-btn c61-btn-ghost">Buka layar penuh</a>
                    <button type="button" wire:click="closePreview" class="c61-btn c61-btn-primary">Tutup</button>
                </div>
            </div>
        </div>
    @endif
</div>
