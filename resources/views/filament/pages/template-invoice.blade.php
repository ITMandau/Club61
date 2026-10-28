{{--
    Keuangan → Template Invoice (App\Filament\Pages\TemplateInvoice). Kiri: isian; kanan: pratinjau A4 dari template PDF
    yang sama persis (App\Services\Mail\InvoicePdf), diperbarui saat mengetik. Kolom kosong = teks bawaan (placeholder).
--}}
@php
    $d = $this->defaults;
    $ro = ! $this->canManage;
    $savedLogo = \App\Models\Setting\InvoiceTemplateSetting::current();
@endphp
<div class="c61 ti-wrap">
    @include('filament.partials.c61-admin-style')
    <style>
        .ti-grid { display: grid; grid-template-columns: minmax(0, 440px) minmax(0, 1fr); gap: 1.25rem; align-items: start; }
        @media (max-width: 1180px) { .ti-grid { grid-template-columns: 1fr; } }
        .ti-col { display: flex; flex-direction: column; gap: 1rem; min-width: 0; }
        .ti-two { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.85rem; }
        @media (max-width: 520px) { .ti-two { grid-template-columns: 1fr; } }
        .ti-body { display: flex; flex-direction: column; gap: 0.95rem; }
        .ti-body .c61-label { margin-bottom: 0.35rem; }
        .ti-err { font-size: 0.6875rem; font-weight: 700; color: #B42318; margin-top: 0.3rem; }

        /* Logo */
        .ti-logo { display: flex; align-items: center; gap: 1rem; }
        .ti-logo-box { width: 132px; height: 84px; flex-shrink: 0; border-radius: 12px; border: 1px solid var(--c-line); background: #FFFFFF; display: flex; align-items: center; justify-content: center; padding: 0.6rem; }
        .ti-logo-box img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .ti-logo-meta { min-width: 0; display: flex; flex-direction: column; gap: 0.5rem; }
        .ti-logo-state { font-size: 0.75rem; font-weight: 700; color: var(--c-brown); }
        .ti-logo-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .ti-file { position: relative; overflow: hidden; }
        .ti-file input { position: absolute; inset: 0; opacity: 0; cursor: pointer; }

        /* Warna aksen */
        .ti-swatches { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.5rem; }
        .ti-swatch { display: flex; align-items: center; gap: 0.55rem; min-height: 44px; padding: 0 0.7rem; border-radius: 10px; border: 1px solid var(--c-line); background: var(--c-paper); cursor: pointer; font-size: 0.75rem; font-weight: 700; color: var(--c-brown); }
        .ti-swatch:hover { border-color: #D8C6A4; }
        .ti-swatch.is-active { border-color: var(--c-terra); background: #FFFFFF; box-shadow: 0 0 0 3px rgba(102, 39, 33, 0.12); }
        .ti-swatch .dot { width: 18px; height: 18px; border-radius: 50%; flex-shrink: 0; box-shadow: inset 0 0 0 1px rgba(0, 0, 0, 0.12); }
        .ti-swatch input { position: absolute; opacity: 0; pointer-events: none; }

        /* Pratinjau A4 */
        .ti-preview { position: sticky; top: 1rem; }
        .ti-stage { background: #EFE9DF; border-radius: 14px; padding: 1.25rem; }
        .ti-stage-head { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 0.9rem; flex-wrap: wrap; }
        .ti-stage-title { font-family: var(--font-serif); font-size: 1rem; font-weight: 600; color: var(--c-brown); }
        .ti-stage-sub { font-size: 0.6875rem; color: var(--c-muted); }
        .ti-paper { position: relative; overflow: hidden; background: #FFFFFF; border-radius: 4px; box-shadow: 0 1px 2px rgba(42, 26, 23, 0.08), 0 18px 40px -18px rgba(42, 26, 23, 0.35); }
        .ti-paper iframe { position: absolute; top: 0; left: 0; width: 794px; height: 1123px; border: 0; transform-origin: 0 0; background: #FFFFFF; }

        .ti-savebar { display: flex; align-items: center; justify-content: space-between; gap: 0.75rem 1rem; flex-wrap: wrap; padding: 1rem 1.15rem; background: var(--c-paper); }
        .ti-savebar-text { font-size: 0.75rem; color: var(--c-muted); max-width: 300px; line-height: 1.45; }
        .ti-savebar-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    </style>

    {{-- HEADER --}}
    <div class="c61-hero">
        <div>
            <div class="c61-eyebrow"><span class="dot"></span>Keuangan &bull; Dokumen resmi Club 61</div>
            <div class="c61-hero-title">Template Invoice</div>
            <div class="c61-hero-sub">
                PDF invoice A4 yang dilampirkan di email customer setelah pembayaran lunas (booking online &amp; membership).
                Pratinjau di kanan memakai template yang sama persis dengan PDF-nya.
            </div>
        </div>
        <div class="c61-hero-actions">
            <button type="button" wire:click="downloadSample" wire:loading.attr="disabled" wire:target="downloadSample" class="c61-btn c61-btn-outline-cream">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v12m0 0l-4-4m4 4l4-4M5 20h14"/></svg>
                <span>Unduh Contoh PDF</span>
            </button>
            @if (! $ro)
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save,logoUpload" class="c61-btn c61-btn-cream c61-btn-lg">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M5 13l4 4L19 7"/></svg>
                    <span>Simpan Template</span>
                </button>
            @endif
        </div>
    </div>

    @if ($ro)
        <div class="c61-card" style="margin-bottom:1rem;">
            <div class="c61-card-body" style="font-size:0.8125rem; color:var(--c-muted);">
                Anda hanya bisa melihat &amp; mengunduh contoh. Mengubah template butuh izin <strong>Ubah Logo, Identitas Usaha &amp; Kalimat Template Invoice</strong>.
            </div>
        </div>
    @endif

    <div class="ti-grid">
        {{-- ================= ISIAN ================= --}}
        <div class="ti-col">
            {{-- Logo & warna --}}
            <div class="c61-card">
                <div class="c61-card-head">
                    <div>
                        <div class="c61-card-title">Logo &amp; warna</div>
                        <div class="c61-card-sub">Logo di kiri atas invoice; warna aksen untuk garis atas, judul &amp; total.</div>
                    </div>
                </div>
                <div class="c61-card-body ti-body">
                    <div class="ti-logo">
                        <div class="ti-logo-box">
                            @if ($logoUpload && ! $errors->has('logoUpload'))
                                <img src="{{ $logoUpload->temporaryUrl() }}" alt="Logo baru">
                            @elseif ($logo = ($removeLogo ? (new \App\Models\Setting\InvoiceTemplateSetting)->logoDataUri() : $savedLogo->logoDataUri()))
                                <img src="{{ $logo }}" alt="Logo invoice">
                            @endif
                        </div>
                        <div class="ti-logo-meta">
                            <div class="ti-logo-state">
                                @if ($logoUpload && ! $errors->has('logoUpload')) Logo baru — belum disimpan
                                @elseif ($this->hasCustomLogo) Logo unggahan
                                @else Logo bawaan Club 61
                                @endif
                            </div>
                            @if (! $ro)
                                <div class="ti-logo-actions">
                                    <label class="c61-btn c61-btn-ghost c61-btn-sm ti-file">
                                        <span wire:loading.remove wire:target="logoUpload">Ganti logo</span>
                                        <span wire:loading wire:target="logoUpload">Mengunggah…</span>
                                        <input type="file" wire:model="logoUpload" accept="image/png,image/jpeg,image/webp" aria-label="Pilih file logo">
                                    </label>
                                    @if ($logoUpload || $this->hasCustomLogo)
                                        <button type="button" class="c61-btn c61-btn-ghost c61-btn-sm" wire:click="useDefaultLogo">Pakai bawaan</button>
                                    @endif
                                </div>
                            @endif
                            <div class="c61-hint" style="margin-top:0;">PNG transparan paling rapi. JPG / WEBP juga bisa, maks 2 MB.</div>
                        </div>
                    </div>
                    @error('logoUpload')<div class="ti-err">{{ $message }}</div>@enderror

                    <div>
                        <span class="c61-label">Warna aksen</span>
                        <div class="ti-swatches" role="radiogroup" aria-label="Warna aksen">
                            @foreach (\App\Models\Setting\InvoiceTemplateSetting::ACCENTS as $key => $accent)
                                <label class="ti-swatch {{ $form['accent'] === $key ? 'is-active' : '' }}" style="position:relative;">
                                    <input type="radio" wire:model.live="form.accent" value="{{ $key }}" @disabled($ro)>
                                    <span class="dot" style="background: {{ $accent['hex'] }};"></span>
                                    {{ $accent['label'] }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- Identitas usaha --}}
            <div class="c61-card">
                <div class="c61-card-head">
                    <div>
                        <div class="c61-card-title">Identitas usaha</div>
                        <div class="c61-card-sub">Bagian "Diterbitkan oleh". Kosongkan untuk memakai data Konten Website.</div>
                    </div>
                </div>
                <div class="c61-card-body ti-body">
                    <div>
                        <label class="c61-label" for="ti-business">Nama usaha</label>
                        <input id="ti-business" type="text" class="c61-input" wire:model.live.debounce.500ms="form.business_name" placeholder="{{ $d['business_name'] }}" maxlength="120" @disabled($ro)>
                        @error('form.business_name')<div class="ti-err">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="c61-label" for="ti-address">Alamat</label>
                        <textarea id="ti-address" rows="2" class="c61-textarea" wire:model.live.debounce.500ms="form.address" placeholder="{{ $d['address'] }}" maxlength="255" @disabled($ro)></textarea>
                        @error('form.address')<div class="ti-err">{{ $message }}</div>@enderror
                    </div>
                    <div class="ti-two">
                        <div>
                            <label class="c61-label" for="ti-phone">Telepon / WhatsApp</label>
                            <input id="ti-phone" type="text" class="c61-input" wire:model.live.debounce.500ms="form.phone" placeholder="{{ $d['phone'] ?: '+62 …' }}" maxlength="40" @disabled($ro)>
                            @error('form.phone')<div class="ti-err">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="c61-label" for="ti-email">Email</label>
                            <input id="ti-email" type="email" class="c61-input" wire:model.live.debounce.500ms="form.email" placeholder="billing@…" maxlength="120" @disabled($ro)>
                            @error('form.email')<div class="ti-err">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="c61-label" for="ti-website">Website</label>
                            <input id="ti-website" type="text" class="c61-input" wire:model.live.debounce.500ms="form.website" placeholder="{{ $d['website'] ?: 'club61.id' }}" maxlength="120" @disabled($ro)>
                            @error('form.website')<div class="ti-err">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="c61-label" for="ti-tax">NPWP <span style="font-weight:500; color:var(--c-faint);">(opsional)</span></label>
                            <input id="ti-tax" type="text" class="c61-input" wire:model.live.debounce.500ms="form.tax_id" placeholder="00.000.000.0-000.000" maxlength="40" @disabled($ro)>
                            @error('form.tax_id')<div class="ti-err">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </div>
            </div>

            {{-- Teks dokumen --}}
            <div class="c61-card">
                <div class="c61-card-head">
                    <div>
                        <div class="c61-card-title">Teks dokumen</div>
                        <div class="c61-card-sub">Judul, label status &amp; kalimat yang tercetak di invoice.</div>
                    </div>
                </div>
                <div class="c61-card-body ti-body">
                    <div class="ti-two">
                        <div>
                            <label class="c61-label" for="ti-title">Judul dokumen</label>
                            <input id="ti-title" type="text" class="c61-input" wire:model.live.debounce.500ms="form.document_title" placeholder="{{ $d['document_title'] }}" maxlength="40" @disabled($ro)>
                            @error('form.document_title')<div class="ti-err">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="c61-label" for="ti-status">Label status</label>
                            <input id="ti-status" type="text" class="c61-input" wire:model.live.debounce.500ms="form.status_label" placeholder="{{ $d['status_label'] }}" maxlength="30" @disabled($ro)>
                            @error('form.status_label')<div class="ti-err">{{ $message }}</div>@enderror
                        </div>
                    </div>
                    <div>
                        <label class="c61-label" for="ti-intro">Kalimat pembuka <span style="font-weight:500; color:var(--c-faint);">(opsional)</span></label>
                        <textarea id="ti-intro" rows="2" class="c61-textarea" wire:model.live.debounce.500ms="form.intro_text" placeholder="Mis. Terima kasih telah bermain di Club 61. Berikut rincian pembayaran Anda." maxlength="600" @disabled($ro)></textarea>
                        @error('form.intro_text')<div class="ti-err">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="c61-label" for="ti-notes">Catatan / ketentuan <span style="font-weight:500; color:var(--c-faint);">(opsional)</span></label>
                        <textarea id="ti-notes" rows="4" class="c61-textarea" wire:model.live.debounce.500ms="form.notes_text" placeholder="Mis. ketentuan reschedule, cara check-in di front desk." maxlength="1200" @disabled($ro)></textarea>
                        <div class="c61-hint">Satu baris = satu baris di invoice.</div>
                        @error('form.notes_text')<div class="ti-err">{{ $message }}</div>@enderror
                    </div>
                    <div>
                        <label class="c61-label" for="ti-footer">Kalimat penutup (kaki halaman)</label>
                        <input id="ti-footer" type="text" class="c61-input" wire:model.live.debounce.500ms="form.footer_text" placeholder="{{ $d['footer_text'] }}" maxlength="255" @disabled($ro)>
                        @error('form.footer_text')<div class="ti-err">{{ $message }}</div>@enderror
                    </div>
                </div>

                @if (! $ro)
                    <div class="ti-savebar">
                        <div class="ti-savebar-text">Berlaku untuk invoice lunas berikutnya. Invoice yang sudah terkirim tidak berubah.</div>
                        <div class="ti-savebar-actions">
                            <button type="button" class="c61-btn c61-btn-ghost"
                                x-on:click="$dispatch('club61-confirm', {
                                    title: 'Kembalikan template ke bawaan?',
                                    message: 'Semua isian & logo unggahan dihapus, invoice kembali memakai teks dan logo bawaan Club 61.',
                                    confirmLabel: 'Ya, kembalikan',
                                    onConfirm: () => $wire.resetToDefaults(),
                                })">Kembalikan bawaan</button>
                            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save,logoUpload" class="c61-btn c61-btn-primary">Simpan Template</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ================= PRATINJAU A4 ================= --}}
        <div class="ti-preview">
            <div class="ti-stage">
                <div class="ti-stage-head">
                    <div>
                        <div class="ti-stage-title">Pratinjau</div>
                        <div class="ti-stage-sub">Data contoh &middot; A4 &middot; ikut berubah saat Anda mengetik</div>
                    </div>
                    <span class="c61-pill c61-pill-cream" wire:loading.delay wire:target="form,logoUpload,removeLogo">Memperbarui…</span>
                </div>
                <div class="ti-paper"
                    x-data="{ scale: 1 }"
                    x-init="const fit = () => { scale = $el.clientWidth / 794 }; fit(); new ResizeObserver(fit).observe($el)"
                    x-bind:style="'height:' + (1123 * scale) + 'px'">
                    <iframe title="Pratinjau invoice" sandbox="" loading="lazy" x-bind:style="'transform: scale(' + scale + ')'"
                        srcdoc="{{ $this->previewHtml }}"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>
