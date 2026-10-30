<div>
    @php
        $rp = fn ($v) => \App\Filament\Pages\BukuTransaksi::rupiah($v);
        $user = auth()->user();
        $can = fn (string $p) => $user?->can($p);
        $statusPill = [
            'MINUS' => ['c61-pill-danger', 'Minus'],
            'EMPTY' => ['c61-pill-danger', 'Habis'],
            'LOW' => ['c61-pill-warn', 'Menipis'],
            'OK' => ['c61-pill-ok', 'Aman'],
        ];
    @endphp

    <div class="c61 sf-root" style="gap: 1rem;">
        @include('filament.partials.c61-admin-style')
        <style>
            .sf-menu-link { background: none; border: 0; padding: 0; font: inherit; font-weight: 700; color: var(--c-terra); text-decoration: underline; text-underline-offset: 3px; text-decoration-color: var(--c-line); cursor: pointer; text-align: left; }
            .sf-menu-link:hover { text-decoration-color: var(--c-terra); }
            .sf-root .sf-stock { font-family: var(--font-mono); font-weight: 800; font-size: 0.9375rem; }
            .sf-root .sf-stock.is-minus { color: #B42318; }
            .sf-root .sf-filters { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; }
            .sf-root .sf-filters .c61-select, .sf-root .sf-filters .c61-input { width: auto; min-width: 160px; }
            .sf-change { font-family: var(--font-mono); font-weight: 800; }
            .sf-change.is-plus { color: #047857; }
            .sf-change.is-minus { color: #B42318; }
            .sf-voided { text-decoration: line-through; opacity: 0.6; }
            .sf-modal-wide { max-width: 900px !important; }
            .sf-modal-xl { max-width: 1180px !important; }
            /* Form di dalam modal ikut tata letak modal: isi bisa digulir, tombol bawah selalu terlihat. */
            .c61-modal > form { display: flex; flex-direction: column; flex: 1; min-height: 0; }
            .c61-modal-body > * { flex-shrink: 0; }
            .sf-compact th, .sf-compact td { padding: 0.55rem 0.6rem; }
            .sf-opname-row { display: grid; grid-template-columns: minmax(0, 1fr) 70px 100px 70px minmax(0, 1.2fr); gap: 0.6rem; align-items: center; padding: 0.45rem 0; border-bottom: 1px solid var(--c-line-soft); font-size: 0.8125rem; }
            .sf-opname-row.is-head { font-size: 0.625rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: var(--c-muted); }
            .sf-opname-row .num { text-align: right; font-family: var(--font-mono); font-weight: 700; }
            .sf-line { display: grid; grid-template-columns: minmax(0, 1fr) 110px 150px 110px 34px; gap: 0.5rem; align-items: start; padding: 0.4rem 0; border-bottom: 1px solid var(--c-line-soft); }
            .sf-line.is-head { font-size: 0.625rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: var(--c-muted); align-items: center; }
            .sf-line .c61-input, .sf-line .c61-select { height: 36px; }
            .sf-line .num { text-align: right; font-family: var(--font-mono); font-weight: 700; font-size: 0.8125rem; padding-top: 0.55rem; }
            .sf-adj { display: grid; grid-template-columns: minmax(0, 1fr) 165px 95px 200px 34px; gap: 0.5rem; align-items: start; padding: 0.4rem 0; border-bottom: 1px solid var(--c-line-soft); }
            .sf-adj.is-head { font-size: 0.625rem; font-weight: 800; letter-spacing: 0.08em; text-transform: uppercase; color: var(--c-muted); align-items: center; }
            .sf-adj .c61-input, .sf-adj .c61-select { height: 36px; }
            .sf-grid-3 { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0 0.85rem; }
            .sf-grid-2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 0.85rem; }
            .sf-radio { display: flex; gap: 0.5rem; flex-wrap: wrap; }
            .sf-radio label { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.45rem 0.75rem; border: 1px solid var(--c-line); border-radius: 10px; cursor: pointer; font-weight: 700; font-size: 0.8125rem; }
            .sf-error { color: #B42318; font-size: 0.6875rem; font-weight: 700; margin-top: 0.3rem; display: block; }
            .sf-total { display: flex; justify-content: flex-end; gap: 1rem; font-weight: 800; padding-top: 0.6rem; font-size: 0.875rem; }
            .sf-total span:last-child { font-family: var(--font-mono); }
            @media (max-width: 760px) {
                .sf-grid-2, .sf-grid-3 { grid-template-columns: 1fr; }
                .sf-opname-row { grid-template-columns: minmax(0, 1fr) 60px 90px 60px; }
                .sf-opname-row > .sf-opname-note { grid-column: 1 / -1; }
                .sf-opname-row.is-head > :last-child { display: none; }
                .sf-line { grid-template-columns: minmax(0, 1fr) 80px 110px 34px; }
                .sf-line > .num, .sf-line > .sf-line-unit { display: none; }
                .sf-adj { grid-template-columns: minmax(0, 1fr) 1fr; }
                .sf-adj.is-head { display: none; }
            }
        </style>

        <div class="c61-hero">
            <div>
                <div class="c61-eyebrow">F&amp;B · Inventaris</div>
                <div class="c61-hero-title">Stok F&amp;B</div>
                <div class="c61-hero-sub">Stok berkurang otomatis ketika pesanan kasir F&amp;B lunas dan dapat bernilai minus. Barang yang diterima dari supplier dicatat melalui Penerimaan Barang, kejadian lain melalui Penyesuaian Stok, dan perhitungan fisik melalui Stok Opname.</div>
            </div>
            {{-- Satu aksi utama (paling sering dipakai) + menu "Lainnya" untuk aksi yang jarang. --}}
            <div class="c61-hero-actions">
                @if ($can('restock_fnb_stock'))
                    <button type="button" wire:click="openModal('receipt')" class="c61-btn c61-btn-cream c61-btn-lg">+ Penerimaan Barang</button>
                @endif
                @if ($can('adjust_fnb_stock') || $can('opname_fnb_stock'))
                    <x-filament::dropdown placement="bottom-end" teleport>
                        <x-slot name="trigger">
                            <button type="button" class="c61-btn c61-btn-outline-cream c61-btn-lg">
                                Lainnya
                                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-width="2.5" d="m6 9 6 6 6-6"/></svg>
                            </button>
                        </x-slot>
                        <x-filament::dropdown.list>
                            @if ($can('adjust_fnb_stock'))
                                <x-filament::dropdown.list.item icon="heroicon-o-arrows-right-left" wire:click="openModal('adjust')" x-on:click="close()">Penyesuaian Stok</x-filament::dropdown.list.item>
                            @endif
                            @if ($can('opname_fnb_stock'))
                                <x-filament::dropdown.list.item icon="heroicon-o-clipboard-document-check" wire:click="openModal('opname')" x-on:click="close()">Stok Opname</x-filament::dropdown.list.item>
                            @endif
                        </x-filament::dropdown.list>
                    </x-filament::dropdown>
                @endif
            </div>
        </div>

        <div class="c61-kpis">
            <div class="c61-kpi">
                <div class="c61-kpi-label">Persediaan Dilacak</div>
                <div class="c61-kpi-value">{{ $kpi['tracked'] }} <small>menu</small></div>
                <div class="c61-kpi-foot"><span>Menu lainnya hanya menggunakan HPP</span></div>
            </div>
            <div class="c61-kpi">
                <div class="c61-kpi-label">Stok Menipis</div>
                <div class="c61-kpi-value" style="color: #92400E;">{{ $kpi['low'] }} <small>menu</small></div>
                <div class="c61-kpi-foot"><span>Sama dengan / di bawah stok minimum</span></div>
            </div>
            <div class="c61-kpi">
                <div class="c61-kpi-label">Habis / Minus</div>
                <div class="c61-kpi-value" style="color: #B42318;">{{ $kpi['empty'] }} <small>menu</small></div>
                <div class="c61-kpi-foot"><span>Catat Penerimaan Barang atau Stok Opname</span></div>
            </div>
            <div class="c61-kpi">
                <div class="c61-kpi-label">Nilai Stok (HPP)</div>
                <div class="c61-kpi-value is-terra" style="font-size: 1.4rem;">{{ $rp($kpi['value']) }}</div>
                <div class="c61-kpi-foot"><span>Stok × HPP menu yang dilacak</span></div>
            </div>
        </div>

        <div class="c61-toolbar">
            <div class="c61-seg">
                <button type="button" wire:click="setTab('stock')" class="c61-seg-btn {{ $tab === 'stock' ? 'is-active' : '' }}">Daftar Persediaan</button>
                <button type="button" wire:click="setTab('receipts')" class="c61-seg-btn {{ $tab === 'receipts' ? 'is-active' : '' }}">Penerimaan Barang</button>
                <button type="button" wire:click="setTab('history')" class="c61-seg-btn {{ $tab === 'history' ? 'is-active' : '' }}">Riwayat Stok</button>
            </div>
            <div class="sf-filters">
                <input type="search" wire:model.live.debounce.400ms="search" class="c61-input" placeholder="{{ $tab === 'receipts' ? 'Cari no. penerimaan / supplier / nota' : 'Cari menu' }}">
                @if ($tab === 'stock')
                    <select wire:model.live="categoryFilter" class="c61-select">
                        <option value="ALL">Semua Kategori</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="statusFilter" class="c61-select">
                        @foreach (\App\Filament\Pages\StokFnb::STATUS_FILTERS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                @else
                    @if ($tab === 'history')
                        <select wire:model.live="historyType" class="c61-select">
                            <option value="ALL">Semua Jenis</option>
                            @foreach (\App\Models\Fnb\FnbStockMovement::TYPES as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @endif
                    <select wire:model.live="historyPreset" class="c61-select">
                        @foreach (\App\Services\Finance\LedgerReport::PRESETS as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @if ($historyPreset === 'kustom')
                        <input type="date" wire:model.live="historyFrom" class="c61-input">
                        <input type="date" wire:model.live="historyUntil" class="c61-input">
                    @endif
                @endif
            </div>
        </div>

        @if ($tab === 'stock')
            <div class="c61-card">
                <div class="c61-table-wrap">
                    <table class="c61-table">
                        <thead>
                            <tr>
                                <th>Menu</th>
                                <th style="text-align: right;">Harga Jual</th>
                                <th style="text-align: right;">HPP</th>
                                <th style="text-align: right;">Margin</th>
                                <th style="text-align: right;">Stok</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($menus as $menu)
                                @php
                                    $margin = (float) $menu->base_price - (float) $menu->hpp;
                                    $pct = (float) $menu->base_price > 0 ? round($margin / (float) $menu->base_price * 100) : 0;
                                    $status = $menu->stockStatus();
                                @endphp
                                <tr wire:key="sf-menu-{{ $menu->id }}">
                                    <td>
                                        @if ($menu->track_stock)
                                            {{-- Klik nama menu → Kartu Stok (riwayat pergerakan). Semua aksi stok ada di tombol atas. --}}
                                            <button type="button" wire:click="openModal('card', '{{ $menu->id }}')" class="sf-menu-link" title="Lihat Kartu Stok">{{ $menu->name }}</button>
                                        @else
                                            <div class="strong">{{ $menu->name }}</div>
                                        @endif
                                        <div class="sub">{{ $menu->category?->name }}{{ $menu->is_available ? '' : ' · tidak dijual' }}</div>
                                    </td>
                                    <td class="num">{{ $rp($menu->base_price) }}</td>
                                    <td class="num">
                                        @if ((float) $menu->hpp > 0)
                                            {{ $rp($menu->hpp) }}
                                        @else
                                            <span class="c61-pill c61-pill-warn">Belum diisi</span>
                                        @endif
                                    </td>
                                    <td class="num">{{ $rp($margin) }} <div class="sub">{{ $pct }}%</div></td>
                                    <td class="num">
                                        @if ($menu->track_stock)
                                            <span class="sf-stock {{ $menu->stock_qty < 0 ? 'is-minus' : '' }}">{{ $menu->stock_qty }}</span>
                                            <div class="sub">min. {{ $menu->min_stock ?? '-' }}</div>
                                        @else
                                            <span class="sub">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($status)
                                            <span class="c61-pill {{ $statusPill[$status][0] }}">{{ $statusPill[$status][1] }}</span>
                                        @else
                                            <span class="c61-pill c61-pill-gray" title="Dibuat sesuai pesanan — atur di Kelola Menu F&B">Tidak dilacak</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="c61-empty">Tidak ada menu yang sesuai dengan filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="c61-card-foot">
                    <span class="c61-hint" style="margin: 0;">Klik nama menu untuk melihat Kartu Stok. HPP, Lacak Persediaan, dan Stok Minimum diatur pada data menu (F&amp;B → Menu F&amp;B).</span>
                </div>
            </div>
        @elseif ($tab === 'receipts')
            <div class="c61-card">
                <div class="c61-table-wrap">
                    <table class="c61-table">
                        <thead>
                            <tr>
                                <th>No. Penerimaan</th>
                                <th>Waktu</th>
                                <th>Supplier</th>
                                <th>No. Nota</th>
                                <th style="text-align: right;">Item</th>
                                <th style="text-align: right;">Total Pembelian</th>
                                <th>Dicatat Oleh</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($receipts as $receipt)
                                <tr wire:key="rc-{{ $receipt->id }}">
                                    <td><span class="code">{{ $receipt->receipt_number }}</span><div class="sub">{{ \App\Models\Fnb\FnbStockReceipt::SOURCES[$receipt->source] ?? $receipt->source }}</div></td>
                                    <td style="white-space: nowrap;">{{ $receipt->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</td>
                                    <td>{{ $receipt->supplier ?: '-' }}</td>
                                    <td>{{ $receipt->invoice_number ?: '-' }}</td>
                                    <td class="num">{{ $receipt->item_count }}</td>
                                    <td class="num"><span class="{{ $receipt->voided_at ? 'sf-voided' : '' }}">{{ $rp($receipt->total_amount) }}</span></td>
                                    <td>{{ $receipt->creator?->name ?? '-' }}</td>
                                    <td>
                                        @if ($receipt->voided_at)
                                            <span class="c61-pill c61-pill-danger" title="{{ $receipt->void_reason }}">Dibatalkan</span>
                                        @else
                                            <span class="c61-pill c61-pill-ok">Diterima</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="c61-actions">
                                            <button type="button" wire:click="openReceipt('{{ $receipt->id }}')" class="c61-btn c61-btn-ghost c61-btn-sm">Rincian</button>
                                            @if (! $receipt->voided_at && $can('adjust_fnb_stock'))
                                                <button type="button" wire:click="openVoid('{{ $receipt->id }}')" class="c61-btn c61-btn-ghost c61-btn-sm">Batalkan</button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="9" class="c61-empty">Belum ada Penerimaan Barang pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($receipts->hasPages())
                    <div class="c61-card-foot">
                        <span class="c61-hint" style="margin: 0;">Halaman {{ $receipts->currentPage() }} dari {{ $receipts->lastPage() }} · {{ $receipts->total() }} penerimaan</span>
                        <div class="c61-pages">
                            <button type="button" wire:click="previousPage" @disabled($receipts->onFirstPage()) class="c61-page">&larr;</button>
                            <button type="button" wire:click="nextPage" @disabled(! $receipts->hasMorePages()) class="c61-page">&rarr;</button>
                        </div>
                    </div>
                @endif
            </div>
        @else
            <div class="c61-card">
                <div class="c61-table-wrap">
                    <table class="c61-table">
                        <thead>
                            <tr>
                                <th>Waktu</th>
                                <th>Menu</th>
                                <th>Jenis</th>
                                <th style="text-align: right;">Perubahan</th>
                                <th style="text-align: right;">Stok Akhir</th>
                                <th>Keterangan</th>
                                <th>Oleh</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($history as $mv)
                                @include('filament.pages.partials.fnb-stock-row', ['mv' => $mv, 'showMenu' => true])
                            @empty
                                <tr><td colspan="7" class="c61-empty">Belum ada pergerakan stok pada periode ini.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($history->hasPages())
                    <div class="c61-card-foot">
                        <span class="c61-hint" style="margin: 0;">Halaman {{ $history->currentPage() }} dari {{ $history->lastPage() }} · {{ $history->total() }} catatan</span>
                        <div class="c61-pages">
                            <button type="button" wire:click="previousPage" @disabled($history->onFirstPage()) class="c61-page">&larr;</button>
                            <button type="button" wire:click="nextPage" @disabled(! $history->hasMorePages()) class="c61-page">&rarr;</button>
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    {{-- ===== Modal Penerimaan Barang: tab Input Manual (satu nota, banyak menu) & Impor Excel ===== --}}
    @if (in_array($modal, ['receipt', 'import'], true))
        <div class="c61-modal-backdrop" wire:click.self="closeModal">
            <div class="c61-modal {{ $modal === 'import' ? 'sf-modal-xl' : 'sf-modal-wide' }}">
                <div class="c61-modal-head">
                    <div>
                        <div class="c61-modal-eyebrow">Stok F&amp;B</div>
                        <div class="c61-modal-title">Penerimaan Barang</div>
                    </div>
                    <button type="button" wire:click="closeModal" class="c61-modal-close">&times;</button>
                </div>
                <form wire:submit="{{ $modal === 'import' ? 'saveImport' : 'saveReceipt' }}">
                    <div class="c61-modal-body">
                        <div class="c61-seg" style="align-self: flex-start;">
                            <button type="button" wire:click="openModal('receipt')" class="c61-seg-btn {{ $modal === 'receipt' ? 'is-active' : '' }}">Input Manual</button>
                            <button type="button" wire:click="openModal('import')" class="c61-seg-btn {{ $modal === 'import' ? 'is-active' : '' }}">Impor Excel</button>
                        </div>

                        @if ($modal === 'receipt')
                            @php $lineTotal = collect($lines)->sum(fn ($l) => is_numeric($l['qty'] ?? null) && is_numeric($l['price'] ?? null) ? (int) $l['qty'] * (float) $l['price'] : 0); @endphp
                            <div class="sf-grid-3">
                                <div class="c61-field" style="margin: 0;">
                                    <label class="c61-label">Supplier</label>
                                    <input type="text" wire:model="supplier" maxlength="100" class="c61-input" placeholder="Opsional">
                                </div>
                                <div class="c61-field" style="margin: 0;">
                                    <label class="c61-label">No. Nota / Faktur</label>
                                    <input type="text" wire:model="invoiceNumber" maxlength="60" class="c61-input" placeholder="Opsional">
                                </div>
                                <div class="c61-field" style="margin: 0;">
                                    <label class="c61-label">Catatan</label>
                                    <input type="text" wire:model="notes" maxlength="500" class="c61-input" placeholder="Opsional">
                                </div>
                            </div>

                            @if ($receiptMenus->isEmpty())
                                <div class="c61-note c61-note-warn">Belum ada menu terdaftar. Tambahkan melalui F&amp;B → Menu F&amp;B.</div>
                            @else
                                <div>
                                    <div class="sf-line is-head"><span>Menu</span><span>Jumlah Diterima</span><span>Harga Beli per Satuan</span><span class="sf-line-unit" style="text-align: right;">Total</span><span></span></div>
                                    @foreach ($lines as $i => $line)
                                        <div class="sf-line" wire:key="line-{{ $i }}">
                                            <div>
                                                <select wire:model="lines.{{ $i }}.menu_id" class="c61-select">
                                                    <option value="">Pilih menu</option>
                                                    @foreach ($receiptMenus as $m)
                                                        <option value="{{ $m->id }}">{{ $m->name }} ({{ $m->track_stock ? 'stok '.$m->stock_qty : 'belum dilacak' }} · HPP {{ $rp($m->hpp) }})</option>
                                                    @endforeach
                                                </select>
                                                @error('lines.'.$i.'.menu_id') <span class="sf-error">{{ $message }}</span> @enderror
                                            </div>
                                            <div>
                                                <input type="number" min="1" wire:model.blur="lines.{{ $i }}.qty" class="c61-input" placeholder="0">
                                                @error('lines.'.$i.'.qty') <span class="sf-error">{{ $message }}</span> @enderror
                                            </div>
                                            <div>
                                                <input type="number" min="0" wire:model.blur="lines.{{ $i }}.price" class="c61-input" placeholder="0">
                                                @error('lines.'.$i.'.price') <span class="sf-error">{{ $message }}</span> @enderror
                                            </div>
                                            <span class="num sf-line-unit">{{ is_numeric($line['qty'] ?? null) && is_numeric($line['price'] ?? null) ? $rp((int) $line['qty'] * (float) $line['price']) : '-' }}</span>
                                            <button type="button" wire:click="removeLine({{ $i }})" class="c61-icon-btn is-danger" title="Hapus baris" style="width: 34px; height: 36px;">&times;</button>
                                        </div>
                                    @endforeach
                                    <div class="c61-row" style="justify-content: space-between; margin-top: 0.6rem;">
                                        <button type="button" wire:click="addLine" class="c61-btn c61-btn-ghost c61-btn-sm">+ Tambah Baris</button>
                                        <div class="sf-total"><span>Total Pembelian</span><span>{{ $rp($lineTotal) }}</span></div>
                                    </div>
                                </div>
                            @endif

                            <div class="c61-note">
                                Total dihitung otomatis (jumlah × harga beli per satuan) dan tercatat di Pengeluaran F&amp;B sebagai satu catatan <b>Pembelian Persediaan</b>.
                                Isi harga 0 untuk barang titipan yang belum dibayar. Menu yang belum dilacak otomatis diaktifkan Lacak Persediaan-nya (stok dimulai dari jumlah yang diterima). HPP tidak diperbarui otomatis; sesuaikan pada data menu apabila harga pembelian berubah.
                            </div>
                        @else
                            <div class="c61-note">
                                <b>1.</b> Unduh templat — berisi <b>seluruh menu terdaftar</b> beserta ID Sistem.
                                <b>2.</b> Pada baris menu yang diperbarui, isi <i>Stok Fisik Saat Ini</i> (opsional, jumlah sebelum barang baru diterima), <i>Jumlah Diterima</i>, <i>Harga Beli per Satuan</i>, serta <i>Supplier</i> dan <i>No. Nota</i>.
                                <b>3.</b> Unggah file, periksa pratinjau, lalu simpan. Sistem menghitung total dan stok akhir; baris dengan Supplier + No. Nota yang sama menjadi satu Penerimaan Barang.
                                <div style="margin-top: 0.6rem;">
                                    <button type="button" wire:click="downloadImportTemplate" class="c61-btn c61-btn-ghost c61-btn-sm">Unduh Templat Excel</button>
                                </div>
                            </div>

                            <div class="c61-field" style="margin: 0;">
                                <label class="c61-label">File Excel (.xlsx) <small>*</small></label>
                                <input type="file" wire:model="importFile" accept=".xlsx" class="c61-input" style="padding-top: 0.45rem;">
                                <div class="c61-hint" wire:loading wire:target="importFile">Membaca file…</div>
                                @error('importFile') <span class="sf-error">{{ $message }}</span> @enderror
                            </div>

                            @if ($importPreview)
                                @if ($importPreview['fatal'])
                                    <div class="c61-note c61-note-danger">{{ $importPreview['fatal'] }}</div>
                                @else
                                    <div class="c61-note {{ $importPreview['errors'] ? 'c61-note-danger' : 'c61-note-ok' }}">
                                        {{ count($importPreview['rows']) }} baris terbaca ·
                                        @if ($importPreview['errors'])
                                            {{ $importPreview['errors'] }} baris bermasalah — perbaiki file lalu unggah kembali.
                                        @else
                                            semua baris valid: {{ $importPreview['receipts'] }} Penerimaan Barang (total pembelian {{ $rp($importPreview['purchase_total']) }}){{ $importPreview['counted'] ? ', '.$importPreview['counted'].' menu disesuaikan dengan stok fisik' : '' }}{{ $importPreview['enable'] ? ', Lacak Persediaan diaktifkan untuk '.$importPreview['enable'].' menu' : '' }}.
                                        @endif
                                    </div>
                                    <div class="c61-table-wrap" style="max-height: 320px; overflow-y: auto; border: 1px solid var(--c-line); border-radius: 12px;">
                                        <table class="c61-table sf-compact">
                                            <thead>
                                                <tr>
                                                    <th>Baris</th><th>Menu</th><th>Supplier / Nota</th>
                                                    <th style="text-align: right;">Stok Sistem</th><th style="text-align: right;">Stok Fisik</th>
                                                    <th style="text-align: right;">Diterima</th><th style="text-align: right;">Harga / Satuan</th>
                                                    <th style="text-align: right;">Total</th><th style="text-align: right;">Stok Akhir</th><th>Keterangan</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($importPreview['rows'] as $row)
                                                    <tr>
                                                        <td class="num">{{ $row['row'] }}</td>
                                                        <td class="strong">{{ $row['name'] }}</td>
                                                        <td><div class="sub" style="white-space: normal;">{{ $row['qty'] ? (($row['supplier'] ?? 'Tanpa supplier').($row['invoice'] ? ' · '.$row['invoice'] : '')) : '-' }}</div></td>
                                                        <td class="num">{{ $row['system'] }}</td>
                                                        <td class="num">{{ $row['physical'] ?? '-' }}</td>
                                                        <td class="num">{{ $row['qty'] ? '+'.$row['qty'] : '-' }}</td>
                                                        <td class="num">{{ $row['qty'] && $row['price'] !== null ? $rp($row['price']) : '-' }}</td>
                                                        <td class="num">{{ $row['qty'] ? $rp($row['total']) : '-' }}</td>
                                                        <td class="num">{{ $row['error'] ? '-' : $row['final'] }}</td>
                                                        <td>
                                                            @if ($row['error'])
                                                                <span style="color: #B42318; font-weight: 700;">{{ $row['error'] }}</span>
                                                            @elseif ($row['enable'])
                                                                <span class="c61-pill c61-pill-info">Lacak Persediaan diaktifkan</span>
                                                            @else
                                                                <span class="c61-pill c61-pill-ok">Siap</span>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            @endif

                            <div class="c61-field" style="margin: 0;">
                                <label class="c61-label">Catatan</label>
                                <input type="text" wire:model="notes" maxlength="500" class="c61-input" placeholder="Opsional, berlaku untuk seluruh penerimaan dari file ini">
                            </div>
                            @if ($can('manage_fnb_hpp'))
                                <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.75rem;">
                                    <input type="checkbox" wire:model="updateHpp" style="margin-top: 0.15rem;">
                                    <span><b>Perbarui HPP menu</b> dengan Harga Beli per Satuan pada file ini (hanya baris dengan harga lebih dari 0). Perubahan HPP berlaku untuk penjualan berikutnya.</span>
                                </label>
                            @endif
                        @endif
                    </div>
                    <div class="c61-modal-foot">
                        <button type="button" wire:click="closeModal" class="c61-btn c61-btn-ghost">Batal</button>
                        @if ($modal === 'import')
                            <button type="submit" class="c61-btn c61-btn-primary" wire:loading.attr="disabled" wire:target="saveImport"
                                @disabled(! $importPreview || $importPreview['fatal'] || $importPreview['errors'])>Simpan Impor</button>
                        @else
                            <button type="submit" class="c61-btn c61-btn-primary" wire:loading.attr="disabled" wire:target="saveReceipt" @disabled($receiptMenus->isEmpty())>Simpan Penerimaan</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ===== Modal Penyesuaian Stok (banyak menu sekaligus, satu keterangan) ===== --}}
    @if ($modal === 'adjust')
        <div class="c61-modal-backdrop" wire:click.self="closeModal">
            <div class="c61-modal sf-modal-wide">
                <div class="c61-modal-head">
                    <div>
                        <div class="c61-modal-eyebrow">Stok F&amp;B</div>
                        <div class="c61-modal-title">Penyesuaian Stok</div>
                    </div>
                    <button type="button" wire:click="closeModal" class="c61-modal-close">&times;</button>
                </div>
                <form wire:submit="saveAdjustment">
                    <div class="c61-modal-body">
                        @if ($trackedMenus->isEmpty())
                            <div class="c61-note c61-note-warn">Belum ada menu dengan persediaan dilacak.</div>
                        @else
                            <div>
                                <div class="sf-adj is-head"><span>Menu</span><span>Jenis</span><span>Jumlah</span><span>Alasan</span><span></span></div>
                                @foreach ($lines as $i => $line)
                                    <div class="sf-adj" wire:key="adj-{{ $i }}">
                                        <div>
                                            <select wire:model="lines.{{ $i }}.menu_id" class="c61-select">
                                                <option value="">Pilih menu</option>
                                                @foreach ($trackedMenus as $m)
                                                    <option value="{{ $m->id }}">{{ $m->name }} (stok {{ $m->stock_qty }})</option>
                                                @endforeach
                                            </select>
                                            @error('lines.'.$i.'.menu_id') <span class="sf-error">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <select wire:model="lines.{{ $i }}.direction" class="c61-select">
                                                <option value="OUT">Pengurangan</option>
                                                <option value="IN">Penambahan</option>
                                            </select>
                                        </div>
                                        <div>
                                            <input type="number" min="1" wire:model="lines.{{ $i }}.qty" class="c61-input" placeholder="0">
                                            @error('lines.'.$i.'.qty') <span class="sf-error">{{ $message }}</span> @enderror
                                        </div>
                                        <div>
                                            <select wire:model="lines.{{ $i }}.reason" class="c61-select">
                                                @foreach (\App\Models\Fnb\FnbStockMovement::REASONS as $key => $label)
                                                    <option value="{{ $key }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <button type="button" wire:click="removeLine({{ $i }})" class="c61-icon-btn is-danger" title="Hapus baris" style="width: 34px; height: 36px;">&times;</button>
                                    </div>
                                @endforeach
                                <div style="margin-top: 0.6rem;">
                                    <button type="button" wire:click="addLine" class="c61-btn c61-btn-ghost c61-btn-sm">+ Tambah Baris</button>
                                </div>
                            </div>
                        @endif
                        <div class="c61-field" style="margin: 0;">
                            <label class="c61-label">Keterangan <small>*</small></label>
                            <textarea wire:model="notes" rows="2" maxlength="500" class="c61-textarea" placeholder="Contoh: 2 botol pecah saat dipindahkan ke chiller; sisa kue titipan dikembalikan ke Bu Ani"></textarea>
                            @error('notes') <span class="sf-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="c61-note c61-note-warn">Pengurangan stok dihitung sebagai kerugian stok (jumlah × HPP) pada Laporan F&amp;B, kecuali alasan <b>Salah Input</b> dan <b>Dikembalikan ke Penitip</b>. Penerimaan Barang yang salah input dibatalkan melalui tab Penerimaan Barang.</div>
                    </div>
                    <div class="c61-modal-foot">
                        <button type="button" wire:click="closeModal" class="c61-btn c61-btn-ghost">Batal</button>
                        <button type="submit" class="c61-btn c61-btn-primary" wire:loading.attr="disabled" wire:target="saveAdjustment" @disabled($trackedMenus->isEmpty())>Simpan Penyesuaian</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ===== Modal Stok Opname ===== --}}
    @if ($modal === 'opname')
        <div class="c61-modal-backdrop" wire:click.self="closeModal">
            <div class="c61-modal sf-modal-wide">
                <div class="c61-modal-head">
                    <div>
                        <div class="c61-modal-eyebrow">Stok F&amp;B</div>
                        <div class="c61-modal-title">Stok Opname (Perhitungan Fisik)</div>
                    </div>
                    <button type="button" wire:click="closeModal" class="c61-modal-close">&times;</button>
                </div>
                <form wire:submit="saveOpname">
                    <div class="c61-modal-body">
                        <div class="c61-note">Isi jumlah fisik hasil perhitungan. Menu yang dikosongkan tidak ikut dihitung. Selisih dicatat otomatis pada Kartu Stok beserta catatan per menu (bila diisi). Disarankan dilakukan saat kasir tidak melayani transaksi atau setelah jam operasional.
                            <div style="margin-top: 0.6rem;">
                                <button type="button" wire:click="printOpnameSheet" class="c61-btn c61-btn-ghost c61-btn-sm">Cetak Lembar Opname (PDF)</button>
                                <span class="c61-hint">Untuk dibawa saat menghitung di gudang; stok sistem sengaja tidak dicantumkan.</span>
                            </div>
                        </div>
                        @if ($trackedMenus->isEmpty())
                            <div class="c61-empty">Belum ada menu dengan persediaan dilacak.</div>
                        @else
                            <div>
                                <div class="sf-opname-row is-head"><span>Menu</span><span class="num">Sistem</span><span class="num">Fisik</span><span class="num">Selisih</span><span>Catatan</span></div>
                                @foreach ($trackedMenus as $m)
                                    <div class="sf-opname-row" wire:key="op-{{ $m->id }}" x-data="{ v: '' }">
                                        <span class="c61-truncate strong">{{ $m->name }}</span>
                                        <span class="num">{{ $m->stock_qty }}</span>
                                        <input type="number" min="0" wire:model="counts.{{ $m->id }}" x-on:input="v = $event.target.value" class="c61-input" style="height: 34px; text-align: right;">
                                        <span class="num" x-text="v === '' ? '-' : ((v - {{ $m->stock_qty }}) > 0 ? '+' : '') + (v - {{ $m->stock_qty }})"
                                            :style="v !== '' && (v - {{ $m->stock_qty }}) < 0 ? 'color:#B42318' : (v !== '' && (v - {{ $m->stock_qty }}) > 0 ? 'color:#047857' : '')"></span>
                                        <input type="text" wire:model="countNotes.{{ $m->id }}" maxlength="200" class="c61-input sf-opname-note" style="height: 34px;" placeholder="Opsional, mis. 2 botol di gudang">
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <div class="c61-field" style="margin: 0;">
                            <label class="c61-label">Catatan Umum</label>
                            <textarea wire:model="notes" rows="2" maxlength="500" class="c61-textarea" placeholder="Opsional, berlaku untuk seluruh menu, mis. Stok opname akhir bulan Oktober"></textarea>
                        </div>
                    </div>
                    <div class="c61-modal-foot">
                        <button type="button" wire:click="closeModal" class="c61-btn c61-btn-ghost">Batal</button>
                        <button type="submit" class="c61-btn c61-btn-primary" wire:loading.attr="disabled" wire:target="saveOpname" @disabled($trackedMenus->isEmpty())>Simpan Hasil Opname</button>
                    </div>
                </form>
            </div>
        </div>
    @endif

    {{-- ===== Modal Kartu Stok ===== --}}
    @if ($modal === 'card' && $activeMenu)
        <div class="c61-modal-backdrop" wire:click.self="closeModal">
            <div class="c61-modal sf-modal-wide">
                <div class="c61-modal-head">
                    <div>
                        <div class="c61-modal-eyebrow">Kartu Stok · stok saat ini {{ $activeMenu->stock_qty }}</div>
                        <div class="c61-modal-title">{{ $activeMenu->name }}</div>
                    </div>
                    <button type="button" wire:click="closeModal" class="c61-modal-close">&times;</button>
                </div>
                <div class="c61-modal-body" style="padding: 0;">
                    <div class="c61-table-wrap">
                        <table class="c61-table">
                            <thead>
                                <tr>
                                    <th>Waktu</th>
                                    <th>Jenis</th>
                                    <th style="text-align: right;">Perubahan</th>
                                    <th style="text-align: right;">Stok Akhir</th>
                                    <th>Keterangan</th>
                                    <th>Oleh</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($cardMovements as $mv)
                                    @include('filament.pages.partials.fnb-stock-row', ['mv' => $mv, 'showMenu' => false])
                                @empty
                                    <tr><td colspan="6" class="c61-empty">Belum ada pergerakan stok.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="c61-modal-foot">
                    <span class="c61-hint" style="margin: 0 auto 0 0;">Menampilkan 100 pergerakan terakhir. Riwayat lengkap tersedia di tab Riwayat Stok.</span>
                    <button type="button" wire:click="closeModal" class="c61-btn c61-btn-ghost">Tutup</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Modal Rincian Penerimaan Barang ===== --}}
    @if ($modal === 'receipt-detail' && $activeReceipt)
        <div class="c61-modal-backdrop" wire:click.self="closeModal">
            <div class="c61-modal sf-modal-wide">
                <div class="c61-modal-head">
                    <div>
                        <div class="c61-modal-eyebrow">Penerimaan Barang · {{ $activeReceipt->created_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }}</div>
                        <div class="c61-modal-title">{{ $activeReceipt->receipt_number }}</div>
                    </div>
                    <button type="button" wire:click="closeModal" class="c61-modal-close">&times;</button>
                </div>
                <div class="c61-modal-body">
                    <div class="c61-kv">
                        <div><div class="k">Supplier</div><div class="v">{{ $activeReceipt->supplier ?: '-' }}</div></div>
                        <div><div class="k">No. Nota / Faktur</div><div class="v">{{ $activeReceipt->invoice_number ?: '-' }}</div></div>
                        <div><div class="k">Dicatat Oleh</div><div class="v">{{ $activeReceipt->creator?->name ?? '-' }}</div><div class="s">{{ \App\Models\Fnb\FnbStockReceipt::SOURCES[$activeReceipt->source] ?? '' }}</div></div>
                        <div><div class="k">Total Pembelian</div><div class="v">{{ $rp($activeReceipt->total_amount) }}</div></div>
                    </div>
                    @if ($activeReceipt->notes)
                        <div class="c61-note">{{ $activeReceipt->notes }}</div>
                    @endif
                    @if ($activeReceipt->voided_at)
                        <div class="c61-note c61-note-danger">Dibatalkan {{ $activeReceipt->voided_at->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} oleh {{ $activeReceipt->voider?->name ?? '-' }}: {{ $activeReceipt->void_reason }}</div>
                    @endif
                    <div class="c61-table-wrap" style="border: 1px solid var(--c-line); border-radius: 12px;">
                        <table class="c61-table">
                            <thead><tr><th>Menu</th><th style="text-align: right;">Jumlah Diterima</th><th style="text-align: right;">Total Harga Pembelian</th><th style="text-align: right;">Harga / Satuan</th></tr></thead>
                            <tbody>
                                @foreach ($activeReceipt->lines as $line)
                                    <tr>
                                        <td class="strong">{{ $line->menu_name }}</td>
                                        <td class="num">{{ $line->qty_change }}</td>
                                        <td class="num">{{ $rp($line->purchase_total) }}</td>
                                        <td class="num">{{ $line->qty_change > 0 ? $rp((float) $line->purchase_total / $line->qty_change) : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="c61-modal-foot">
                    <button type="button" wire:click="closeModal" class="c61-btn c61-btn-ghost">Tutup</button>
                </div>
            </div>
        </div>
    @endif

    {{-- ===== Modal Batalkan Penerimaan Barang ===== --}}
    @if ($voidTarget)
        <div class="c61-modal-backdrop" wire:click.self="cancelVoid" style="z-index: 10000;">
            <div class="c61-modal is-danger">
                <div class="c61-modal-head">
                    <div>
                        <div class="c61-modal-eyebrow">Batalkan Penerimaan Barang</div>
                        <div class="c61-modal-title">{{ $voidTarget->receipt_number }}</div>
                    </div>
                    <button type="button" wire:click="cancelVoid" class="c61-modal-close">&times;</button>
                </div>
                <form wire:submit="confirmVoid">
                    <div class="c61-modal-body">
                        <div class="c61-note c61-note-warn">
                            Stok {{ $voidTarget->item_count }} item pada penerimaan ini akan dikurangi kembali dan pengeluaran Pembelian Persediaan {{ $rp($voidTarget->total_amount) }} ikut dibatalkan. Catatan tetap tersimpan dengan tanda batal.
                        </div>
                        <div class="c61-field" style="margin: 0;">
                            <label class="c61-label">Alasan Pembatalan <small>*</small></label>
                            <textarea wire:model="voidReason" rows="2" maxlength="255" class="c61-textarea" placeholder="Contoh: jumlah barang salah input, seharusnya 24"></textarea>
                            @error('voidReason') <span class="sf-error">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="c61-modal-foot">
                        <button type="button" wire:click="cancelVoid" class="c61-btn c61-btn-ghost">Kembali</button>
                        <button type="submit" class="c61-btn c61-btn-danger" wire:loading.attr="disabled">Ya, Batalkan Penerimaan</button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>
