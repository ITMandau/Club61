# Roadmap & Progress Checklist: Club 61 Central Backend System

Dokumen pelacak progres (Single Source of Truth) untuk memantau status penyelesaian fitur, modul yang sudah beres ([x]), dan modul yang siap dikerjakan selanjutnya ([ ]). Seluruh teks disajikan bersih tanpa ikon atau emoji.

---

## Ringkasan Status Progres Global

| Modul | Deskripsi | Status | Progress |
| :--- | :--- | :---: | :---: |
| Modul 01 | Core Auth, Multi-Door (Web, API Sanctum, Filament RBAC) | Selesai Penuh | 100% |
| Modul 02 | Padel Court Booking Engine (Core Flow, Reschedule & Delta) | Selesai Penuh | 100% |
| Modul 03 | Wellness & Sauna (Cold Plunge & Finnish Sauna) | Database Ready | 25% |
| Modul 04 | Salon & Beauty Appointments (Stylist Stacking) | Database Ready | 25% |
| Modul 05 | Gym Membership & QR Gate Pass | Database Ready | 25% |
| Modul 06 | F&B Cafe, Table QR Ordering & Kitchen KOT (BOM) | Database & UI KDS Ready | 40% |
| Modul 07 | Merchandise Retail | Database Ready | 25% |
| Modul 08 | POS Frontdesk, Split Bill & Multi-Gateway | Database & UI POS Ready | 50% |
| Modul 09 | Enterprise Role & Sidebar Permission Matrix (Club 61 Matrix) | Selesai Penuh | 100% |

---

## MODUL 01: CORE AUTHENTICATION & MULTI-DOOR ACCESS

### Status: 100% SELESAI
- [x] Sistem autentikasi multi-guard (Web Session Guard dan API Sanctum Bearer Token).
- [x] Unifikasi password default dev (`password123`) di seeder dan database aktif.
- [x] Shortcut login username admin (`admin`) pada form web `/login`.
- [x] Smart Multi-Door Redirection berbasis `home_route` peran pengguna:
  - [x] Staf Kasir langsung mendarat di `/pos`.
  - [x] Staf Dapur / Barista langsung mendarat di `/kitchen`.
  - [x] Staf Admin & Super Admin langsung mendarat di `/admin`.
  - [x] Member Customer langsung mendarat di `/dashboard`.
- [x] Tombol navigasi dinamis pada landing page `welcome.blade.php` menyesuaikan peran pengguna yang sedang login.

---

## MODUL 02: PADEL COURT BOOKING ENGINE

### Status: 100% SELESAI (PRODUCTION-HARDENED & ENTERPRISE ARCHITECTURE)
Alur pemesanan lapangan oleh customer dari memilih jam, kuncian slot, pembayaran payment gateway, boarding pass digital, check-in gate kasir, internal override reschedule/refund, pelunasan selisih delta, hingga proteksi anti-premature expiry sudah 100% SELESAI dan lulus automated test suite.

### Yang Sudah Selesai Penuh:
- [x] Matriks Jadwal Real-time (06:00 - 23:00 WIB): Endpoint `GET /api/v1/padel/schedule` timezone-aware.
- [x] Diferensiasi Tarif Jam: Otomatis membedakan Tarif Reguler vs Prime Time (17:00+ & Akhir Pekan).
- [x] Privasi Terproteksi: Identitas pemain di jadwal publik di-masking (`BOOKED`).
- [x] Katalog Sewa Alat (Add-Ons): Endpoint `GET /api/v1/padel/equipments` (Raket Carbon, Bola, dll.).
- [x] Two-Tier Concurrency Lock (Anti-Double Booking):
  - [x] Tier 1: Distributed Cache Lock (TTL 600s keranjang, TTL 86.400s reschedule) dengan sorted keys anti-deadlock.
  - [x] Tier 2: Database Pessimistic Lock (`SELECT ... FOR UPDATE`) rumus matematika terbuka (`<` dan `>`).
- [x] Atomic Multi-Slot (All-or-Nothing): Jika 1 slot bentrok saat hold multi-jam, seluruh batch otomatis dibatalkan (409 Conflict).
- [x] Auto Expiry Garbage Collection (10 Menit & Anti-Premature Guard):
  - [x] Scheduler `padel:release-expired-slots` berjalan tiap 1 menit melepaskan slot `LOCKED` keranjang yang ditinggal tanpa pembayaran.
  - [x] Anti-Premature Expiry Rule: GC strictly kebal (immune) terhadap booking yang memiliki `reschedule_count > 0` atau memiliki record pembayaran sukses (`SUCCESS`).
- [x] Countdown Timer di Web Customer: Timer digital di `/cart` dan `/checkout` dengan auto-freeze & modal sesi habis.
- [x] Checkout Idempotent: Header `X-Idempotency-Key` (TTL 24h) anti-debit ganda.
- [x] Multi-Driver Payment Gateway:
  - [x] Midtrans Snap Pop-up + SHA512 Signature Webhook Validation.
  - [x] Xendit Invoice + Callback Token Webhook Validation.
  - [x] Mock Driver untuk testing offline.
- [x] Penyatuan Multi-Jam (Consolidated Invoice):
  - [x] Multi-jam nempel (misal: 08:00 - 11:00) digabung menjadi 1 Boarding Pass & 1 Order ID Resmi.
- [x] Pelunasan Selisih Tarif Reschedule (Delta Settlement Under Same Order):
  - [x] Endpoint `POST /api/v1/padel/bookings/{id}/retry-payment` menagihkan hanya nominal selisih delta via Midtrans Snap.
  - [x] Konsolidasi pembayaran di bawah `order_id` yang sama, baik pelunasan via Kasir Frontdesk maupun Online.
  - [x] Webhook Midtrans otomatis rilis QR Turnstile begitu selisih delta lunas.
  - [x] Data delta transparan di API tiket (`has_pending_delta`, `unpaid_delta`, `total_paid`) dan customer invoice.
- [x] Pintu Belakang Admin: Pindah Jadwal (Admin Reschedule):
  - [x] Anti-Jebakan Durasi Multi-Jam: Mengunci durasi asli (D jam) dan mengeksekusi Contiguous Check.
  - [x] Validasi Anti-Tanggal Lampau: Menolak pemindahan jadwal ke tanggal kemarin (HTTP 422).
  - [x] Flat Equipment Zero-Overhead: Raket tetap terikat ke `order_id` tanpa overhead manipulasi data.
  - [x] Eksekusi Finansial Price Delta: Kurang Bayar (tagihan supplemental `payments`) & Lebih Bayar (deposit member `refunds`).
  - [x] Filament Atomic Action: Terbungkus utuh di dalam `DB::transaction()`.
  - [x] Modal interaktif "Pindah Jadwal" di Filament Admin `/admin/kelola-pemesanan`.
- [x] Pintu Belakang Admin: Pelunasan Tagihan Menggantung (Quick Settle):
  - [x] Notifikasi status kurang bayar dan penahanan QR pada tabel Filament.
  - [x] Method `adminSettleSupplementalPayment()` dan modal 1-klik kasir untuk melunasi dan merilis QR tiket.
- [x] Pintu Belakang Admin: Batalkan & Refund (Admin Void/Refund):
  - [x] Method `adminCancelAndRefund()` di `PadelBookingService.php` (set status `REFUNDED`/`CANCELLED`, revoke QR, catat audit di tabel `refunds`, rilis slot lapangan ke publik).
  - [x] Modal aksi "Batalkan & Refund" di Filament Admin `/admin/kelola-pemesanan`.
- [x] Scan QR Check-in Kasir Frontdesk & Turnstile Gate:
  - [x] Single-use hash, toleransi double scan 30 detik, audit penyerahan raket, dan penolakan jika tiket memiliki tagihan delta belum lunas.
- [x] Refaktor Arsitektur Modular (Concerns Traits):
  - [x] `PadelBookingService.php` (29 baris) merangkai 5 traits terisolasi: `ManagesScheduleAndSlots`, `ManagesCheckoutAndPayments`, `ManagesCheckInAndTurnstile`, `ManagesTicketsAndRefunds`, dan `ManagesRescheduleAndCashier`.
- [x] Dropdown Pemilihan Pelatih (Coach Padel): Kolom `coach_id` & `coach_fee` terintegrasi di skema.
- [x] Konfigurasi Jumlah Lapangan Pasti: 4 Lapangan aktif (Panoramic Pro, Panoramic Elite, Club Elite, Center Court).

---

## MODUL 03: WELLNESS (COLD PLUNGE & SAUNA)

### Status: 25% (DATABASE & SEEDER READY)
- [x] Skema Tabel Database: `wellness_facilities`, `wellness_slots`, `wellness_bookings`, `wellness_waitlists`.
- [x] Data Seeder Bawaan (Ice Bath / Cold Plunge & Finnish Cedarwood Sauna).
- [ ] Endpoint API Publik:
  - [ ] `GET /api/v1/wellness/facilities` (Daftar fasilitas & durasi).
  - [ ] `GET /api/v1/wellness/slots?facility_id=&date=` (Jadwal sesi & sisa kuota).
- [ ] Endpoint Pemesanan:
  - [ ] `POST /api/v1/wellness/bookings` (Reservasi sesi & atomic headcount decrement).
  - [ ] `POST /api/v1/wellness/waitlist` (Masuk antrean otomatis jika sesi penuh).
- [ ] Halaman Web Customer Portal `/wellness` (Katalog sesi, pilih jam, countdown tiket).

---

## MODUL 04: SALON & BEAUTY TREATMENT

### Status: 25% (DATABASE & SEEDER READY)
- [x] Skema Tabel Database: `salon_services`, `salon_appointments`, `salon_appointment_services`.
- [x] Data Seeder Bawaan (Haircut, Balayage Treatment, Scalp Spa, Stylist Siti).
- [ ] Endpoint API Publik:
  - [ ] `GET /api/v1/salon/services` (Daftar treatment & estimasi menit).
  - [ ] `GET /api/v1/salon/stylists` (Daftar stylist aktif).
- [ ] Algoritma Stylist Duration Stacking:
  - [ ] Perhitungan total durasi gabungan multi-service (misal: Potong 45m + Warna 90m = 135m).
  - [ ] Anti-overlap timeline stylist.
- [ ] Endpoint Pemesanan: `POST /api/v1/salon/appointments`.
- [ ] Halaman Web Customer Portal `/salon` (Katalog treatment, pilih stylist, appointment picker).

---

## MODUL 05: GYM & MEMBERSHIP PASS

### Status: 25% (DATABASE & SEEDER READY)
- [x] Skema Tabel Database: `gym_packages`, `gym_memberships`, `gym_checkins`.
- [x] Data Seeder Bawaan (Paket Bulanan, 10-Sessions Flexi, VIP Annual).
- [ ] Endpoint API:
  - [ ] `GET /api/v1/gym/packages`
  - [ ] `POST /api/v1/gym/memberships` (Beli paket membership)
  - [ ] `POST /api/v1/gym/checkin` (Scan QR pass di pintu masuk gym)
- [ ] Halaman Web Customer Portal `/gym` (Beli membership & kartu member digital).

---

## MODUL 06: CAFE F&B, TABLE QR & BARISTA KOT

### Status: 40% (DATABASE & DEMO KDS READY)
- [x] Skema Tabel Database: `fnb_categories`, `fnb_menus`, `fnb_modifier_groups`, `fnb_modifier_options`, `raw_materials`, `recipe_boms`, `table_qr_codes`.
- [x] Data Seeder Menu, Bahan Baku, dan BOM Resep.
- [x] Layar Kitchen Display System (KDS) di `/kitchen`.
- [ ] Endpoint Table QR:
  - [ ] `GET /api/v1/fnb/menus`
  - [ ] `POST /api/v1/fnb/orders` (Pesan via QR meja)
- [ ] Otomasi Pengurangan Stok Bahan Baku (BOM) saat order `PAID`.
- [ ] Halaman Web Customer Self-Order Table QR.

---

## MODUL 07: MERCHANDISE RETAIL

### Status: 25% (DATABASE & SEEDER READY)
- [x] Skema Tabel Database: `merch_products`, `merch_variants`, `merch_stocks`, `merch_stock_mutations`.
- [x] Data Seeder Produk & Varian Ukuran Apparel Club 61.
- [ ] Endpoint API:
  - [ ] `GET /api/v1/merch/products` (Katalog produk & stok per varian).
  - [ ] `POST /api/v1/merch/checkout` (Pembelian merchandise).
- [ ] Halaman Web Customer Portal `/merch`.

---

## MODUL 08: POS FRONTDESK & SPLIT BILL

### Status: 50% (DATABASE & DEMO POS READY)
- [x] Skema Tabel Database: `orders`, `order_items`, `bill_splits`, `bill_split_items`, `payments`, `vouchers`.
- [x] Layar POS Frontdesk Kasir di `/pos`.
- [x] Fitur Check-In Tiket Padel terintegrasi di POS Frontdesk.
- [ ] Fitur Split Bill:
  - [ ] Split `EQUAL` (Bagi rata dengan pembagian sisa rupiah ganjil).
  - [ ] Split `BY_ITEM` (Bayar sesuai makanan/minuman masing-masing).
- [ ] Keranjang Terpadu POS Kasir (Booking Lapangan + Kafe + Raket dalam 1 struk).

---

## MODUL 09: ENTERPRISE ROLE & PERMISSION MATRIX

### Status: 100% SELESAI (ENTERPRISE GRANULAR SECURITY)
- [x] **Integrasi Spatie Permission & Filament Shield**:
  - [x] 5 Tabel relasional dinamis: `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions`.
  - [x] Kustomisasi Kunci Morf ULID: Kolom `model_id` pada pivot Spatie dikonfigurasi bertipe `char(26)` untuk mendukung penuh primary key ULID `users`.
  - [x] Pembersihan Cache Otomatis: Inisialisasi `forgetCachedPermissions()` di baris paling atas seeder anti-stale data.
- [x] **Enterprise Role & Sidebar Permission Matrix (Club 61 Matrix)**:
  - [x] Service class terpusat `Club61PermissionMatrix.php` memetakan total 63 izin granular pada 11 kategori modul venue tanpa ikon maupun emoji.
  - [x] Penambahan kolom `home_route` dan `description` pada tabel `roles`.
  - [x] Model kustom `App\Models\Role` terhubung global di `config/permission.php`.
- [x] **Resource Backoffice Kustom (`RoleResource`) di Filament**:
  - [x] Tabel Roles: Kolom ID, SLUG, NAME, MENUS (jumlah izin aktif), HOME ROUTE (badge warna tujuan login), dan tombol aksi Edit / Hapus.
  - [x] Form & Modal Edit Role: Input SLUG, NAME, Dropdown HOME ROUTE, serta kartu matriks 11 kategori modul dengan kotak centang sub-modul dan tombol pintas Select All / Deselect All per modul.
  - [x] Otomatis menonaktifkan resource default Shield agar tidak terjadi duplikasi menu.
- [x] **Dynamic Home Route Redirection**:
  - [x] Autentikasi web `/login` langsung mengarahkan user sesuai `home_route` perannya (`/admin`, `/pos`, `/kitchen`, `/dashboard`).
- [x] **Proteksi Navigasi Halaman Filament (`HasPageShield`)**:
  - [x] Trait `HasPageShield` aktif pada seluruh 10 halaman admin Filament (`Analytics`, `BookingSystem`, `Dashboard`, `KelolaClub`, `KelolaKaryawan`, `KelolaPemesanan`, `KelolaTurnamen`, `Kustomer`, `Marketing`, `MasterData`).
- [x] **Automated Test Suite**:
  - [x] Lulus 100% (**80 passed, 365 assertions**).
