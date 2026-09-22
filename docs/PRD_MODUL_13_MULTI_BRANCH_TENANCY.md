# Product Requirements Document (PRD)
## Modul 13: Multi-Cabang & Isolasi Database Per-Tenant ("Club61 Franchise Architecture")
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-13-MULTI-BRANCH-TENANCY` |
| **Versi** | `v1.0.0-DRAFT` |
| **Status** | **Proposed — Menunggu Persetujuan Implementasi** |
| **Target Pengguna** | Pemilik/Manajemen Pusat (Landlord), Admin & Staf per Cabang, Customer per Cabang |
| **Prinsip Utama** | **ISOLASI DATABASE FISIK PENUH PER CABANG, ZERO CROSS-TENANT LEAK, PROVISIONING AMAN, PUSAT SEBAGAI PANEL OVERSIGHT** |
| **Dependensi Teknis** | `stancl/tenancy` (Laravel Multi-Database Tenancy Package) |

---

## 1. Latar Belakang & Keputusan Arsitektur

Club 61 saat ini beroperasi sebagai sistem **single-tenant** — satu aplikasi, satu database, satu venue. Rencana ekspansi bisnis ke model **multi-cabang (franchise-style)** membutuhkan keputusan arsitektur mendasar: bagaimana data antar cabang dipisahkan?

### Dua Pilihan yang Dipertimbangkan

| Pendekatan | Isolasi Data | Laporan Gabungan Lintas Cabang |
| :--- | :--- | :--- |
| Shared Schema (1 DB + kolom `branch_id`) | Bergantung pada disiplin `WHERE branch_id` di setiap query — rawan bug kebocoran data | Mudah (1 query `GROUP BY`) |
| **Database-per-Tenant (dipilih)** | **Isolasi fisik total** — data cabang A secara struktural tidak bisa diakses cabang B, bahkan kalau ada bug | Butuh agregasi lintas-database (lebih kompleks, dijelaskan di FR-06) |

**Keputusan**: Club 61 memilih **Database-per-Tenant**. Alasan bisnis: setiap cabang berpotensi memiliki penanggung jawab operasional/keuangan yang berbeda, dan isolasi fisik menghilangkan seluruh kelas bug "lupa filter tenant" yang secara historis menjadi sumber kebocoran data paling umum pada sistem multi-tenant.

### Prinsip Non-Negosiasi
- Seluruh modul yang sudah dibangun dan diperkuat (Booking Engine, Payment Orchestrator, Shift Kasir, RBAC, Tax Engine) **tetap berjalan identik per cabang** tanpa perlu ditulis ulang — isolasi terjadi di level koneksi database, bukan di level query aplikasi.
- Pusat (Landlord) **tidak** memiliki akses langsung baca/tulis ke data operasional cabang secara real-time kecuali melalui mekanisme agregasi terkontrol (FR-06).

---

## 2. Solusi Arsitektur

```
                         +---------------------------------+
                         |     DATABASE LANDLORD/PUSAT      |
                         |  (club61_landlord)               |
                         |  - tabel `tenants` (daftar cabang)|
                         |  - tabel `landlord_admins`         |
                         |  - tabel `branch_report_cache`     |
                         +----------------+------------------+
                                          |
                          Resolusi Tenant (subdomain / header)
                                          |
               +--------------------------+--------------------------+
               |                          |                          |
               v                          v                          v
   +-------------------------+  +-------------------------+  +-------------------------+
   | cabang-jakarta.club61.id|  | cabang-bandung.club61.id|  | cabang-bali.club61.id   |
   | DB: club61_jakarta       |  | DB: club61_bandung      |  | DB: club61_bali          |
   | (skema identik: orders,  |  | (skema identik)          |  | (skema identik)          |
   |  payments, padel_bookings,|  |                          |  |                          |
   |  users, roles, dst.)      |  |                          |  |                          |
   +-------------------------+  +-------------------------+  +-------------------------+
```

Setiap kotak cabang menjalankan **codebase yang sama persis** (satu deployment, bukan instalasi terpisah) — perbedaannya murni pada koneksi database aktif yang di-switch otomatis oleh `stancl/tenancy` berdasarkan subdomain permintaan yang masuk, sebelum satu query pun dijalankan.

---

## 3. Skema Database

### 3.1 Database Landlord — Tabel `tenants`
| Kolom | Tipe | Keterangan |
| :--- | :--- | :--- |
| `id` | ULID, PK | Identifier unik cabang. |
| `name` | string(150) | Nama cabang (misal "Club 61 Jakarta Selatan"). |
| `subdomain` | string(63), unique | Subdomain akses (misal `jakarta`). |
| `db_name` | string(64), unique | Nama database fisik MySQL cabang ini. |
| `db_host` | string(150), nullable | Host DB jika di masa depan cabang tertentu dipindah ke server terpisah (null = server default). |
| `status` | string(20), default `PROVISIONING` | `PROVISIONING`, `ACTIVE`, `SUSPENDED`. |
| `midtrans_server_key` / `midtrans_client_key` | string, nullable, encrypted | Kredensial Midtrans **milik cabang ini sendiri** (lihat FR-05). |
| `owner_contact_name` / `owner_contact_phone` | string, nullable | Kontak PIC operasional cabang. |
| `provisioned_at` | datetime, nullable | Waktu database cabang selesai dibuat & dimigrasikan. |
| `timestamps` | | |

### 3.2 Database Landlord — Tabel `landlord_admins`
Tabel otentikasi **terpisah total** dari sistem RBAC per-cabang. Ini adalah akun pusat (pemilik/manajemen atas) yang mengelola daftar cabang — **bukan** akun `super_admin` cabang manapun.
| Kolom | Tipe | Keterangan |
| :--- | :--- | :--- |
| `id` | ULID, PK | |
| `name`, `email`, `password` | standar | Kredensial login panel pusat. |
| `timestamps` | | |

### 3.3 Database Landlord — Tabel `branch_report_cache`
Menyimpan hasil agregasi periodik lintas cabang (lihat FR-06) — bukan live query, karena database cabang secara fisik terpisah dan tidak bisa di-JOIN langsung.
| Kolom | Tipe | Keterangan |
| :--- | :--- | :--- |
| `id` | ULID, PK | |
| `tenant_id` | ULID, FK → `tenants.id` | |
| `report_date` | date | Tanggal ringkasan (harian). |
| `total_revenue` | decimal(14,2) | Omzet cabang hari itu. |
| `total_transactions` | integer | |
| `generated_at` | datetime | |

### 3.4 Database Per-Cabang
**Identik dengan skema Club61 yang sudah ada saat ini** — `padel_bookings`, `orders`, `payments`, `pos_cashier_shifts`, `club_finance_settings`, `users`, `roles`, dst. **Tidak ada satupun kolom `branch_id`/`tenant_id` yang perlu ditambahkan** ke tabel-tabel ini, karena isolasi terjadi di level database, bukan di level baris data.

---

## 4. Spesifikasi Kebutuhan Fungsional (Functional Requirements)

### FR-01: Integrasi Package `stancl/tenancy`
- Install & konfigurasi `stancl/tenancy` sebagai lapisan resolusi tenant berbasis **subdomain**.
- Middleware `InitializeTenancyBySubdomain` dipasang di seluruh route grup web & API (`routes/web.php`, `routes/api.php`) sehingga koneksi database aktif otomatis berpindah ke database cabang yang sesuai sebelum controller/service manapun dieksekusi.
- **Zero Touch ke Model/Service Existing**: `PadelBooking`, `Order`, `Payment`, `PaymentOrchestratorService`, `PosCashierShift`, seluruh RBAC — tidak ada satu baris kode pun yang diubah, karena mereka hanya memanggil koneksi database "default" yang connection-nya sudah di-swap oleh middleware.

### FR-02: Panel Pusat — "Kelola Cabang" (Landlord Filament Panel)
- Panel Filament **terpisah** (guard/panel baru, bukan bagian dari panel `/admin` cabang manapun) diakses lewat domain utama tanpa subdomain (misal `panel.club61.id`), login menggunakan `landlord_admins`.
- Fitur: daftar cabang (nama, subdomain, status, tanggal provisioning), tombol "Tambah Cabang Baru", toggle suspend/aktifkan cabang.
- **Halaman ini sama sekali tidak bisa melihat data operasional cabang** (booking, customer, keuangan harian) — hanya metadata cabang dan laporan ringkas terjadwal (FR-06).

### FR-03: Provisioning Cabang Baru (Aman & Terisolasi Privilege)
Alur saat admin pusat klik "Tambah Cabang Baru":
1. Validasi keunikan `subdomain`.
2. **Job Queue (bukan proses synchronous di request HTTP)** `ProvisionNewBranchJob` dijalankan:
   - Membuat database fisik baru menggunakan **user MySQL terpisah yang punya privilege `CREATE DATABASE`** — user ini **tidak pernah** dipakai oleh aplikasi runtime harian (prinsip least-privilege; kredensial provisioning disimpan terpisah, bukan di `.env` yang sama dengan koneksi aplikasi).
   - Menjalankan seluruh migration existing terhadap database baru (`tenants:artisan migrate --tenants=...`).
   - Menjalankan seeder RBAC standar (`Club61PermissionMatrix`, 7 role bawaan) dan membuat 1 akun `super_admin` awal untuk cabang tersebut.
   - Mengubah `status` tenant menjadi `ACTIVE` dan mencatat `provisioned_at`.
3. Jika provisioning gagal di tengah jalan (migration error, dsb.), status tetap `PROVISIONING` dan database yang gagal dibuat **tidak dihapus otomatis** — memerlukan investigasi manual staf teknis (mencegah penghapusan data yang keliru akibat race condition job retry).

### FR-04: Konsistensi Skema Lintas Cabang (Migration Governance)
- Setiap migration baru yang dibuat untuk fitur apapun ke depannya **wajib** dijalankan ke SEMUA database cabang secara serempak menggunakan `php artisan tenants:migrate`, tidak boleh dijalankan manual satu-satu.
- Ditambahkan pemeriksaan otomatis (`tenants:migrate --pretend` sebagai bagian dari CI/deployment) untuk mendeteksi jika ada cabang yang skemanya "ketinggalan" dibanding yang lain sebelum deployment fitur baru dilanjutkan.

### FR-05: Kredensial Payment Gateway Per-Cabang
- Setiap cabang adalah entitas bisnis yang berpotensi memiliki akun merchant Midtrans **masing-masing** (rekening tujuan dana berbeda per cabang).
- `MidtransService` diubah agar membaca `server_key`/`client_key` dari **tenant context aktif** (`tenancy()->tenant->midtrans_server_key`, dienkripsi di tabel `tenants` pada database Landlord) — bukan lagi murni dari `config('services.midtrans.*')` global `.env`.
- **Fallback**: jika kredensial per-cabang belum diisi (masa transisi), sistem jatuh ke kredensial global sebagai default sementara, dengan log peringatan agar staf pusat menindaklanjuti pengisian kredensial resmi cabang tersebut.

### FR-06: Laporan Agregasi Lintas Cabang (Panel Pusat)
- Command terjadwal harian `php artisan tenants:aggregate-reports` melakukan iterasi ke **setiap** database cabang satu per satu (`tenancy()->run()`), menghitung total omzet & transaksi hari itu, lalu menuliskan hasilnya ke tabel `branch_report_cache` di database Landlord.
- Panel Pusat menampilkan dashboard perbandingan omzet antar-cabang berdasarkan data cache ini — **bukan** live query lintas database (yang secara arsitektur tidak memungkinkan karena database benar-benar terpisah secara fisik).

### FR-07: Isolasi Cache/Redis Antar Cabang
- `stancl/tenancy` dikonfigurasi dengan **tag prefix cache otomatis per tenant** sehingga seluruh mekanisme `Cache::lock()` yang sudah dibangun (atomic slot-locking booking, guard shift kasir) tetap aman dipakai bersama walau seluruh cabang berbagi 1 instance Redis fisik yang sama — kunci cache milik cabang A tidak akan pernah bertumbukan dengan kunci milik cabang B.

### FR-08: Command Terjadwal Multi-Tenant
- Seluruh scheduled command yang sudah ada (`releaseExpiredLocks`, sinkronisasi status booking kedaluwarsa, dsb.) dibungkus agar dieksekusi terhadap **setiap** database cabang yang berstatus `ACTIVE`, bukan hanya satu database global seperti sekarang.

---

## 5. Kebutuhan Non-Fungsional

1. **Zero Regression Terhadap Modul Existing**: Booking Engine, Payment Orchestrator, Shift Kasir, Tax Engine, dan RBAC yang sudah diperkuat melalui berbagai audit sebelumnya wajib tetap berjalan identik di dalam konteks tenant manapun tanpa modifikasi logic bisnis.
2. **Least-Privilege Provisioning**: kredensial database dengan hak `CREATE DATABASE` disimpan terpisah dari kredensial aplikasi runtime, tidak pernah diekspos ke kode aplikasi yang menangani request pengguna.
3. **Tidak Ada Kebocoran Cross-Tenant**: dijamin secara struktural oleh isolasi database fisik — bukan bergantung pada disiplin developer menulis `WHERE` clause.
4. **Auditability Provisioning**: setiap kegagalan provisioning tercatat dan tidak memicu penghapusan data otomatis.

---

## 6. Rencana Pengujian

1. `test_tenant_resolution_switches_database_connection_correctly`: memastikan request ke 2 subdomain berbeda benar-benar terhubung ke 2 database fisik yang berbeda.
2. `test_booking_created_in_tenant_a_is_invisible_from_tenant_b`: verifikasi isolasi — booking yang dibuat di cabang A sama sekali tidak muncul dalam query apapun dari konteks cabang B.
3. `test_provisioning_job_creates_database_and_runs_full_migration_set`: memastikan cabang baru langsung memiliki skema lengkap & role RBAC standar begitu selesai diprovisioning.
4. `test_provisioning_failure_does_not_silently_delete_partial_database`: simulasikan migration gagal di tengah jalan, pastikan status tetap `PROVISIONING` dan tidak ada penghapusan otomatis.
5. `test_midtrans_service_uses_tenant_specific_credentials_when_available`: verifikasi `MidtransService` membaca kredensial dari tenant aktif, dengan fallback ke global jika belum diisi.
6. `test_cache_lock_keys_are_isolated_per_tenant`: dua cabang melakukan hold-slot pada waktu bersamaan dengan `court_id` yang secara ULID kebetulan mirip pola — pastikan tidak ada interferensi lock antar cabang.
7. `test_scheduled_gc_command_processes_all_active_tenants`: pastikan `releaseExpiredLocks` dijalankan ke seluruh cabang aktif, bukan hanya satu.
8. Jalankan seluruh regression suite existing (payment, shift, booking, RBAC) **di dalam konteks minimal 2 tenant berbeda** untuk memastikan tidak ada asumsi single-database yang tersembunyi di kode lama.

---

## 7. Ruang Lingkup & Batasan (Out of Scope v1)

- **Akun customer lintas cabang** (1 login dipakai belanja di semua cabang) — v1 setiap cabang memiliki basis customer yang sepenuhnya independen.
- **Dashboard real-time lintas cabang** — v1 hanya laporan agregasi harian terjadwal (FR-06), bukan live.
- **Migrasi cabang ke server fisik terpisah** — skema `db_host` di tabel `tenants` sudah disiapkan untuk kemungkinan ini, tapi implementasi pemindahan aktual tidak termasuk v1.
- **Fitur/konfigurasi berbeda per cabang** (feature flag per tenant) — v1 seluruh cabang menjalankan set fitur yang identik dari codebase yang sama.
- **Migrasi data venue existing Club 61 saat ini menjadi "cabang pertama"** — perlu rencana migrasi terpisah (memindahkan database production yang sudah berjalan menjadi tenant pertama di arsitektur baru ini) yang harus didokumentasikan sebagai runbook tersendiri sebelum eksekusi, bukan bagian otomatis dari PRD ini. Lihat `RUNBOOK_MIGRASI_CABANG_PERTAMA.md` — disiapkan lebih awal, dieksekusi hanya menjelang go-live (saat database ini sudah/akan menyimpan data transaksi customer sungguhan).
