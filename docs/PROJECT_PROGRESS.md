# Roadmap & Progress Checklist: Club 61 Central Backend System

Dokumen pelacak progres (*Single Source of Truth*) untuk memantau status penyelesaian fitur, modul yang sudah beres (`[x]`), dan modul yang siap dikerjakan selanjutnya (`[ ]`).

---

## 🏆 Ringkasan Status Progres Global

| Modul | Deskripsi | Status | Progress |
| :--- | :--- | :---: | :---: |
| **Modul 01** | Core Auth, Multi-Door (Web, API Sanctum, Filament RBAC) | **Selesai** | `100%` |
| **Modul 02** | Padel Court Booking Engine (Core Flow & Payment) | **Selesai (Core)** | `90%` |
| **Modul 03** | Wellness & Sauna (Cold Plunge & Finnish Sauna) | **Database Ready** | `25%` |
| **Modul 04** | Salon & Beauty Appointments (Stylist Stacking) | **Database Ready** | `25%` |
| **Modul 05** | Gym Membership & QR Gate Pass | **Database Ready** | `25%` |
| **Modul 06** | F&B Cafe, Table QR Ordering & Kitchen KOT (BOM) | **Database & UI KDS Ready** | `40%` |
| **Modul 07** | Merchandise Retail | **Database Ready** | `25%` |
| **Modul 08** | POS Frontdesk, Split Bill & Multi-Gateway | **Database & UI POS Ready** | `50%` |

---

## 🎾 MODUL 02: PADEL COURT BOOKING ENGINE

### Status: **90% SELESAI (CORE FLOW PRODUCTION-HARDENED)**
Alur utama pemesanan lapangan oleh customer dari memilih jam, kuncian slot, pembayaran payment gateway, hingga tiket QR check-in di venue sudah **100% SELESAI dan lulus 51 automated tests**. 

Tersisa 2 fitur pendukung opsional: **Dropdown Pemilihan Pelatih (Coach)** dan **Alur Reschedule Mandiri**.

### ✅ Yang Sudah Selesai Penuh:
- [x] **Matriks Jadwal Real-time (06:00 - 23:00 WIB)**: Endpoint `GET /api/v1/padel/schedule` timezone-aware.
- [x] **Diferensiasi Tarif Jam**: Otomatis membedakan Tarif Reguler vs Prime Time (17:00+ & Akhir Pekan).
- [x] **Privasi Terproteksi**: Identitas pemain di jadwal publik di-masking (`BOOKED`).
- [x] **Katalog Sewa Alat (Add-Ons)**: Endpoint `GET /api/v1/padel/equipments` (Raket Carbon, Bola, dll.).
- [x] **Two-Tier Concurrency Lock (Anti-Double Booking)**:
  - [x] Tier 1: Distributed Cache Lock (TTL 600s) dengan *sorted keys* anti-deadlock.
  - [x] Tier 2: Database Pessimistic Lock (`SELECT ... FOR UPDATE`) rumus matematika terbuka (`<` dan `>`).
- [x] **Atomic Multi-Slot (All-or-Nothing)**: Jika 1 slot bentrok saat hold multi-jam, seluruh batch otomatis dibatalkan (409 Conflict).
- [x] **Auto Expiry Garbage Collection (10 Menit)**: Scheduler `padel:release-expired-slots` berjalan tiap 1 menit melepaskan slot `LOCKED` yang ditinggal tanpa pembayaran.
- [x] **Countdown Timer di Web Customer**: Timer digital di `/cart` dan `/checkout` dengan auto-freeze & modal sesi habis.
- [x] **Checkout Idempotent**: Header `X-Idempotency-Key` (TTL 24h) anti-debit ganda.
- [x] **Multi-Driver Payment Gateway**:
  - [x] Midtrans Snap Pop-up + SHA512 Signature Webhook Validation.
  - [x] Xendit Invoice + Callback Token Webhook Validation.
  - [x] Mock Driver untuk testing offline.
- [x] **Penyatuan Multi-Jam (Consolidated Invoice)**:
  - [x] Multi-jam nempel (misal: 08:00 - 11:00) digabung menjadi **1 Boarding Pass** & **1 Order ID Resmi**.
### Status: **100% (SELESAI & TERVERIFIKASI PENUH)**
- [x] Two-Tier Distributed Concurrency Lock (Cache Redis/Array 10 menit + DB Pessimistic `FOR UPDATE`).
- [x] Multi-Hour Consolidated Order (Tiket 3 jam nempel = 1 QR & 1 baris invoice).
- [x] Flat Equipment Rental (Sewa raket flat 1x per order, anti perkalian jam).
- [x] Anti-Jadwal Bolong (Sesi terputus otomatis dipecah jadi sub-sesi boarding pass terpisah).
- [x] Integrasi Dual Payment Gateway Webhook (Midtrans QRIS & Xendit Invoice).
- [x] Scan QR Check-in Kasir Frontdesk (Single-use hash, toleransi double scan 30 detik, audit penyerahan raket).
- [x] **Pintu Belakang Admin: Pindah Jadwal (Admin Reschedule)**:
  - [x] Method `adminRescheduleBooking()` di `PadelBookingService.php`:
    - [x] **Anti-Jebakan Durasi Multi-Jam**: Mengunci durasi asli ($D$ jam) dan mengeksekusi Contiguous Check.
    - [x] **Validasi Anti-Tanggal Lampau**: Menolak pemindahan jadwal ke tanggal kemarin (HTTP 422).
    - [x] **Flat Equipment Zero-Overhead**: Raket tetap terikat ke `order_id` tanpa overhead manipulasi data.
    - [x] **Eksekusi Finansial Price Delta**: Kurang Bayar (tagihan supplemental `payments`) & Lebih Bayar (deposit member `refunds`).
    - [x] **Filament Atomic Action**: Terbungkus utuh di dalam `DB::transaction()`.
  - [x] Modal interaktif *"Pindah Jadwal"* di Filament Admin `/admin/kelola-pemesanan`.
- [x] **Pintu Belakang Admin: Pelunasan Tagihan Menggantung (Quick Settle)**:
  - [x] Badge merah mencolok `⚠️ KURANG BAYAR: Rp ... (QR Ditahan)` di tabel Filament.
  - [x] Method `adminSettleSupplementalPayment()` dan modal 1-klik kasir untuk melunasi dan merilis QR tiket.
- [x] **Pintu Belakang Admin: Batalkan & Refund (Admin Void/Refund)**:
  - [x] Method `adminCancelAndRefund()` di `PadelBookingService.php` (set status `REFUNDED`/`CANCELLED`, revoke QR, catat audit di tabel `refunds`, rilis slot lapangan ke publik).
  - [x] Modal aksi *"Batalkan & Refund"* di Filament Admin `/admin/kelola-pemesanan`.
- [x] **Dropdown Pemilihan Pelatih (Coach Padel)**: Kolom `coach_id` & `coach_fee` terintegrasi di skema.
- [x] **Konfigurasi Jumlah Lapangan Pasti**: 4 Lapangan aktif (Panoramic Pro, Panoramic Elite, Club Elite, Center Court) siap dikonfigurasi ulang saat data venue Medan final.

---

## 🧖 MODUL 03: WELLNESS (COLD PLUNGE & SAUNA)

### Status: **25% (DATABASE & SEEDER READY)**
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

## 💇 MODUL 04: SALON & BEAUTY TREATMENT

### Status: **25% (DATABASE & SEEDER READY)**
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

## 🏋️ MODUL 05: GYM & MEMBERSHIP PASS

### Status: **25% (DATABASE & SEEDER READY)**
- [x] Skema Tabel Database: `gym_packages`, `gym_memberships`, `gym_checkins`.
- [x] Data Seeder Bawaan (Paket Bulanan, 10-Sessions Flexi, VIP Annual).
- [ ] Endpoint API:
  - [ ] `GET /api/v1/gym/packages`
  - [ ] `POST /api/v1/gym/memberships` (Beli paket membership)
  - [ ] `POST /api/v1/gym/checkin` (Scan QR pass di pintu masuk gym)
- [ ] Halaman Web Customer Portal `/gym` (Beli membership & kartu member digital).

---

## ☕ MODUL 06: CAFE F&B, TABLE QR & BARISTA KOT

### Status: **40% (DATABASE & DEMO KDS READY)**
- [x] Skema Tabel Database: `fnb_categories`, `fnb_menus`, `fnb_modifier_groups`, `fnb_modifier_options`, `raw_materials`, `recipe_boms`, `table_qr_codes`.
- [x] Data Seeder Menu, Bahan Baku, dan BOM Resep.
- [x] Layar Kitchen Display System (KDS) di `/kitchen/kds`.
- [ ] Endpoint Table QR:
  - [ ] `GET /api/v1/fnb/menus`
  - [ ] `POST /api/v1/fnb/orders` (Pesan via QR meja)
- [ ] Otomasi Pengurangan Stok Bahan Baku (BOM) saat order `PAID`.
- [ ] Halaman Web Customer Self-Order Table QR.

---

## 💳 MODUL 08: POS FRONTDESK & SPLIT BILL

### Status: **50% (DATABASE & DEMO POS READY)**
- [x] Skema Tabel Database: `orders`, `order_items`, `bill_splits`, `bill_split_items`, `payments`, `vouchers`.
- [x] Layar POS Frontdesk Kasir di `/pos`.
- [ ] Fitur Split Bill:
  - [ ] Split `EQUAL` (Bagi rata dengan pembagian sisa rupiah ganjil).
  - [ ] Split `BY_ITEM` (Bayar sesuai makanan/minuman masing-masing).
- [ ] Keranjang Terpadu POS Kasir (Booking Lapangan + Kafe + Raket dalam 1 struk).
