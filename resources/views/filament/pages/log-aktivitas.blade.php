<x-filament-panels::page>
    <div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-start; padding:0.9rem 1.1rem; background:#FFFDF7; border:1.5px solid #DFC387; border-radius:14px; color:#5C410F; font-size:0.8rem; line-height:1.5;">
        <div style="flex:1 1 320px;">
            <div style="font-weight:800; color:#1F170D; font-size:0.85rem;">Jejak semua aktivitas staf, customer & sistem</div>
            Transaksi lunas, refund, reschedule, check-in, buka/tutup shift, perubahan harga & menu, perubahan user/role, dan login.
            Klik baris untuk melihat detail perubahan <b>sebelum &rarr; sesudah</b>.
        </div>
        <div style="flex:0 1 280px; font-size:0.75rem; color:#7A643E;">
            Log bersifat <b>permanen &amp; hanya-baca</b> — tidak bisa diedit atau dihapus dari aplikasi oleh siapa pun.
            Disimpan {{ (int) config('audit.retention_months', 24) }} bulan.
        </div>
    </div>

    {{ $this->table }}
</x-filament-panels::page>
