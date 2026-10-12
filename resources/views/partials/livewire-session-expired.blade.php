{{--
    Sesi habis (419) saat tombol Livewire ditekan — pengganti popup bawaan browser "This page has expired".
    Panel admin ($filamentConfirm = true) memakai dialog konfirmasi Club61 (filament.confirm-dialog); layar lain
    (POS F&B) memakai kotak pesan sederhana. Isian yang belum disimpan memang hilang saat muat ulang — makanya
    user diberi tahu dulu, tidak langsung di-reload.
--}}
<script>
    (function () {
        var shown = false;
        var registered = false;
        var title = 'Sesi halaman sudah habis';
        var message = 'Halaman ini terlalu lama terbuka (atau Anda masuk / keluar di tab lain), jadi aksi tadi belum tersimpan. Muat ulang halaman lalu ulangi aksinya.';

        function showBox() {
            var box = document.createElement('div');
            box.setAttribute('role', 'alertdialog');
            box.style.cssText = 'position:fixed;top:0;right:0;bottom:0;left:0;z-index:99999;background:rgba(30,18,15,.55);display:flex;align-items:center;justify-content:center;padding:16px;';
            box.innerHTML = '<div style="max-width:420px;width:100%;background:#FCF8EE;border:1px solid #E6DAC0;border-radius:18px;padding:22px;font-family:system-ui,sans-serif;color:#4F2F2A;box-shadow:0 24px 60px -20px rgba(0,0,0,.5)">'
                + '<div style="font-size:18px;font-weight:800;margin-bottom:8px"></div>'
                + '<div style="font-size:14px;line-height:1.55;color:#7A5A52;margin-bottom:18px"></div>'
                + '<button type="button" style="width:100%;min-height:46px;border:0;border-radius:12px;background:#662721;color:#F7F0DB;font-weight:800;font-size:15px;cursor:pointer">Muat Ulang Halaman</button>'
                + '</div>';
            box.querySelector('div > div').textContent = title;
            box.querySelector('div > div + div').textContent = message;
            box.querySelector('button').onclick = function () { window.location.reload(); };
            document.body.appendChild(box);
        }

        function register() {
            if (registered || ! window.Livewire) { return; }
            registered = true;
            window.Livewire.hook('request', function (ctx) {
                ctx.fail(function (res) {
                    if (res.status !== 419) { return; }
                    res.preventDefault();
                    if (shown) { return; }
                    shown = true;
                    @if ($filamentConfirm ?? false)
                        window.dispatchEvent(new CustomEvent('club61-confirm', { detail: {
                            title: title,
                            message: message,
                            confirmLabel: 'Muat Ulang Halaman',
                            cancelLabel: 'Nanti',
                            onConfirm: function () { window.location.reload(); }
                        } }));
                        // Ditutup tanpa muat ulang → boleh muncul lagi di aksi berikutnya.
                        setTimeout(function () { shown = false; }, 1500);
                    @else
                        showBox();
                    @endif
                });
            });
        }

        // Skrip ini bisa termuat sebelum atau sesudah Livewire — daftar di dua-duanya, cukup sekali.
        document.addEventListener('livewire:init', register);
        register();
    })();
</script>
