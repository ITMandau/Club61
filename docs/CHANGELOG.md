# CHANGELOG: Club 61 Integrated Dashboard Backend

Catatan riwayat pembaruan sistem dan evolusi arsitektur.

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
