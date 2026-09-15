# CHANGELOG: Club 61 Integrated Dashboard Backend

Catatan riwayat pembaruan sistem dan evolusi arsitektur.

---

## [v1.3.0-Enterprise-Role-Matrix] - 2026-09-15

### Ditambahkan (Added)
- **Enterprise Role & Sidebar Permission Matrix (Full Club 61 Ecosystem)**:
  - Membuat class terpusat `App\Services\Permission\Club61PermissionMatrix` yang mendefinisikan 63 izin aksi granular dalam 11 kategori modul operasional venue (Padel Arena, POS Kasir Frontdesk, F&B Kitchen KDS, Gym & Fitness, Wellness & Sauna, Salon, Merchandise, Karyawan, Turnamen, Analytics, dan Master Data) murni menggunakan teks polos.
  - Menambahkan kolom `home_route` (varchar 100, nullable) dan `description` (varchar 255, nullable) pada tabel `roles` di `create_permission_tables.php`.
  - Membuat model kustom `App\Models\Role` yang meng-extend Spatie `Role` dengan konfigurasi opsi resmi Home Route (`/admin`, `/pos`, `/kitchen`, `/dashboard`).
  - Mengonfigurasi `config/permission.php` untuk menggunakan `App\Models\Role::class` secara global.
- **Resource Backoffice Kustom (`RoleResource`) di Filament**:
  - Menggantikan tampilan Shield bawaan dengan `App\Filament\Resources\Roles\RoleResource`:
    - **Tabel Roles**: Menampilkan kolom ID, SLUG, NAME, MENUS (jumlah izin aktif), HOME ROUTE (badge warna tujuan login), dan tombol aksi Edit / Hapus.
    - **Form & Modal Edit Role**: Menampilkan input SLUG, NAME, Dropdown HOME ROUTE, serta kartu matriks untuk 11 kategori modul dengan kotak centang sub-modul dan tombol pintas *Select All / Deselect All* per modul.
  - Otomatis menonaktifkan resource default Shield agar tidak terjadi duplikasi menu di sidebar.
- **Dynamic Home Route Redirection**:
  - Memperbarui `AuthenticatedSessionController.php` dan `welcome.blade.php` agar setiap pengguna yang berhasil login langsung mendarat di rute `home_route` yang terpasang pada peran aktifnya.
- **Perlindungan Akses Halaman Filament (`HasPageShield`)**:
  - Memasang trait `HasPageShield` pada seluruh 10 halaman menu Filament Admin Panel (`Analytics`, `BookingSystem`, `Dashboard`, `KelolaClub`, `KelolaKaryawan`, `KelolaPemesanan`, `KelolaTurnamen`, `Kustomer`, `Marketing`, `MasterData`).

### Diperbaiki & Diuji (Fixed & Verified)
- Menghapus total penggunaan emoji dan ikon dekoratif pada seluruh rancangan teks dan antarmuka sistem.
- Menambahkan test case baru untuk memverifikasi login redirection berdasarkan `home_route` dan integritas 63 izin matrix.
- Seluruh automated test suite kini lulus 100% (**80 passed, 365 assertions**).

---

## [v1.2.0-Dynamic-RBAC-Shield] - 2026-09-15

### Ditambahkan (Added)
- **Modul 09: Dynamic Role-Based Access Control (RBAC) & Filament Shield**:
  - Mengintegrasikan package industri `spatie/laravel-permission:^6.25` dan `bezhansalleh/filament-shield:^4.3`.
  - Mengonversi otorisasi enum statis `users.role` menjadi arsitektur 5 tabel relasional dinamis: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`.
  - Menghasilkan 34 permissions dan 2 policies (`UserPolicy`, `RolePolicy`) secara otomatis via `shield:generate`.
  - Memasang **Filament Shield Plugin** dengan antarmuka centang visual matriks hak akses peran di `/admin/shield/roles`.
  - Membuat **`UserResource` di Filament** (`/admin/users`) untuk pengelolaan pengguna dan penugasan peran (*role multi-select*).
  - Menambahkan gerbang absolut **Super Admin Override** via `Gate::before` di `AppServiceProvider.php`.

### Diperbaiki & Dioptimalkan (Fixed & Refactored)
- **Kustomisasi Kunci Morf ULID (`CHAR(26)`) Spatie**:
  - Memodifikasi migrasi Spatie Permission agar kolom `model_id` pada tabel pivot bertipe `char(26)` untuk mendukung Primary Key ULID model `User`.
- **Konsolidasi Migrasi Database**:
  - Mengonsolidasikan kolom `order_id` dan `checked_in_at` ke dalam migrasi padel utama `create_padel_tables.php`.
  - Menghapus 2 file migrasi usang/terfragmentasi (`2026_09_07_...` dan `2026_09_08_...`).
- **Jembatan Kompatibilitas Model `User`**:
  - Accessor `getRoleAttribute()` dan mutator `setRoleAttribute()` menjaga respons JSON mobile Flutter tetap valid tanpa breaking changes.
  - Inisialisasi pembersihan cache `forgetCachedPermissions()` di baris teratas `DatabaseSeeder.php` untuk mencegah stale cache saat fresh migrate.
- **Automated Test Suite**:
  - Seluruh 73 automated tests lulus 100% (335 assertions).

---

## [v1.1.0-Reschedule-Hardening] - 2026-09-15

### Diperbaiki (Fixed)
- **Anti-Premature Expiry Guard pada Garbage Collection (`releaseExpiredLocks`)**:
  - Memperbaiki bug kritis di mana booking hasil reschedule yang berstatus `LOCKED` (menunggu pelunasan selisih tarif / delta) hangus dan terbuka kembali slotnya ke publik setelah 10 menit oleh cron `padel:release-expired-slots`.
  - Mengisolasi aturan kedaluwarsa: Scheduler GC kini **kebal (immune)** terhadap booking yang memiliki `reschedule_count > 0` atau telah memiliki record pembayaran `SUCCESS`. Hanya keranjang baru yang benar-benar belum pernah dibayar (`reschedule_count == 0` dan tanpa pembayaran sukses) yang di-expire setelah 10 menit.
  - Memperpanjang TTL slot cache lock untuk reschedule kurang bayar menjadi **24 Jam (86.400 detik)** agar slot lapangan baru tetap aman terkunci hingga jadwal tanding tiba.

### Ditambahkan (Added)
- **Delta-Only Payment Retry & Midtrans Webhook Settlement**:
  - `retryPayment()` kini mendeteksi status kurang bayar hasil reschedule. Jika terdapat selisih tarif pending, sistem hanya menagihkan nominal delta ($\Delta$) tanpa menimpa order atau menghitung ulang total dari awal.
  - Mengonsolidasikan transaksi finansial di bawah **Order ID yang sama (`order_id`)**, baik pelunasan via Tunai Kasir Frontdesk maupun via Payment Gateway (Midtrans Snap VA/QRIS).
  - Webhook Midtrans (`MidtransWebhookController`) otomatis menandai supplemental payment menjadi `SUCCESS`, memperbarui status booking menjadi `PAID`, serta menerbitkan hash QR Turnstile begitu selisih dibayar lunas.
- **Atribut Baru pada E-Tiket / Boarding Pass (`getTicket()`)**:
  - Menambahkan field `has_pending_delta`, `unpaid_delta`, dan `total_paid` pada response tiket API dan Blade view invoice untuk transparansi tagihan pelanggan.

### Refaktor Arsitektur (Refactored)
- **Modularisasi `PadelBookingService.php` (Concerns Trait Architecture)**:
  - Memecah monolith service berukuran 1.611 baris menjadi 5 Trait domain terfokus di `app/Services/Padel/Concerns/`:
    - `ManagesScheduleAndSlots`: Matriks jadwal, availability, hold slot, dan release lock.
    - `ManagesCheckoutAndPayments`: Alur checkout, idempotency key, retry payment delta, dan verifikasi gateway.
    - `ManagesCheckInAndTurnstile`: Validasi QR single-use check-in, anti-replay, dan turnstile window.
    - `ManagesTicketsAndRefunds`: Boarding pass customer, refund mandiri H-24, dan admin void/refund.
    - `ManagesRescheduleAndCashier`: Admin reschedule override, contiguous check, kalkulasi delta, dan quick settle kasir.
  - `PadelBookingService` kini ramping (29 baris) sebagai orchestrator tanpa mengubah signature method publik.
  - Seluruh 73 automated tests (332 assertions) lulus 100% tanpa regresi.

---

## [v1.0.0-Enterprise] - 2026-09-03

### Ditambahkan (Added)
- **Arsitektur Hybrid "1 Rumah 3 Pintu":**
  - **Pintu 1 (Web Customer):** Laravel Breeze Blade (`/register`, `/login`, `/dashboard`).
  - **Pintu 2 (Mobile API):** Laravel Sanctum Headless RESTful JSON API (`/api/v1/...`).
  - **Pintu 3 (Admin & Kasir):** Filament v3 Panel (`/admin`).
- **Skema Database MySQL (MariaDB XAMPP 35 Tabel):**
  - Mengonversi skema lama PostgreSQL ke MySQL InnoDB InnoDB.
  - Menggunakan Primary Key **ULID** (`CHAR(26)`) via `HasUlids` untuk mencegah fragmentasi clustered index pada storage disk.
  - Memasang **Composite Unique SoftDeletes** (`UNIQUE (email, deleted_at)`) untuk menangkal bug *Duplicate Entry* saat user re-registrasi.
- **Strategi Tameng Concurrency Anti-Double Booking:**
  - **Pessimistic Lock (`lockForUpdate()`):** Menolak transaksi yang berbenturan jam pada Lapangan Padel dan Salon Stylist.
  - **Cache Lock (5 Menit Hold):** Mengunci slot saat user memilih raket sebelum checkout.
  - **Atomic Query Kuota & Stok:** Mengamankan kuota Cold Plunge / Sauna dan pemotongan stok bahan baku resep BOM Cafe.
  - **Kunci Waitlist Antrean (`lockForUpdate()`):** Menjamin hanya 1 peserta waitlist teratas yang berhasil mengklaim kuota lungsuran.
- **5 Sabuk Pengaman Level Production:**
  - Global Exception Handler di `bootstrap/app.php` untuk memformat seluruh error ke JSON seragam.
  - Rate Limiting di `AppServiceProvider.php` (Auth 10 hit/min, Booking 5 hit/min).
  - CORS diperketat di `config/cors.php`.
  - Concurrency Feature Test otomatis di `tests/Feature/PadelBookingConcurrencyTest.php` (100% PASS).
  - Mode Simulator Pembayaran di `POST /api/v1/payments/simulate` untuk pengujian Flutter tanpa uang nyata.
- **Database Seeder Komprehensif:**
  - 6 Akun default (Super Admin, Kasir, Barista, Coach Padel, Stylist Salon, Customer).
  - 4 Lapangan Padel (Indoor/Outdoor), 3 Jenis Raket, 2 Fasilitas Wellness (16 Sesi), 3 Layanan Salon, 3 Paket Gym, 3 Kategori Cafe dengan Resep BOM, dan Merchandise.
- **Dokumentasi Lengkap:**
  - `docs/PANDUAN_PEMULA_DAN_FLUTTER.md` untuk tim mobile.
