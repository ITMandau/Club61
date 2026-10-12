{{-- Pesan setelah form kedaluwarsa (419) dikembalikan ke halamannya — lihat bootstrap/app.php. --}}
@if (session('session_expired'))
    <div role="alert"
         style="position: fixed; top: 16px; left: 50%; transform: translateX(-50%); z-index: 9999; width: calc(100% - 32px); max-width: 560px; display: flex; gap: 12px; align-items: flex-start; padding: 14px 16px; border-radius: 14px; background: #FFFBEB; border: 1px solid #F59E0B; color: #4F2F2A; box-shadow: 0 18px 40px -18px rgba(79, 47, 42, .55); font-size: 14px; line-height: 1.5;">
        <svg width="20" height="20" fill="none" stroke="#B45309" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink: 0; margin-top: 1px;" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2.5 2.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        <div style="flex: 1;">
            <strong style="display: block; margin-bottom: 2px;">Sesi halaman sudah habis</strong>
            {{ session('session_expired') }}
        </div>
        <button type="button" onclick="this.parentElement.remove()" aria-label="Tutup"
                style="flex-shrink: 0; background: none; border: 0; font-size: 20px; line-height: 1; color: #7A5A52; cursor: pointer; padding: 0 2px;">&times;</button>
    </div>
@endif
