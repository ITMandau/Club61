{{--
    Hak akses berubah saat halaman terbuka (admin mengubah "Roles & Hak Akses") — App\Support\StaffAccess.
    - Tiap 30 detik (dan saat tab dibuka lagi) sidik jari hak akses dicek. Berubah → halaman ini dicek ulang: kalau sudah
      tidak diizinkan, dimuat ulang (server memindahkan ke halaman yang masih boleh + notifikasi); kalau masih boleh,
      cukup menu sidebar yang diperbarui (event Filament "refresh-sidebar"), isian di halaman tidak hilang.
    - Aksi Livewire ditolak (403): sama — halaman dicek; masih boleh → dialog "aksi ditolak", bukan popup 403 mentah.
    $filamentConfirm = true di panel admin (dialog Club61), selain itu kotak pesan sederhana (POS / KDS).
--}}
@auth
<script>
    (function () {
        if (window.club61AccessWatch) { return; }
        window.club61AccessWatch = true;

        var versionUrl = @js(route('staff.access-version'));
        var current = @js(\App\Support\StaffAccess::version(auth()->user()));
        var busy = false;
        var deniedShown = false;
        var deniedTitle = 'Aksi ditolak';
        var deniedMessage = 'Akun Anda tidak punya izin untuk aksi ini (hak akses diatur admin Club 61). Kalau seharusnya boleh, minta admin menambahkan izinnya.';

        // true = halaman ini masih boleh dibuka; false = sudah tidak → dimuat ulang (server mengalihkan + notifikasi).
        function pageStillAllowed() {
            // Header probe: server cukup menjawab boleh / tidak (tanpa notifikasi & log) — yang mencatat adalah muat ulangnya.
            return fetch(window.location.href, { credentials: 'same-origin', redirect: 'manual', headers: { 'Accept': 'text/html', 'X-Club61-Access-Probe': '1' } })
                .then(function (res) {
                    if (res.type === 'opaqueredirect' || res.status === 403) { window.location.reload(); return false; }
                    return true;
                })
                .catch(function () { return true; });
        }

        function check() {
            if (busy || document.visibilityState !== 'visible') { return; }
            busy = true;
            fetch(versionUrl, { credentials: 'same-origin', redirect: 'manual', headers: { 'Accept': 'application/json' } })
                .then(function (res) { return res.ok ? res.json() : null; })
                .then(function (data) {
                    if (! data || ! data.v || data.v === current) { return; }
                    current = data.v;
                    return pageStillAllowed().then(function (allowed) {
                        if (allowed && window.Livewire) { window.Livewire.dispatch('refresh-sidebar'); }
                    });
                })
                .catch(function () {})
                .finally(function () { busy = false; });
        }

        function showDenied() {
            if (deniedShown) { return; }
            deniedShown = true;
            @if ($filamentConfirm ?? false)
                window.dispatchEvent(new CustomEvent('club61-confirm', { detail: {
                    title: deniedTitle,
                    message: deniedMessage,
                    confirmLabel: 'Muat Ulang Halaman',
                    cancelLabel: 'Tutup',
                    onConfirm: function () { window.location.reload(); }
                } }));
                setTimeout(function () { deniedShown = false; }, 1500);
            @else
                var box = document.createElement('div');
                box.setAttribute('role', 'alertdialog');
                box.style.cssText = 'position:fixed;top:0;right:0;bottom:0;left:0;z-index:99999;background:rgba(30,18,15,.55);display:flex;align-items:center;justify-content:center;padding:16px;';
                box.innerHTML = '<div style="max-width:420px;width:100%;background:#FCF8EE;border:1px solid #E6DAC0;border-radius:18px;padding:22px;font-family:system-ui,sans-serif;color:#4F2F2A;box-shadow:0 24px 60px -20px rgba(0,0,0,.5)">'
                    + '<div style="font-size:18px;font-weight:800;margin-bottom:8px"></div>'
                    + '<div style="font-size:14px;line-height:1.55;color:#7A5A52;margin-bottom:18px"></div>'
                    + '<button type="button" style="width:100%;min-height:46px;border:0;border-radius:12px;background:#662721;color:#F7F0DB;font-weight:800;font-size:15px;cursor:pointer">Tutup</button>'
                    + '</div>';
                box.querySelector('div > div').textContent = deniedTitle;
                box.querySelector('div > div + div').textContent = deniedMessage;
                box.querySelector('button').onclick = function () { box.remove(); deniedShown = false; };
                document.body.appendChild(box);
            @endif
        }

        var hooked = false;
        function hookLivewire() {
            if (hooked || ! window.Livewire) { return; }
            hooked = true;
            window.Livewire.hook('request', function (ctx) {
                ctx.fail(function (res) {
                    if (res.status !== 403) { return; }
                    res.preventDefault();
                    pageStillAllowed().then(function (allowed) { if (allowed) { showDenied(); } });
                });
            });
        }

        document.addEventListener('livewire:init', hookLivewire);
        hookLivewire();
        setInterval(check, 30000);
        document.addEventListener('visibilitychange', check);
    })();
</script>
@endauth
