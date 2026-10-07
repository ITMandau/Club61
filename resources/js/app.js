import './bootstrap';

import Alpine from 'alpinejs';

// Halaman Livewire (POS, admin) sudah membawa Alpine sendiri — @livewireScripts jalan sebelum modul ini. Menyalakan
// Alpine kedua membuat komponen x-data diproses dua kali ("Detected multiple instances of Alpine running").
if (! window.Alpine) {
    window.Alpine = Alpine;

    Alpine.start();
}
