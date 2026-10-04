{{-- Di bawah 1024px Filament sudah menyembunyikan sidebar sendiri (mode drawer/hamburger).
     Script ini menangani rentang tablet landscape 1024–1279px: sidebar otomatis diciutkan jadi
     strip ikon tanpa perlu diklik, lalu kembali ke pilihan user begitu layar ≥1280px.
     Sengaja hanya mengubah $store.sidebar.isOpen (bukan close()) supaya preferensi desktop
     user yang disimpan Filament (isOpenDesktop) tidak ikut tertimpa. --}}
<script>
    (() => {
        const TABLET_MIN = 1024;
        const DESKTOP_MIN = 1280;
        const isTablet = (w) => w >= TABLET_MIN && w < DESKTOP_MIN;
        let previousWidth = null;

        const apply = (force) => {
            const store = window.Alpine?.store?.('sidebar');
            if (! store) {
                return;
            }

            const width = window.innerWidth;

            if (isTablet(width) && (force || ! isTablet(previousWidth ?? 0))) {
                store.isOpen = false;
            } else if (width >= DESKTOP_MIN && previousWidth !== null && isTablet(previousWidth)) {
                store.isOpen = store.isOpenDesktop;
            }

            previousWidth = width;
        };

        document.addEventListener('alpine:initialized', () => apply(true));
        // Jalan SETELAH handler Filament sendiri (yang mengembalikan isOpen = isOpenDesktop).
        document.addEventListener('livewire:navigated', () => requestAnimationFrame(() => apply(true)));
        window.addEventListener('resize', () => apply(false));

        if (window.Alpine?.store?.('sidebar')) {
            apply(true);
        }
    })();
</script>
