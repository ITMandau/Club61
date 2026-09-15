# Product Requirements Document (PRD)
## Modul 09: Dynamic Role-Based Access Control (RBAC) & Enterprise Permission Matrix
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-09-DYNAMIC-RBAC` |
| **Versi** | `v1.3.0-PROD-ENTERPRISE` |
| **Status** | **Approved & Fully Implemented** |
| **Tech Stack** | `spatie/laravel-permission:^6.25`, `bezhansalleh/filament-shield:^4.3`, Laravel 11, Filament v5 |
| **Prinsip Utama** | **GRANULAR PERMISSIONS, UI-DRIVEN ASSIGNMENT, SUPER-ADMIN GATE OVERRIDE, ULID MORPH SUPPORT, HOME ROUTE REDIRECTION** |

---

## 1. Latar Belakang & Filosofi Arsitektur

Sebelumnya, otorisasi sistem disimpan secara statis di dalam kolom string enum `role` pada tabel `users` (misal: `'SUPER_ADMIN'`, `'CASHIER'`).

### Kelemahan Sistem Enum Statis:
1. **Tidak Fleksibel**: Setiap penambahan peran baru (contoh: Junior Cashier, Supervisor Padel, F&B Floor Manager) menuntut pengubahan kode migration dan deploy ulang.
2. **Tidak Granular**: Seluruh staf kasir memiliki hak akses yang identik tanpa bisa dibatasi secara spesifik per menu atau tombol aksi (misal: kasir biasa dilarang melakukan refund tunai).
3. **Ketiadaan Visibilitas**: Manajemen venue tidak dapat memantau atau mengatur matriks izin staf langsung dari antarmuka Backoffice.

### Solusi Enterprise Modul 09:
Mengadopsi kombinasi **Spatie Laravel Permission** dan **Filament Shield Custom Architecture**:
* **5 Tabel Relasional Dinamis**: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`.
* **Enterprise Role & Sidebar Permission Matrix**: Antarmuka backoffice visual di `/admin/roles` yang menyusun 63 hak akses ke dalam 11 kategori modul operasional venue menggunakan teks polos bersih.
* **Super Admin Override**: Peran absolut `super_admin` yang otomatis mem-bypass seluruh pemeriksaan izin via `Gate::before`.
* **Zero-Downtime API Bridge**: Accessor `getRoleAttribute()` dan mutator `setRoleAttribute()` pada model `User` menjaga agar kontrak JSON mobile Flutter tetap menerima string role (`SUPER_ADMIN`, `CASHIER`, `CUSTOMER`) tanpa perubahan pada frontend.
* **Home Route Redirection**: Penentuan rute pendaratan otomatis setelah login per role (`/admin`, `/pos`, `/kitchen`, `/dashboard`).

---

## 2. Struktur Skema Database & Kustomisasi Kunci ULID

Karena Club 61 menggunakan Primary Key **ULID (`CHAR(26)`)** pada tabel `users` untuk mencegah fragmentasi storage B-Tree, skema Spatie Permission dikustomisasi secara khusus:

### Invarian Kritis: Morph Key ULID (`CHAR(26)`)
Di file migrasi `create_permission_tables.php`, kolom `model_id` pada tabel pivot `model_has_permissions` dan `model_has_roles` diubah dari bawaan Spatie (`unsignedBigInteger`) menjadi:
```php
$table->char($columnNames['model_morph_key'], 26);
```
Hal ini menjamin integritas referensial dan mencegah bug *1265 Data truncated for column 'model_id'*.

### Kolom Tambahan pada Tabel `roles`:
1. `home_route` (varchar 100, nullable): Menentukan URL redirect default setelah login sukses.
2. `description` (varchar 255, nullable): Keterangan deskriptif label peran.

### Konsolidasi Migrasi Database:
1. Kolom statis `users.role` resmi dihapus dari skema awal `create_users_table.php`.
2. Kolom `order_id` dan `checked_in_at` disatukan langsung ke dalam `create_padel_tables.php`.
3. File-file migrasi `ALTER TABLE` terfragmentasi (`2026_09_07_...` dan `2026_09_08_...`) dihapus demi kebersihan repositori.

---

## 3. Matriks 7 Peran Bawaan Sistem (Standard Seeded Roles)

| Peran (Role) | Target Pengguna | Home Route | Akses Default |
| :--- | :--- | :---: | :--- |
| **`super_admin`** | Owner & Lead Developer | `/admin` | Akses penuh tanpa batas (*bypass gate*). |
| **`admin`** | General Manager Venue | `/admin` | Manajemen operasional, override reschedule, refund resmi, analytics keuangan. |
| **`cashier`** | Staf Frontdesk POS | `/pos` | Terminal POS, scan tiket QR check-in, pelunasan tunai/EDC selisih jadwal. |
| **`kitchen`** | Barista & Kitchen Chef | `/kitchen` | Layar Kitchen Display System (KDS) di `/kitchen`. |
| **`trainer`** | Pelatih / Sparring Coach | `/admin` | Jadwal sesi coaching padel dan profil instruktur. |
| **`stylist`** | Hair Stylist Salon | `/admin` | Antrean appointment salon dan durasi treatment. |
| **`customer`** | Pemain / Member Publik | `/dashboard` | Portal customer, e-invoice boarding pass, riwayat booking pribadi. |

---

## 4. Spesifikasi Fungsional (Functional Requirements)

### FR-01: Auto-Discovery & Policy Generation
* Perintah `php artisan shield:generate --all --panel=admin` otomatis memindai resource, page, dan widget untuk mengikat ke Policy Laravel.
* Seluruh 10 halaman navigasi Filament dipasangi trait `HasPageShield` sehingga menu sidebar otomatis disembunyikan jika izin tidak diberikan.

### FR-02: User Management Resource (`UserResource`)
* Halaman Filament **"Kelola Pengguna"** (`/admin/users`):
  * Input Form: Nama Lengkap, Alamat Email, Nomor WhatsApp, Password, Toggle Aktif.
  * Role Selector: Dropdown multi-select relasi Spatie `roles` dengan fitur search dan preload.
  * Tabel List: Menampilkan data user dengan badge peran berwarna dinamis, filter peran, dan filter soft deletes.

### FR-03: Dynamic Home Route Redirection
Saat staf atau customer login via web portal (`/login`), sistem membaca kolom `home_route` dari peran utama pengguna:
* Jika `home_route` terisi (misal: `/pos`, `/kitchen`, `/admin`, `/dashboard`), pengguna langsung diarahkan ke URL tersebut.
* Fallback cerdas jika kosong: staf ke `/admin`, kasir ke `/pos`, kitchen ke `/kitchen`, customer ke `/dashboard`.
* Tombol aksi di landing page `welcome.blade.php` otomatis menyesuaikan rute dan label tujuan pengguna.

### FR-04: Cache Invalidation di Database Seeder
Untuk menangkal stale cache pada memori saat seeding ulang, baris pertama fungsi `run()` di `DatabaseSeeder.php` wajib mengeksekusi:
```php
app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
```

### FR-05: Enterprise Role Resource (`RoleResource`) & Matriks 11 Modul
* Halaman Filament **"Roles & Hak Akses"** (`/admin/roles`) mengadopsi tata letak referensi Mandau ERP:
  * **Tabel Roles**: Menampilkan ID, SLUG, NAME, MENUS (jumlah izin terpasang), HOME ROUTE, dan aksi Edit / Hapus.
  * **Form Edit / Tambah**:
    * Baris Atas: Input SLUG (`name`), NAME (`description`), dan Dropdown HOME ROUTE (`home_route`).
    * Matriks Grid Izin: Menampilkan 11 kartu kategori modul:
      1. Padel Arena & Bookings
      2. Point of Sale (POS) & Kasir
      3. F&B Cafe & Kitchen (KDS)
      4. Gym & Fitness Center
      5. Wellness & Recovery Suite
      6. Salon & Hair Treatment
      7. Merchandise & Pro Shop
      8. Karyawan, Pelatih & Staf
      9. Turnamen, Marketing & Voucher
      10. Analytics & Laporan Keuangan
      11. Master Data & Hak Akses
    * Setiap kategori memuat checkbox daftar aksi granular lengkap dengan tombol pintas *Select All / Deselect All* per modul.

---

## 5. Ringkasan Pengujian & Verifikasi

Seluruh 80 skenario automated tests lulus 100% (365 assertions):
* **`SecurityHardeningAuditTest`**: Verifikasi proteksi role POS dan KDS dapur.
* **`AuthenticationTest`**: Verifikasi multi-door login redirection untuk tiap role, custom role home_route, page shield hiding, dan integritas matriks peran.
* **`PadelAdminOverrideTest`**: Verifikasi aksi admin reschedule dan refund dengan role admin.
* **`AuthApiTest`**: Verifikasi registrasi customer via API tetap mengembalikan atribut `role: "CUSTOMER"` dan menyimpan relasi role Spatie `customer` secara valid.
