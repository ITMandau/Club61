# Product Requirements Document (PRD)
## Modul 10: Unified Payment Orchestrator (Universal Order & Midtrans Gateway)
**Club 61 Sports & Social Club Central System**

---

### Metadata Dokumen
- **Kode Dokumen**: `PRD-MODUL-10-UNIFIED-PAYMENT`
- **Versi**: `v2.1.0-ALIGNED`
- **Status**: **Approved Architecture - Siap Diimplementasikan**
- **Tech Stack**: Laravel 11, Universal `Order` Aggregate, Dynamic Fulfillment Registry, Midtrans Snap, POS Frontdesk Settlement
- **Prinsip Utama**: **SATU PINTU PEMBAYARAN, ZERO DATABASE BLOAT, EAGER ORDER CREATION, MULTI-SLOT BUNDLING READY, OPEN-CLOSED FULFILLMENT REGISTRY, PERSISTED VOUCHER STATE, IDEMPOTENT TRANSACTION**
- **Modul Terdampak**: Modul 02 (Padel Booking), Modul 03/04/05 (Wellness, Salon, Gym), Modul 08 (POS Frontdesk)

---

## 1. Executive Summary & Catatan Revisi Arsitektur (v2.1.0)

Dokumen ini mendefinisikan spesifikasi arsitektur dan teknis untuk **Modul 10: Unified Payment Orchestrator** di Club 61.

Revisi `v2.1.0-ALIGNED` ini menyempurnakan draf arsitektur sebelumnya dengan menutup 6 celah teknis kritis:

1. **Penerapan Open-Closed Principle pada Domain Fulfillment**:
   Logika pemenuhan pesanan pasca-bayar (aktivasi tiket QR, alokasi turnstile gym, dll) dipisahkan dari `PaymentOrchestratorService` menggunakan kontrak `DomainFulfillmentHandlerInterface` dan registry dinamis `PaymentFulfillmentRegistry`. Penambahan modul baru di masa depan (Gym, Salon, Wellness) tidak akan pernah mengubah baris kode di dalam orchestrator inti yang sudah stabil.
2. **Persistensi State Voucher di Database (`orders.voucher_code`)**:
   Menghapus ketergantungan pada Redis/Cache transient untuk penyimpanan kode voucher. Menambahkan kolom `voucher_code` langsung pada tabel `orders` agar pengurangan kuota promo berjalan aman, deterministik, dan dapat dinikmati secara konsisten oleh pemesanan online maupun kasir POS frontdesk.
3. **Standarisasi Placeholder `payments.transaction_id` Saat Checkout**:
   Menetapkan konvensi deterministik bahwa saat checkout pertama kali membuat baris `payments` berstatus `PENDING`, kolom `transaction_id` diisi dengan kode unik `$order->order_number`. Hal ini mematuhi batasan `UNIQUE NOT NULL` pada skema database tanpa risiko tabrakan data sebelum Midtrans menerbitkan respons webhook.
4. **Atribusi Pendapatan yang Akurat (Koreksi `item_type`)**:
   Sewa peralatan lapangan (raket, bola, handuk) secara tegas dikategorikan sebagai `item_type = 'PADEL'`, bukan `'MERCH'`. Hal ini mencegah kekeliruan atribusi omzet antara Modul 02 (Padel) dan Modul 07 (Merchandise Retail) pada laporan analitik finansial.
5. **Penegasan Uji Keamanan Mock & Fail-Closed Gateway**:
   Memasukkan pengujian otomatis untuk isolasi driver simulator mock di production dan verifikasi fail-closed signature Midtrans ke dalam matriks pengujian wajib.
6. **Kelengkapan Matriks Modifikasi Berkas**:
   Mencantumkan seluruh berkas pendukung keamanan (`MockSimulatorDriver.php`, `MidtransService.php`) serta migration pendukung ke dalam rencana eksekusi.

---

## 2. Analisis Akar Masalah (Root Cause Analysis)

### 2.1 Multi-Slot Booking & Keranjang Terkonsolidasi
Pada pemesanan lebih dari satu slot waktu (misal 2 jam berturut-turut) atau bundling sewa lapangan dengan sewa raket:
- **Masalah Lama**: Mengaitkan pembayaran langsung ke record individual `PadelBooking` memaksa controller dan webhook memeriksa apakah transaksi bertipe tunggal atau gabungan.
- **Solusi v2.1**: Seluruh baris booking dalam satu sesi checkout dikaitkan ke satu entitas `Order` yang sama (`padel_bookings.order_id = orders.id`). Model `Order` bertindak sebagai agregat finansial tunggal yang menaungi relasi `Order::padelBookings()`.

### 2.2 Eliminasi Anti-Pattern Lazy Order Creation
Pada kode sebelumnya di `ManagesCheckoutAndPayments.php`:
- Kolom `padel_bookings.order_id` saat checkout hanya diisi string sementara (`ORD-PAD-XXXXXX`), sementara baris tabel `orders` belum dibuat ke database.
- Baris `orders` baru dibuat belakangan (*lazy*) via `ensureBookingOrder()` saat webhook Midtrans tiba atau saat reschedule/refund.
- **Dampak Buruk**: Inkonsistensi tipe data referensi (string kode vs ULID) dan celah *race condition* antar transaksi simultan.
- **Solusi v2.1**: **Eager Order Creation**. Record `orders` dan rincian `order_items` langsung dibuat secara instan di dalam satu `DB::transaction` saat checkout sebelum request pembayaran dikirim ke Midtrans Snap.

### 2.3 Eliminasi Tabel Redundan (`payment_orders`)
Tabel generik `orders`, `order_items`, `payments`, dan `refunds` telah tersedia dari migrasi POS awal (`database/migrations/2026_09_03_150008_create_pos_and_payment_tables.php`). Membuat tabel `payment_orders` terpisah terbukti redundan dan melahirkan duplikasi *state of truth*. Seluruh alur pembayaran disatukan pada tabel `orders` eksisting.

---

## 3. Arsitektur Solusi: Universal Order & Open-Closed Fulfillment

```
                           +------------------------+
                           |    CHECKOUT DOMAIN     |
                           | (Padel, Gym, POS, dst) |
                           +-----------+------------+
                                       |
                                       | 1. Eager create Order & Items
                                       |    (Persist voucher_code di orders)
                                       v
                           +------------------------+
                           |      tabel orders      |
                           |  (order_number: ORD-)  |
                           |  (voucher_code: ...)   |
                           |  (payment_status: ...) |
                           +-----------+------------+
                                       |
                                       | 2. Inisialisasi Sesi Bayar
                    +------------------+------------------+
                    |                                     |
                    v                                     v
       +-------------------------+           +-------------------------+
       |      Midtrans Snap      |           |    Kasir POS Counter    |
       |   (Online / Customer)   |           |    (Cash / EDC Fisik)   |
       +------------+------------+           +------------+------------+
                    |                                     |
                    | 3a. Webhook callback                | 3b. Konfirmasi Lunas
                    v                                     v
       +---------------------------------------------------------------+
       |       PaymentOrchestratorService::markOrderAsPaid($order)     |
       |                                                               |
       |  1. DB::transaction & lockForUpdate pada baris Order         |
       |  2. Idempotency Guard: jika payment_status === 'PAID', exit   |
       |  3. Update orders.payment_status = 'PAID'                     |
       |  4. Catat/Update riwayat di tabel payments (SUCCESS)          |
       |  5. Atomic decrement kuota voucher via orders.voucher_code    |
       +-------------------------------+-------------------------------+
                                       |
                                       | 6. Resolusi Dinamis per item_type
                                       v
                      +--------------------------------+
                      |   PaymentFulfillmentRegistry   |
                      +----------------+---------------+
                                       |
              +------------------------+------------------------+
              |                                                 |
              v                                                 v
+-------------------------------+             +-------------------------------+
|     PadelFulfillmentHandler   |             |   GymFulfillmentHandler       |
|  - Ubah padelBookings ke PAID |             |   (Modul Baru Mendatang)      |
|  - Generate QR E-Tiket        |             |  - Aktivasi Barcode Gate      |
+-------------------------------+             +-------------------------------+
```

---

## 4. Pemanfaatan Skema Database (Zero Bloat + Persisted Voucher)

### 4.1 Tabel `orders` (Single Source of Truth Finansial)
| Kolom | Tipe Data | Keterangan |
| :--- | :--- | :--- |
| `id` | ULID (char 26), PK | Primary Key internal untuk relasi Foreign Key. |
| `order_number` | string(35), unique, index | Kode bisnis transaksi (`ORD-PAD-XXXXXX`) yang dikirim ke Midtrans dan dicetak di struk kasir. |
| `user_id` | ULID, nullable, FK | Akun pelanggan pemesan. |
| `cashier_id` | ULID, nullable, FK | ID staf kasir jika diproses melalui POS counter. |
| `order_type` | string(20) | `ONLINE_BOOKING`, `DINE_IN`, `TAKE_AWAY`, `RETAIL`. |
| `subtotal` | decimal(12,2) | Total nilai sewa lapangan dan peralatan sebelum diskon. |
| `discount_amount` | decimal(12,2) | Nilai potongan promo voucher. |
| `voucher_code` | string(50), nullable, index | **[NEW COLUMN]** Kode voucher promo yang terpasang secara permanen di database. |
| `service_charge` | decimal(12,2) | Biaya layanan / payment gateway fee. |
| `grand_total` | decimal(12,2) | Nominal akhir yang wajib dibayar pelanggan. |
| `payment_status` | string(20), default `UNPAID` | Nilai: `UNPAID`, `PARTIALLY_PAID`, `PAID`, `REFUNDED`, `CANCELLED`. |
| `is_split_bill` | boolean, default false | Indikator fitur split bill kasir POS. |

> **Catatan Migrasi Skema**:
> Dibuatkan satu migration aditif: `database/migrations/2026_09_16_000001_add_voucher_code_to_orders_table.php` untuk menambahkan kolom `voucher_code` nullable setelah `discount_amount`.

### 4.2 Tabel `order_items` (Lini Item Transaksi Lintas Domain)
| Kolom | Tipe Data | Keterangan |
| :--- | :--- | :--- |
| `id` | ULID (char 26), PK | Primary Key internal item. |
| `order_id` | ULID, FK | Relasi ke `orders.id`. |
| `item_type` | string(20) | Nilai enum: `PADEL`, `WELLNESS`, `SALON`, `GYM`, `FNB`, `MERCH`. |
| `reference_id` | string(26), nullable | ID entitas domain (`padel_bookings.id`, `court_equipments.id`, dll). |
| `item_name` | string(150) | Deskripsi item (misal: "Sewa Court 1 (08:00 - 09:00)" atau "Sewa Raket Nox ML10"). |
| `quantity` | integer | Jumlah unit / durasi jam. |
| `unit_price` | decimal(12,2) | Harga per unit. |
| `subtotal` | decimal(12,2) | Nilai total per baris item. |

> **Aturan Atribusi Item Domain**:
> Sewa peralatan Padel (raket, bola, handuk) **wajib menggunakan `item_type = 'PADEL'`** dengan `reference_id` menunjuk ke `court_equipments.id`. Kategori `'MERCH'` dikhususkan secara eksklusif untuk Modul 07 (Penjualan Retail Merchandise Toko Fisik).

### 4.3 Tabel `payments` (Audit Trail Pembayaran Finansial)
| Kolom | Tipe Data | Keterangan |
| :--- | :--- | :--- |
| `id` | ULID (char 26), PK | Primary Key internal pembayaran. |
| `order_id` | ULID, FK | Relasi ke `orders.id`. |
| `payment_gateway` | string(30) | `MIDTRANS`, `CASH`, `EDC_BCA`, `MOCK`. |
| `transaction_id` | string(100), unique | **Konvensi Deterministik**: Diisi `$order->order_number` saat checkout PENDING, di-update ke nomor transaksi Midtrans/Kasir saat settlement. |
| `snap_token` | string(255), nullable | Snap token Midtrans untuk sesi pembayaran frontend. |
| `payment_url` | text, nullable | URL redirect halaman pembayaran Midtrans Snap. |
| `amount` | decimal(12,2) | Nominal uang transaksi. |
| `payment_method` | string(30) | `QRIS`, `CREDIT_CARD`, `BANK_TRANSFER`, `CASH`, `EDC`. |
| `status` | string(20) | `PENDING`, `SUCCESS`, `FAILED`, `EXPIRED`. |
| `payload_log` | json, nullable | Payload respons mentah dari gateway untuk audit rekonsiliasi. |

### 4.4 Tabel `padel_bookings`
| Kolom Kunci | Tipe Data | Keterangan |
| :--- | :--- | :--- |
| `id` | ULID (char 26), PK | Primary Key booking lapangan. |
| `order_id` | string(50), index | Menyimpan Foreign Key `orders.id` (ULID) secara eager saat checkout. |
| `booking_code` | string(20), unique | Kode tiket operasional lapangan. |
| `status` | string(30) | `LOCKED`, `PENDING_PAYMENT`, `PAID`, `CHECKED_IN`, `COMPLETED`, `CANCELLED`. |
| `qr_code_hash` | string(64), nullable | Hash tiket QR terenkripsi yang diaktifkan otomatis saat pembayaran lunas. |

---

## 5. Spesifikasi Fungsional (Functional Requirements)

### FR-01: Eager Order Creation Pada Alur Checkout
- **Lokasi**: `app/Services/Padel/Concerns/ManagesCheckoutAndPayments.php` (`checkout`).
- **Alur Kerja**:
  1. Validasi ketersediaan slot terkunci (`status = 'LOCKED'`).
  2. Hitung biaya lapangan, sewa raket/bola, biaya gerbang, dan potongan voucher promo.
  3. Simpan entitas `Order` secara eager di dalam `DB::transaction`:
     ```php
     $order = Order::create([
         'order_number' => 'ORD-PAD-' . strtoupper(Str::random(10)),
         'user_id' => $user->id,
         'order_type' => 'ONLINE_BOOKING',
         'subtotal' => $courtTotal + $equipmentTotal,
         'discount_amount' => $discountAmount,
         'voucher_code' => $appliedVoucherCode,
         'service_charge' => $gatewayFee,
         'grand_total' => $grandTotal,
         'payment_status' => $initialStatus === 'PAID' ? 'PAID' : 'UNPAID',
     ]);
     ```
  4. Simpan rincian baris di `order_items`:
     - Setiap slot lapangan: `item_type = 'PADEL'`, `reference_id = $booking->id`.
     - Setiap peralatan sewa: `item_type = 'PADEL'`, `reference_id = $equipment->id`.
  5. Perbarui semua `padel_bookings` dalam transaksi dengan `order_id = $order->id` dan status `$initialStatus`.
  6. **Pencatatan Awal `payments` (Determinisme `transaction_id`)**:
     ```php
     Payment::create([
         'order_id' => $order->id,
         'payment_gateway' => strtoupper($paymentMethod === 'CASH' ? 'CASH' : 'MIDTRANS'),
         'transaction_id' => $order->order_number, // Unik & memenuhi constraint NOT NULL
         'snap_token' => $paymentResult['token'] ?? null,
         'payment_url' => $paymentResult['redirect_url'] ?? null,
         'amount' => $order->grand_total,
         'payment_method' => strtoupper($paymentMethod),
         'status' => $initialStatus === 'PAID' ? 'SUCCESS' : 'PENDING',
         'payload_log' => $paymentResult['raw'] ?? null,
     ]);
     ```

### FR-02: Payment Orchestrator Service & Open-Closed Fulfillment Registry
- **File Inti**:
  - `app/Services/Payment/Contracts/DomainFulfillmentHandlerInterface.php`
  - `app/Services/Payment/PaymentFulfillmentRegistry.php`
  - `app/Services/Payment/PaymentOrchestratorService.php`
  - `app/Services/Padel/Handlers/PadelFulfillmentHandler.php`

- **Kontrak Domain Fulfillment**:
  ```php
  namespace App\Services\Payment\Contracts;

  use App\Models\Pos\Order;
  use Illuminate\Support\Collection;

  interface DomainFulfillmentHandlerInterface
  {
      public function fulfill(Order $order, Collection $items): void;
  }
  ```

- **Alur `PaymentOrchestratorService::markOrderAsPaid()`**:
  1. Jalankan `DB::transaction` dengan `lockForUpdate` pada record `Order`.
  2. **Transaction-Level Idempotency Guard**:
     - Idempotency dievaluasi pada level transaksi/pembayaran spesifik, bukan pada status global `Order`. Hal ini menjamin pembayaran selisih (supplemental delta reschedule) tetap dapat diproses meski order sebelumnya sudah berstatus `PAID`.
     - Cari record pembayaran berdasarkan `$paymentDetails['transaction_id']` atau pending payment di bawah order ini.
     - **Jika record payment spesifik ini sudah berstatus `SUCCESS`, segera return** (notifikasi berulang/retry Midtrans ditolak secara aman tanpa efek samping ganda).
  3. **Pencatatan / Pembaruan Status Pembayaran**:
     - Jika record pembayaran pending ditemukan: ubah `status = 'SUCCESS'`, perbarui metode bayar, nominal, dan `payload_log`.
     - Jika tidak ada pending payment (skenario walk-in murni): buatkan baris `payments` baru berstatus `SUCCESS`.
  4. **Kalkulasi State Finansial Order (`PARTIALLY_PAID` vs `PAID`)**:
     - Hitung akumulasi pembayaran sukses: `$totalPaid = (float) $order->payments()->where('status', 'SUCCESS')->sum('amount')`.
     - Jika `$totalPaid >= $order->grand_total`: set `$order->payment_status = 'PAID'`.
     - Jika `$totalPaid < $order->grand_total` dan `$totalPaid > 0`: set `$order->payment_status = 'PARTIALLY_PAID'`.
  5. **Pengurangan Kuota Voucher Berbasis Database (Zero-Cache Dependency)**:
     ```php
     if ($order->voucher_code) {
         Voucher::where('code', $order->voucher_code)
             ->where(function ($q) {
                 $q->whereNull('quota')->orWhere('quota', '>', 0);
             })
             ->decrement('quota');

         Voucher::where('code', $order->voucher_code)->increment('used_count');
     }
     ```
  6. **Delegasi Pemenuhan Domain Dinamis**:
      ```php
      // Menggunakan relasi resmi Order::items() (didukung juga alias Order::orderItems())
      $itemsByType = $order->items->groupBy('item_type');

      foreach ($itemsByType as $itemType => $items) {
          if ($this->registry->hasHandler($itemType)) {
              $handler = $this->registry->getHandler($itemType);
              $handler->fulfill($order, $items);
          }
      }
      ```

- **Implementasi `PadelFulfillmentHandler` (Non-Destructive State Transitions)**:
  ```php
  namespace App\Services\Padel\Handlers;

  use App\Models\Pos\Order;
  use App\Services\Payment\Contracts\DomainFulfillmentHandlerInterface;
  use Illuminate\Support\Collection;

  class PadelFulfillmentHandler implements DomainFulfillmentHandlerInterface
  {
      public function fulfill(Order $order, Collection $items): void
      {
          foreach ($order->padelBookings as $booking) {
              // Guard: Dilarang menimpa status yang sudah lebih maju (CHECKED_IN, COMPLETED, CANCELLED)
              if (in_array($booking->status, ['CHECKED_IN', 'COMPLETED', 'CANCELLED'])) {
                  continue;
              }

              $updateData = [];

              // Transisi hanya untuk booking yang belum lunas
              if (in_array($booking->status, ['LOCKED', 'PENDING_PAYMENT', 'PENDING'])) {
                  $updateData['status'] = 'PAID';
              }

              // Rilis atau aktifkan hash tiket QR jika belum ada atau sempat ditahan karena delta tagihan
              if (empty($booking->qr_code_hash)) {
                  $updateData['qr_code_hash'] = hash_hmac(
                      'sha256',
                      $booking->booking_code . $booking->user_id . $booking->court_id . $booking->start_time->toISOString(),
                      config('app.key')
                  );
              }

              if (! empty($updateData)) {
                  $booking->update($updateData);
              }
          }
      }
  }
  ```

### FR-03: Generalisasi Webhook Controller (Midtrans Gateway)
- **Lokasi**: `app/Http/Controllers/Api/V1/Payment/PaymentWebhookController.php`.
- **Alur Kerja**:
  1. Validasi signature Midtrans (SHA512 dari `order_id + status_code + gross_amount + ServerKey`).
  2. Ekstrak nomor order bisnis: `$realOrderNumber = explode('_', $request->input('order_id'))[0]`.
  3. Temukan `Order` di database:
     ```php
     $order = Order::where('order_number', $realOrderNumber)->first();
     ```
  4. Jika status notifikasi Midtrans adalah `settlement` atau `capture`:
     - Panggil `$orchestrator->markOrderAsPaid($order, [...])`.
  5. Jika status adalah `cancel`, `expire`, atau `deny`:
     - Perbarui `$order->update(['payment_status' => 'CANCELLED'])`.
     - Lepas kuncian slot lapangan terkait.
  6. **Fallback Kompatibilitas Transisi (Zero Regression)**:
     - Jika order tidak ditemukan di tabel `orders` (misal sisa transaksi lama pra-migrasi), controller mencari fallback ke `PadelBooking::where('order_id', ...)->orWhere('booking_code', ...)` agar tidak ada webhook lama yang gagal diproses.

### FR-04: Jalur Settlement Manual Kasir POS Frontdesk & Penanganan Walk-In
- **Lokasi**: `app/Http/Controllers/Api/V1/Pos/PaymentController.php` dan Filament POS Action.
- **Dua Skenario Kasir Frontdesk**:
  1. **Skenario A: Pelunasan Booking Tertunda (Online-to-Store)**:
     - Pelanggan sebelumnya melakukan checkout online memilih opsi bayar tunai di meja kasir (`status = 'PENDING_PAYMENT'`).
     - Sesuai FR-01, entitas `Order` dan baris `Payment` (status `PENDING`, `transaction_id = $order->order_number`) sudah tercipta di database.
     - Kasir frontdesk memilih pesanan tersebut dan mengonfirmasi pelunasan fisik (Cash / EDC BCA).
     - Orchestrator menemukan record `Payment` berstatus `PENDING` dan memperbaruinya menjadi `SUCCESS`.

  2. **Skenario B: Walk-In Murni (Direct POS Order Creation)**:
     - Pelanggan datang langsung ke counter (sewa lapangan dadakan, sewa raket, cafe F&B, atau tiket harian gym).
     - Modul POS (Modul 08) atau staf kasir membuat pesanan langsung: entitas `Order` dan baris `order_items` dibuat secara eager.
     - Saat kasir menekan tombol "Bayar Tunai" / "Gesek EDC", sistem langsung memanggil `PaymentOrchestratorService::markOrderAsPaid($order, ...)`.
     - **Pencegahan Orphan Order**: Di dalam `markOrderAsPaid()`, sistem memeriksa keberadaan record `payments`:
       ```php
       $payment = $order->payments()->where('status', 'PENDING')->latest()->first();
       if ($payment) {
           $payment->update([
               'payment_gateway' => $paymentGateway,
               'transaction_id' => $paymentDetails['transaction_id'],
               'payment_method' => $paymentMethod,
               'amount' => $paymentDetails['amount'] ?? $order->grand_total,
               'status' => 'SUCCESS',
               'payload_log' => $paymentDetails['payload_log'] ?? null,
           ]);
       } else {
           $order->payments()->create([
               'payment_gateway' => $paymentGateway,
               'transaction_id' => $paymentDetails['transaction_id'] ?? ('POS-' . strtoupper($paymentGateway) . '-' . strtoupper(Str::random(10))),
               'amount' => $paymentDetails['amount'] ?? $order->grand_total,
               'payment_method' => $paymentMethod,
               'status' => 'SUCCESS',
               'payload_log' => $paymentDetails['payload_log'] ?? null,
           ]);
       }
       ```
     - **Hasil**: Transaksi walk-in murni dijamin selalu memiliki record `payments` sah berstatus `SUCCESS`, kuota promo terpotong jika ada voucher (`$order->voucher_code`), dan tiket/akses langsung aktif tanpa ada data pembayaran yang tercecer.

### FR-05: Isolasi Keamanan Mock Driver & Midtrans Fail-Closed
- **Lokasi**: `MockSimulatorDriver.php` dan `MidtransService.php`.
- **Spesifikasi**:
  1. `MockSimulatorDriver` melempar HTTP 403 Forbidden atau `DomainException` jika dipanggil saat `app()->isProduction()`.
  2. `MidtransService::verifyWebhook()` menolak mentah-mentah (fail-closed) jika `server_key` kosong di environment production.

### FR-06: Manajemen Refund Terpadu
- Saat transaksi dibatalkan atau direfund, entitas baru dicatat di tabel `refunds` yang terikat ke `orders.id` dan `payments.id`.
- Status pesanan berubah menjadi `REFUNDED`, dan handler domain membebaskan ketersediaan slot lapangan kembali ke publik.

---

## 6. Kebutuhan Non-Fungsional (Non-Functional Requirements)

1. **Zero Regression Guarantee**:
   Seluruh 86 automated test suite eksisting wajib tetap 100% hijau (`php artisan test`).
2. **Idempotency Ketat**:
   Notifikasi webhook berulang dengan payload identik wajib ditangani secara idempoten melalui guard status `orders.payment_status === 'PAID'` dan transaksi database terkunci (`lockForUpdate`).
3. **Pemisahan Modul Mandiri (Open-Closed Architecture)**:
   Modul baru (Wellness, Salon, Gym) dapat menambahkan pemenuhan pesanan hanya dengan mendaftarkan kelas handler baru ke `PaymentFulfillmentRegistry` tanpa memodifikasi baris kode pada `PaymentOrchestratorService`.
4. **Data Integrity & Auditability**:
   Nilai uang, kode voucher, dan riwayat webhook dicatat secara permanen pada tabel database relasional untuk keperluan audit akuntansi dan rekonsiliasi sengketa.

---

## 7. Matriks Modifikasi Berkas & Rencana Implementasi

| No | Berkas Target | Aksi | Ringkasan Perubahan |
| :--- | :--- | :--- | :--- |
| 1 | `database/migrations/2026_09_16_000001_add_voucher_code_to_orders_table.php` | **[NEW]** | Menambahkan kolom `voucher_code` nullable pada tabel `orders`. |
| 2 | `app/Models/Pos/Order.php` | **[MODIFY]** | Mendaftarkan `voucher_code` ke dalam `$fillable` dan menambahkan alias relasi `orderItems()`. |
| 3 | `app/Services/Payment/Contracts/DomainFulfillmentHandlerInterface.php` | **[NEW]** | Mendefinisikan kontrak generic `fulfill(Order $order, Collection $items)`. |
| 4 | `app/Services/Payment/PaymentFulfillmentRegistry.php` | **[NEW]** | Registry pemetaan dinamis `item_type => DomainFulfillmentHandlerInterface`. |
| 5 | `app/Services/Padel/Handlers/PadelFulfillmentHandler.php` | **[NEW]** | Handler pemenuhan spesifik domain Padel (status booking & generate QR tiket). |
| 6 | `app/Services/Payment/PaymentOrchestratorService.php` | **[NEW]** | Service orchestrator utama penanganan status `PAID`, audit `payments`, voucher decrement, dan delegasi handler. |
| 7 | `app/Services/Padel/Concerns/ManagesCheckoutAndPayments.php` | **[MODIFY]** | Eager order creation, persistensi `orders.voucher_code`, placeholder `payments.transaction_id`, dan atribusi `item_type = 'PADEL'` untuk sewa alat. |
| 8 | `app/Http/Controllers/Api/V1/Payment/PaymentWebhookController.php` | **[MODIFY]** | Pencarian via `orders.order_number`, delegasi ke orchestrator, dan fallback legacy. |
| 9 | `app/Services/Payment/Drivers/MockSimulatorDriver.php` | **[AUDIT]** | Menjamin isolasi penuh dari environment production. |
| 10 | `app/Services/Payment/Drivers/MidtransService.php` | **[AUDIT]** | Menjamin verifikasi signature SHA512 fail-closed saat Server Key kosong. |
| 11 | `app/Providers/AppServiceProvider.php` | **[MODIFY]** | Mendaftarkan `PaymentFulfillmentRegistry` dan registrasi handler `PADEL`. |
| 12 | `tests/Feature/Payment/UnifiedPaymentOrchestratorTest.php` | **[NEW]** | Automated test suite untuk alur orchestrator baru, registry dinamis, persistensi voucher, dan keamanan gateway. |

---

## 8. Skenario Pengujian & Kriteria Kelulusan (Verification Plan)

### 8.1 Automated Test Cases

1. **Test Eager Order Creation & Atribusi Padel**:
   - Checkout 2 slot lapangan + 1 sewa raket.
   - Assert `orders` tercipta dengan nominal `grand_total` yang valid dan `voucher_code` tersimpan di database.
   - Assert seluruh baris `order_items` memiliki `item_type = 'PADEL'` (tidak ada yang bocor sebagai `'MERCH'`).
   - Assert baris `payments` status `PENDING` menggunakan `transaction_id = $order->order_number`.

2. **Test Dynamic Fulfillment via Registry**:
   - Simulasikan pelunasan order via orchestrator.
   - Assert `PaymentOrchestratorService` mendelegasikan pemenuhan ke `PadelFulfillmentHandler`.
   - Assert seluruh `padel_bookings` berubah status menjadi `PAID` dan memiliki hash tiket QR valid.

3. **Test Persistensi Voucher & Atomic Decrement**:
   - Buat pesanan online dan pesanan kasir POS menggunakan kode voucher promo valid.
   - Lakukan pelunasan pada kedua order.
   - Assert kolom `vouchers.quota` berkurang secara tepat dan `used_count` bertambah tanpa bergantung pada cache.

4. **Test Webhook Idempotency**:
   - Kirim notifikasi webhook Midtrans yang sama sebanyak 2 kali.
   - Assert kuota voucher hanya berkurang 1 kali dan tiket QR tidak digenerate ulang.

5. **Test POS Frontdesk Cash Settlement**:
   - Kasir melunasi pesanan belum bayar secara manual menggunakan metode `CASH`.
   - Assert order lunas seketika dan tercatat di audit `payments`.

6. **Test Keamanan Gateway & Isolasi Lingkungan**:
   - Eksekusi driver `mock` pada environment `production` -> Wajib ditolak (HTTP 403 / DomainException).
   - Verifikasi webhook Midtrans dengan Server Key kosong pada environment `production` -> Wajib fail-closed.
   - Verifikasi webhook dengan signature palsu -> Wajib ditolak HTTP 400 Bad Request.

7. **Test Supplemental Delta Payment Pasca-Reschedule (`test_supplemental_delta_payment_still_processed_when_order_already_marked_paid`)**:
   - Order awal berstatus `PAID`.
   - Terjadi reschedule kurang bayar (misal Reguler ke Prime) yang menghasilkan tagihan delta menggantung (`Payment` berstatus `PENDING`, booking `LOCKED`, QR code dinonaktifkan).
   - Pelunasan delta diproses melalui orchestrator (`markOrderAsPaid`).
   - Assert transaction-level idempotency guard tidak memblokir delta payment.
   - Assert record `Payment` delta berubah menjadi `SUCCESS`.
   - Assert booking terkait kembali berstatus `PAID` dan hash tiket QR dirilis aktif kembali.

8. **Test Non-Destructive Fulfillment Status (`test_fulfillment_handler_does_not_overwrite_checked_in_or_completed_status`)**:
   - Booking pada pesanan telah berstatus `CHECKED_IN` (pemain sudah di lapangan).
   - Dijalankan pemenuhan ulang melalui orchestrator.
   - Assert status booking yang sudah `CHECKED_IN` tidak pernah ditimpa balik menjadi `PAID`.

### 8.2 Kriteria Kelulusan Akhir
```bash
php artisan test
```
**Ekspektasi Kelulusan**: Seluruh automated test suite (minimum 86+ tests) wajib berstatus **100% HIJAU** tanpa satupun kegagalan atau regresi.
