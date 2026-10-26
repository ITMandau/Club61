{{-- Tombol "Cetak Rekap" (Rekap Penjualan Harian / X-Report) — hanya untuk pemegang izin print_sales_recap; server tetap
     mengecek izinnya lagi di openSalesRecap (App\Livewire\Concerns\PrintsDailySalesRecap). --}}
@if ($this->canPrintSalesRecap)
    <button type="button" wire:click="openSalesRecap" wire:loading.attr="disabled" wire:target="openSalesRecap"
        title="Rekap penjualan metode bayar dari shift pertama hari itu sampai sekarang"
        style="display:inline-flex; align-items:center; gap:0.4rem; height:34px; padding:0 0.85rem; border-radius:8px; border:1.5px solid #662721; background:#FFFFFF; color:#662721; font-size:0.75rem; font-weight:800; cursor:pointer; white-space:nowrap;">
        <svg style="width:15px; height:15px; flex-shrink:0;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
        </svg>
        Cetak Rekap
    </button>
@endif
