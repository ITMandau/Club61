{{--
    Pratinjau Rekap Penjualan Harian / X-Report (POS Walk-In, Jual Membership, F&B) — App\Livewire\Concerns\PrintsDailySalesRecap.
    Isi #printable-sales-recap (pos.partials.sales-recap-body) dicetak ke printer struk 58mm lewat club61PrintReceipt.
--}}
@if ($showSalesRecapModal && $salesRecapData)
    <div wire:key="sales-recap-modal" style="position:fixed; inset:0; z-index:9999; background:rgba(15,23,42,0.65); backdrop-filter:blur(6px); display:flex; align-items:center; justify-content:center; padding:1rem;">
        <div role="dialog" aria-modal="true" aria-labelledby="sales-recap-title" style="background:#FFFFFF; border:1.5px solid #E6DAC0; border-radius:18px; width:100%; max-width:440px; max-height:92vh; display:flex; flex-direction:column; overflow:hidden;">
            <div style="background:#FCF8EE; border-bottom:1.5px solid #E6DAC0; padding:0.8rem 1.1rem; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-size:0.625rem; font-weight:900; text-transform:uppercase; letter-spacing:0.06em; color:#662721;">
                        {{ $salesRecapData['counter_label'] }} &bull; {{ $salesRecapData['still_open'] ? 'Shift berjalan' : 'Semua shift tutup' }}
                    </div>
                    <div id="sales-recap-title" style="font-size:1rem; font-weight:900; color:#4F2F2A;">Rekap Penjualan Harian (X-Report)</div>
                </div>
                <button type="button" wire:click="closeSalesRecap" aria-label="Tutup" style="background:none; border:none; font-size:1.25rem; color:#78350F; cursor:pointer; line-height:1; padding:0.25rem;">&times;</button>
            </div>

            <div style="padding:1rem 1.1rem; overflow-y:auto;">
                @include('pos.partials.sales-recap-body', ['recap' => $salesRecapData])
            </div>

            <div style="background:#FCF8EE; border-top:1px solid #E6DAC0; padding:0.75rem 1.1rem; display:flex; justify-content:flex-end; gap:0.5rem;">
                <button type="button" wire:click="closeSalesRecap" style="padding:0.45rem 0.9rem; font-size:0.8125rem; font-weight:800; border:1.5px solid #E6DAC0; background:#FFFFFF; border-radius:8px; cursor:pointer; color:#4F2F2A;">Tutup</button>
                <button type="button" onclick="club61PrintReceipt('#printable-sales-recap')" style="padding:0.45rem 1.1rem; font-size:0.8125rem; font-weight:900; background:#662721; color:#FFFFFF; border:none; border-radius:8px; cursor:pointer;">Cetak Rekap</button>
            </div>
        </div>
    </div>
@endif
