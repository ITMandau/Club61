# Panduan Lengkap Backend Club 61 untuk Pemula & Developer Flutter

Selamat datang di repositori backend **Club 61 Integrated Dashboard System (`Club61-padel-api`)**!
Dokumen ini dibuat khusus agar siapa pun developer baru (bahkan junior) bisa langsung paham alur sistem, cara menjalankan server, dan cara tim Flutter mengonsumsi API.

---

## 1. Konsep Arsitektur: "1 Rumah dengan 3 Pintu Masuk"

Backend ini dibangun menggunakan **Laravel 11**, **PHP 8.2**, dan **MySQL (MariaDB XAMPP)** dengan prinsip *Single Source of Truth* (1 Database terpadu):

1. **Pintu 1: Web Customer (Laravel Breeze)**
   * URL: `http://localhost:8000/login` & `http://localhost:8000/register`
   * Digunakan oleh pelanggan yang membuka website Club 61 via browser laptop / desktop.
2. **Pintu 2: Mobile App REST API (Laravel Sanctum)**
   * Prefix: `http://localhost:8000/api/v1/...`
   * Mengembalikan format JSON terstandarisasi yang siap dikonsumsi langsung oleh aplikasi **Flutter (iOS & Android)**.
3. **Pintu 3: Dashboard Admin & Kasir Venue (Filament v3)**
   * URL: `http://localhost:8000/admin`
   * Digunakan oleh staf resepsionis, kasir, dan manajer operasional untuk memantau lapangan dan penjualan.

> [!NOTE]
> **Data Terpadu:** Pelanggan yang mendaftar di Web Breeze (Pintu 1) bisa langsung login di aplikasi Flutter (Pintu 2) menggunakan email dan password yang sama tanpa perlu mendaftar ulang!

---

## 2. Cara Menjalankan Server di Laptop Lokal

Untuk menjalankan backend secara penuh di komputer lokal, buka **3 Terminal PowerShell terpisah**:

### Terminal 1: Web & API Server
```powershell
cd D:\Desktop\PRoject\PRoject\Club61-padel-api
php artisan serve --port=8000
```
* Server aktif di: `http://127.0.0.1:8000`

### Terminal 2: Pekerja Antrean (Queue Worker)
```powershell
cd D:\Desktop\PRoject\PRoject\Club61-padel-api
php artisan queue:work
```
* **Fungsi:** Memproses tugas berat di latar belakang (misal: webhook pembayaran Midtrans, kirim email/WhatsApp tiket QR) agar respon API tetap kilat (< 30ms).

### Terminal 3: Penjadwal Otomatis (Scheduler / Cron)
```powershell
cd D:\Desktop\PRoject\PRoject\Club61-padel-api
php artisan schedule:work
```
* **Fungsi:** Menjalankan pembersihan otomatis tiap 1 menit untuk merilis slot lapangan yang di-hold lebih dari 5 menit jika user membatalkan pembayaran.

---

## 3. Akun Bawaan (Default Seeded Accounts)

Seluruh akun di bawah ini memiliki password bawaan: **`Password123!`**

| Nama | Email | Role | Akses Panel |
| :--- | :--- | :--- | :--- |
| Super Admin | `admin@club61.com` | `SUPER_ADMIN` | Filament `/admin` & Full API |
| Kasir Frontdesk | `cashier@club61.com` | `CASHIER` | Filament `/admin` & POS API |
| Barista Cafe | `barista@club61.com` | `KITCHEN` | Filament `/admin` & Kitchen API |
| Coach Budi | `coach.budi@club61.com` | `TRAINER` | Booking Pelatih |
| Siti Stylist | `stylist.siti@club61.com` | `STYLIST` | Appointment Salon |
| Andi (Pelanggan) | `budi@gmail.com` | `CUSTOMER` | Web Breeze & Flutter App |

---

## 4. Kamus Endpoint REST API untuk Tim Flutter

Semua response API dibungkus dalam format seragam:
* Sukses: `{ "success": true, "message": "...", "data": ... }`
* Gagal: `{ "success": false, "message": "...", "errors": ... }`

### A. Modul Otentikasi (`/api/v1/auth`)

#### 1. Registrasi Akun Baru
* **Endpoint:** `POST /api/v1/auth/register`
* **Header:** `Content-Type: application/json`
* **Request Body:**
  ```json
  {
    "name": "Budi Santoso",
    "email": "budi.santoso@gmail.com",
    "phone": "081234567890",
    "password": "Password123!"
  }
  ```
* **Response (201 Created):**
  ```json
  {
    "success": true,
    "message": "Registrasi akun berhasil.",
    "data": {
      "user": {
        "id": "01m1k531b1rsg898ybdpbgsy7m",
        "name": "Budi Santoso",
        "email": "budi.santoso@gmail.com",
        "phone": "081234567890",
        "role": "CUSTOMER"
      },
      "token": "1|qWeRtYuIoP..."
    }
  }
  ```

#### 2. Login
* **Endpoint:** `POST /api/v1/auth/login`
* **Request Body:**
  ```json
  {
    "email": "budi@gmail.com",
    "password": "Password123!"
  }
  ```
* **Response (200 OK):** Mengembalikan data user dan Bearer Token untuk disimpan di Flutter Secure Storage (`SharedPreferences`).

#### 3. Cek Profil Saya
* **Endpoint:** `GET /api/v1/auth/me`
* **Header:** `Authorization: Bearer <token>`

---

### B. Modul Padel (`/api/v1/padel`)

#### 1. Ambil Daftar Lapangan & Alat Sewa
* **Endpoint:** `GET /api/v1/padel/courts`
* **Response (200 OK):** Mengembalikan 4 lapangan padel (Indoor/Outdoor) beserta tarif reguler, tarif prime time, dan daftar raket sewa.

#### 2. Hold / Kunci Slot Lapangan (Anti-Double Booking)
* **Endpoint:** `POST /api/v1/padel/hold-slot`
* **Header:** `Authorization: Bearer <token>`
* **Request Body:**
  ```json
  {
    "court_id": "01m1k531b1rsg898ybdpbgsy7m",
    "booking_date": "2026-09-10",
    "start_time": "19:00",
    "end_time": "20:00",
    "coach_id": null
  }
  ```
* **Response Sukses (201 Created):** Slot terkunci status `LOCKED` selama 5 menit.
* **Response Bentrok (409 Conflict):**
  ```json
  {
    "success": false,
    "message": "Slot lapangan pada jam tersebut sudah dipesan atau sedang dikunci."
  }
  ```

#### 3. Riwayat Booking Saya
* **Endpoint:** `GET /api/v1/padel/my-bookings`
* **Header:** `Authorization: Bearer <token>`

---

### C. Modul Fasilitas Lainnya

* **Wellness (Ice Bath & Sauna):**
  * `GET /api/v1/wellness/facilities` $\rightarrow$ Info kapasitas dan harga per sesi.
  * `GET /api/v1/wellness/slots?date=2026-09-10` $\rightarrow$ Ketersediaan kuota jam sesi.
* **Salon & Stylist:**
  * `GET /api/v1/salon/services` $\rightarrow$ Daftar potong rambut, coloring, spa & daftar stylist.
* **Gym Membership:**
  * `GET /api/v1/gym/packages` $\rightarrow$ Paket bulanan / sesi / tahunan.
* **Cafe (F&B):**
  * `GET /api/v1/fnb/menu` $\rightarrow$ Kategori kopi & makanan beserta stok bahan terintegrasi.
* **Merchandise:**
  * `GET /api/v1/merch/products` $\rightarrow$ Jersey dan apparel resmi Club 61.

---

### D. Mode Simulator Pembayaran (Khusus Developer / QA)

Tim Flutter **TIDAK PERLU** keluar uang sungguhan untuk menguji flow pembayaran lunas:
* **Endpoint:** `POST /api/v1/payments/simulate`
* **Request Body:**
  ```json
  {
    "booking_id": "01m1k531b1rsg898ybdpbgsy7m"
  }
  ```
* **Hasil:** Status booking langsung berubah menjadi `PAID` dan siap untuk proses check-in QR!

---

## 5. Peta Struktur Folder Layar UI (Views)

Untuk pengembangan tampilan web di laptop atau monitor venue, file-filenya sudah dipisahkan dengan rapi di dalam folder `resources/views/`:

```text
resources/views/
â”œâ”€â”€ ðŸ“‚ pos/
â”‚   â””â”€â”€ ðŸ“„ index.blade.php      <- [URL: /pos] Layar Kasir Frontdesk (Touchscreen POS)
â”œâ”€â”€ ðŸ“‚ kitchen/
â”‚   â””â”€â”€ ðŸ“„ kds.blade.php        <- [URL: /kitchen] Layar Monitor Dapur & Barista (KOT)
â”œâ”€â”€ ðŸ“‚ customer/
â”‚   â”œâ”€â”€ ðŸ“„ home.blade.php       <- [URL: /] Halaman utama Web Customer
â”‚   â”œâ”€â”€ ðŸ“„ padel.blade.php      <- Halaman booking lapangan padel online
â”‚   â””â”€â”€ ðŸ“„ cafe.blade.php       <- Halaman menu specialty coffee & makanan
â”œâ”€â”€ ðŸ“‚ auth/                    <- Halaman Login & Register Web bawaan Breeze
â””â”€â”€ ðŸ“‚ layouts/                 <- Rangka template navigasi & footer
```
