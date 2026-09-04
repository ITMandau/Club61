# CHANGELOG: Club 61 Integrated Dashboard Backend

Catatan riwayat pembaruan sistem dan evolusi arsitektur.

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
