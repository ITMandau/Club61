# Product Requirements Document (PRD)
## Modul 05: Membership System & Loyalty Pass ("Sistem Keanggotaan Terpadu")
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-05-MEMBERSHIP-SYSTEM` |
| **Versi** | `v1.0.0-PROD-SPEC` |
| **Status** | **Ready for Review & Execution** |
| **Penulis** | Lead Backend Architect & Senior Venue Operations Manager |
| **Target Pengguna** | Customer Web Portal, Kasir Frontdesk (POS), Super Admin (Filament Backoffice) |
| **Prinsip Utama** | **100% REAL DATABASE-DRIVEN, ATOMIC DISCOUNT CALCULATION, SEAMLESS INTEGRATION WITH PADEL BOOKING & POS** |

---

## 1. Latar Belakang & Masalah (Problem Statement)

### 1.1. Akar Masalah Saat Ini (Inkonsistensi Sistem)
1. **Mockup Kosmetik di Frontend**: Pada tampilan customer portal (`/dashboard`, `/my-club`) dan admin panel (`/admin/kustomer`), sebelumnya tertera label-label seperti *"Tier Gold III"*, *"VIP Platinum Member"*, *"Poin Reward"*, dan *"Diskon 25%"*.
2. **Ketiadaan Mesin di Backend**: Di sisi backend dan database, belum ada logika transaksi atau integrasi keanggotaan. Alur yang berjalan 100% murni reservasi lapangan reguler.
3. **Kebutuhan Bisnis Venue di Medan**: Venue Club 61 membutuhkan sistem keanggotaan riil untuk:
   * Mengunci loyalitas pemain reguler (*recurring revenue* dari biaya langganan bulanan/tahunan).
   * Memberikan keistimewaan harga (diskon lapangan & sewa alat) secara otomatis saat member booking online.
   * Memberikan kartu member digital ber-QR Code untuk identifikasi fisik di venue dan akses fasilitas (wellness/gym).

---

## 2. Struktur Tiering & Benefit Keanggotaan

Sistem keanggotaan dirancang dengan 3 Tier utama dan 1 Paket Sesi Kunjungan:

| Tier / Paket | Biaya & Masa Aktif | Benefit Lapangan Padel | Benefit Sewa Alat | Benefit Fasilitas Lain |
| :--- | :--- | :--- | :--- | :--- |
| **Silver Member** | Rp 350.000 / 30 Hari | Diskon 10% Sewa Lapangan (Reguler & Prime) | Diskon 10% Sewa Raket | Akses Locker Standar |
| **Gold Member** | Rp 750.000 / 30 Hari | Diskon 15% Sewa Lapangan & Booking Prioritas H-7 | Gratis 1 Raket per Transaksi Booking | Akses Gym & Shower |
| **Platinum VIP** | Rp 6.500.000 / 365 Hari | Diskon 25% Sewa Lapangan, Booking Prioritas H-14, Kuota 2 Jam Gratis Main per Bulan | Gratis Seluruh Raket & Bola per Transaksi | Akses Bebas Cold Plunge & Sauna, Parkir VIP Valet |
| **10-Sessions Pass** | Rp 500.000 / 60 Hari | Tarif Flat Sesi Reguler | - | Kuota 10x Akses Masuk Fasilitas |

---

## 3. Arsitektur Data & Skema Database

Memanfaatkan dan menyempurnakan tabel yang telah disiapkan di skema migration `2026_09_03_150005_create_gym_tables.php` serta integrasi ke tabel `padel_bookings` dan `orders`:

### 3.1. Tabel `gym_packages` (Katalog Paket Membership)
* `id` (UUID, Primary Key)
* `name` (VARCHAR, misal: "Gold Member Pass", "VIP Platinum Annual")
* `tier` (VARCHAR: `SILVER`, `GOLD`, `PLATINUM`, `PASS`)
* `duration_days` (INT: 30, 60, 365)
* `price` (DECIMAL 12,2)
* `court_discount_percent` (DECIMAL 5,2, misal: 10.00, 15.00, 25.00)
* `free_racket_quantity` (INT, default 0)
* `is_all_facility_access` (BOOLEAN, default false)
* `is_active` (BOOLEAN, default true)

### 3.2. Tabel `gym_memberships` (Data Kartu Member Pengguna)
* `id` (UUID, Primary Key)
* `membership_code` (VARCHAR 30, UNIQUE, misal: `MBR-2026-0001`)
* `user_id` (UUID, Foreign Key ke `users.id`)
* `package_id` (UUID, Foreign Key ke `gym_packages.id`)
* `order_id` (UUID, Foreign Key ke `orders.id` untuk audit pembayaran)
* `start_date` (DATE)
* `end_date` (DATE)
* `remaining_visits` (INT NULL, untuk paket sesi)
* `status` (VARCHAR: `ACTIVE`, `EXPIRED`, `FROZEN`)
* `qr_pass_hash` (VARCHAR 100, UNIQUE, untuk verifikasi barcode/QR di gate/kasir)

### 3.3. Relasi ke `padel_bookings` (Audit Diskon Finansial)
Pada tabel `padel_bookings`, ditambahkan tracking diskon:
* `membership_id` (UUID NULL, Foreign Key ke `gym_memberships.id`)
* `member_discount_court` (DECIMAL 12,2, default 0.00)
* `member_discount_equipment` (DECIMAL 12,2, default 0.00)
* Formula Finansial:
  $$\text{Total Bayar} = (\text{Tarif Normal Lapangan} - \text{Diskon Member}) + (\text{Sewa Alat} - \text{Diskon Alat}) + \text{Coach Fee}$$

---

## 4. Spesifikasi Kebutuhan Fungsional (Functional Requirements)

### FR-01: Pembelian Paket Membership (Customer Online & Kasir Frontdesk)
1. **Online via Web Customer (`/membership`)**:
   * Customer memilih paket tier (Silver, Gold, Platinum).
   * Sistem membuat transaksi di tabel `orders` dan membuka pembayaran Midtrans Snap / Xendit Invoice.
   * Saat webhook settlement diterima (`status: PAID`), sistem otomatis:
     * Men-generate `membership_code` unik.
     * Mengisi `start_date = hari ini` dan `end_date = hari ini + duration_days`.
     * Menghasilkan hash QR digital `qr_pass_hash`.
     * Mengubah status menjadi `ACTIVE`.
2. **Offline via POS Kasir (`/pos`)**:
   * Kasir dapat menjual paket membership langsung di meja frontdesk (pembayaran Tunai / EDC / QRIS).
   * Kartu langsung aktif seketika di tangan customer.

### FR-02: Otomasi Diskon Saat Reservasi Lapangan Padel
1. **Pengecekan Member Real-time (Anti-Future Booking Exploit)**:
   * Saat customer membuka `/booking` atau melakukan checkout `POST /api/v1/padel/bookings/checkout`:
   * Backend memeriksa apakah akun customer memiliki relasi `gym_memberships` dengan kondisi ketat:
     $$\text{status} = \text{'ACTIVE'} \quad \text{AND} \quad \mathbf{end\_date \ge booking\_date}$$
     *(Masa aktif kartu member WAJIB meng-cover tanggal bermain lapangan `booking_date`, bukan tanggal transaksi `today()`. Mencegah pemain yang masa aktifnya habis tanggal 20 memesan lapangan tanggal 25 dengan diskon member).*
2. **Kalkulasi Pemotongan Harga Otomatis**:
   * Jika member berstatus **Gold Member** (Diskon 15%):
     * Tarif Lapangan Rp 300.000 $\rightarrow$ Diskon Rp 45.000 $\rightarrow$ Biaya Lapangan menjadi Rp 255.000.
     * Sewa 1 Raket Babolat Rp 50.000 $\rightarrow$ Gratis 1 raket (Rp 0).
3. **Pencegahan Eksploitasi**:
   * Diskon hanya berlaku jika pemesan utama adalah pemilik kartu member yang sedang login.
   * Perhitungan diskon dieksekusi ketat di backend (bukan mengandalkan kiriman angka dari frontend).
4. **Alur Pengurangan & Pengembalian Kuota "10-Sessions Pass"**:
   * **Saat Booking Lunas (`status: PAID`)**: Jika pemesan menggunakan benefit Session Pass, sistem langsung mengeksekusi dekremen kuota:
     $$\text{remaining\_visits} = \text{remaining\_visits} - 1$$
     Jika `remaining_visits` mencapai 0, status kartu otomatis diubah menjadi `EXPIRED`.
   * **Saat Tiket Dibatalkan & Refund (Admin Override Modul 02)**: Jika booking yang memakai kuota sesi dibatalkan lewat `adminCancelAndRefund()`, kuota sesi **WAJIB dikembalikan secara atomik**:
     $$\text{remaining\_visits} = \text{remaining\_visits} + 1$$
     Jika status kartu sebelumnya `EXPIRED` karena kehabisan sesi, status dikembalikan menjadi `ACTIVE` (selama `end_date >= today()`).

### FR-03: Kartu Member Digital di Layar Customer Portal
1. **Layar Beranda Customer (`/dashboard`)**:
   * Menampilkan kartu digital dinamis: Nama Customer, Tier Member riil (misal: "Gold Member"), Kode Member (`MBR-XXXX`), dan tanggal masa berlaku riil (`end_date`).
   * Menampilkan QR Code kartu digital yang dapat di-tap di meja kasir.
   * Jika belum punya member / sudah expired: Menampilkan tombol ajakan *"Pilih Paket Membership"*.
2. **Layar My Club (`/my-club`)**:
   * Menampilkan daftar benefit aktif yang berhak dinikmati oleh member tersebut sesuai tier-nya.

### FR-04: Manajemen Member di Panel Admin (Filament Backoffice `/admin/kustomer`)
1. **Tabel Data Kustomer & Member Riil**:
   * Menghubungkan tabel database ke tabel riil `users` yang berelasi dengan `gym_memberships`.
   * Kolom: Nama, No HP, Status Member (Non-Member / Silver / Gold / Platinum), Tanggal Berakhir, Total Reservasi Lapangan.
2. **Aksi Manajemen Kasir/Admin**:
   * Tombol **+ Daftarkan Member Manual**: Daftarkan member baru dari meja admin.
   * Tombol **Perpanjang Masa Aktif**: Tambah durasi hari jika customer bayar tunai di venue.
   * Tombol **Freeze / Nonaktifkan**: Bekukan kartu jika ada pelanggaran tata tertib venue.

### FR-05: Auto-Expired Membership Scheduler
* Command Laravel `php artisan membership:sync-expired` berjalan setiap hari pukul 00:01 WIB.
* Mengubah status record `gym_memberships` yang telah melewati `end_date` dari `ACTIVE` menjadi `EXPIRED`.
* Akun customer yang expired otomatis kembali dikenakan tarif booking reguler tanpa diskon.

---

## 5. 5 Invarian QA & Keamanan Data (Architectural Invariants)

| No | Invarian | Mekanisme Proteksi Backend |
| :--- | :--- | :--- |
| 1 | **Anti-Manipulasi Diskon** | Seluruh perhitungan potongan harga dihitung langsung oleh `PadelBookingService` berdasarkan data tier di database. Request payload dari user tidak boleh mengirim nilai rupiah diskon sendiri. |
| 2 | **Validasi Tanggal Main (Anti-Future Booking)** | Pengecekan masa aktif member divalidasi terhadap `booking_date` pertandingan, bukan tanggal checkout. Jika `end_date < booking_date`, diskon otomatis ditolak (0%). |
| 3 | **Logika Perpanjangan vs Upgrade Tier** | &bull; **Paket Sama (Perpanjangan)**: Durasi ditambahkan $\text{end\_date} = \text{end\_date\_lama} + \text{duration\_days}$.<br>&bull; **Paket Berbeda (Upgrade/Downgrade)**: Status kartu lama langsung di-set `UPGRADED` / `EXPIRED`. Record baru diterbitkan dengan $\text{start\_date} = \text{today()}$ dan $\text{end\_date} = \text{today()} + \text{duration\_days}$. Sisa hari tier lama di-reset/hangus. |
| 4 | **Integritas Pembukuan Finansial & Reversal Kuota** | Setiap pembelian membership wajib menerbitkan nomor order resmi di tabel `orders` dan tercatat di tabel `payments`. Pembatalan booking sesi otomatis mengembalikan kuota `remaining_visits + 1`. |
| 5 | **Single-Use QR Security** | Hash QR Pass (`qr_pass_hash`) di-generate menggunakan string acak berkeamanan tinggi (`Str::random(40)`) dan diikat ke ID pengguna. |

---

## 6. Rencana Tahapan Eksekusi (Implementation Steps)

1. **Fase 1: Penyesuaian Skema Database & Model**
   * Menambahkan kolom diskon pada `gym_packages` dan kolom tracking diskon pada `padel_bookings`.
   * Memperbarui model `GymPackage`, `GymMembership`, dan `PadelBooking`.
2. **Fase 2: Service & Logika Bisnis**
   * Membuat `MembershipService.php` (logika beli member, perpanjang, aktivasi, dan validasi benefit).
   * Mengintegrasikan kalkulasi diskon member ke dalam `PadelBookingService.php`.
3. **Fase 3: Customer Portal & Checkout Membership**
   * Halaman katalog paket membership `/membership`.
   * Integrasi checkout payment gateway Midtrans/Xendit untuk paket membership.
   * Tampilan kartu member digital dinamis di `/dashboard` dan `/my-club`.
4. **Fase 4: Filament Admin Backoffice**
   * Menghubungkan halaman `/admin/kustomer` ke database riil (Livewire & Eloquent query).
   * Modal tambah member dan perpanjang masa aktif.
5. **Fase 5: Automated Testing & Verifikasi**
   * Menulis unit & feature test untuk memastikan seluruh 5 invarian lolos 100%.
