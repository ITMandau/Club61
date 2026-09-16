# Product Requirements Document (PRD)
## Modul 10: Unified Payment Orchestrator (Polymorphic Payable Gateway)
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-10-UNIFIED-PAYMENT` |
| **Versi** | `v1.0.0-DRAFT` |
| **Status** | **Proposed — Menunggu Persetujuan Implementasi** |
| **Tech Stack** | Laravel 11, Eloquent Polymorphic Relations, Midtrans Snap, POS Manual Settlement |
| **Prinsip Utama** | **SATU PINTU PEMBAYARAN, ZERO REGRESSION KE MODUL EKSISTING, PAYABLE CONTRACT, IDEMPOTENT STATE TRANSITION** |
| **Modul Terdampak** | Modul 02 (Padel Booking), Modul 03/04/05 (Wellness, Salon, Gym), Modul 08 (POS Frontdesk) |

---

## 1. Latar Belakang & Masalah

Saat ini, alur pembayaran online (webhook Midtrans) diproses oleh `PaymentWebhookController` yang **hardcoded** mencari record hanya ke tabel `padel_bookings` (`App\Models\Padel\PadelBooking`). Ini bekerja baik selama satu-satunya layanan berbayar di sistem adalah booking Padel.

Masalah muncul karena roadmap produk (`docs/PROJECT_PROGRESS.md`) sudah menetapkan penambahan layanan berbayar baru yang independen:

| Modul | Layanan | Status Saat Ini |
| :--- | :--- | :---: |
| Modul 03 | Wellness & Sauna | Database Ready (25%) |
| Modul 04 | Salon & Beauty Appointments | Database Ready (25%) |
| Modul 05 | Gym Membership & QR Gate Pass | Database Ready (25%) |
| Modul 08 | POS Frontdesk, Split Bill & Multi-Gateway | Database & UI POS Ready (50%) |

Jika setiap modul baru menulis logic webhook-nya sendiri (atau, lebih buruk, ikut menumpuk kondisi `if` baru di `PaymentWebhookController` yang sama), timbul dua risiko:

1. **Blast radius tak terkendali**: satu file yang sama disentuh berulang kali oleh setiap modul baru — perubahan untuk Gym berisiko meregresi Padel yang sudah production-stable.
2. **POS Kasir tidak punya jalur yang jelas**: transaksi tunai/EDC di counter tidak lewat webhook gateway sama sekali (dibayar langsung di tempat), sehingga butuh mekanisme pelunasan yang **berbeda cara masuknya** tapi **sama cara efeknya** dengan pembayaran online.

## 2. Solusi Arsitektur: Payable Polymorphic Pattern

Prinsip dasar: **pisahkan "apa yang dibayar" dari "bagaimana cara membayarnya."**

Dibuat satu tabel netral, `payment_orders`, yang menjadi **satu-satunya sumber kebenaran** status transaksi finansial di seluruh sistem — tidak peduil apakah itu booking Padel, booking Gym, appointment Salon, atau transaksi kasir POS. Setiap booking/transaksi yang butuh dibayar akan "terdaftar" ke tabel ini melalui relasi polymorphic (`payable_type` + `payable_id`), bukan sebaliknya (webhook tidak lagi mencari langsung ke tabel domain).

```
                     ┌─────────────────────┐
   Checkout Online   │                     │   Settlement Manual
   (Midtrans Snap)   │   payment_orders    │   (Kasir POS: Cash/EDC/QRIS)
        webhook  ───▶│  (single source of  │◀───  konfirmasi langsung
                      │   truth status)     │
                      └──────────┬──────────┘
                                 │ payable_type / payable_id (morphTo)
                 ┌───────────────┼───────────────┬───────────────┐
                 ▼               ▼               ▼               ▼
          PadelBooking      GymBooking      SalonBooking      PosSale
        (tidak diubah      (modul baru)    (modul baru)    (modul baru)
         logic internalnya)
```

Setiap model domain (`PadelBooking`, `GymBooking`, dst.) mengimplementasikan sebuah **contract** `Payable` yang mewajibkan satu method: `onPaymentSuccess(PaymentOrder $order): void`. Isi method ini, untuk Padel, adalah **logic yang sudah ada sekarang** (ubah status jadi `PAID`, generate `qr_code_hash`, dsb) — dipindahkan ke satu method tanpa mengubah perilakunya sama sekali.

## 3. Skema Database Baru

### Tabel `payment_orders`
| Kolom | Tipe | Keterangan |
| :--- | :--- | :--- |
| `id` | ULID, PK | Primary key internal. |
| `order_id` | string(50), unique, index | Kode bisnis yang dikirim ke gateway pembayaran / ditampilkan ke kasir. |
| `payable_type` | string | Kelas model tujuan (`PadelBooking`, `GymBooking`, `PosSale`, dst). |
| `payable_id` | ULID, index | ID record tujuan pada tabel domain masing-masing. |
| `gross_amount` | decimal(12,2) | Nominal final yang harus dibayar (dihitung oleh service domain, tidak pernah dari input client). |
| `status` | string(20), default `PENDING` | `PENDING`, `PAID`, `FAILED`, `CANCELLED`, `REFUNDED`. |
| `payment_channel` | string(20), nullable | `MIDTRANS`, `CASH`, `EDC`, `QRIS_POS`. |
| `paid_at` | datetime, nullable | Timestamp konfirmasi lunas. |
| `raw_payload` | json, nullable | Payload mentah dari gateway/kasir untuk audit trail. |
| `created_at` / `updated_at` | timestamp | Standar Eloquent. |

Index gabungan `(payable_type, payable_id)` ditambahkan untuk pencarian balik dari sisi domain (misal: "tampilkan riwayat pembayaran booking ini").

## 4. Spesifikasi Fungsional (Functional Requirements)

### FR-01: Pendaftaran Order Saat Checkout (Additive, Tidak Mengubah Logic Domain)
Setiap service checkout domain (`ManagesCheckoutAndPayments` milik Padel, dan service sejenis di Gym/Salon/Wellness nanti) **menambahkan** satu baris kode setelah booking berhasil dibuat: membuat 1 baris `payment_orders` berstatus `PENDING` yang menunjuk ke booking tersebut. Logic penghitungan harga, validasi slot, dan pembuatan record booking **tidak diubah sama sekali**.

### FR-02: Generalisasi `PaymentWebhookController`
Controller webhook diubah agar mencari record lewat `PaymentOrder::where('order_id', $incomingOrderId)->first()`, bukan `PadelBooking::where(...)`. Setelah ditemukan dan signature terverifikasi (logic verifikasi signature **tidak diubah**), controller memanggil `$order->payable->onPaymentSuccess($order)` — Eloquent otomatis me-resolve objek domain yang benar berdasarkan `payable_type`.

### FR-03: Jalur Settlement Manual untuk POS Kasir
Kasir yang menerima pembayaran tunai/EDC/QRIS di counter tidak menunggu webhook. Controller POS memanggil service yang sama (`PaymentOrderService::markAsPaid($order, channel: 'CASH')`) secara sinkron saat kasir menekan tombol "Konfirmasi Lunas" — memicu `onPaymentSuccess()` yang identik dengan jalur webhook online. Ini memastikan **satu logic keberhasilan pembayaran** dipakai oleh dua pintu masuk yang berbeda.

### FR-04: Migrasi Data & Kompatibilitas Mundur (Zero-Downtime)
Karena `padel_bookings.order_id` sudah dipakai di production, dilakukan migrasi bertahap:
1. Tambahkan tabel `payment_orders` (migration baru, tidak mengubah tabel lama).
2. Backfill: setiap `padel_bookings` yang masih `PENDING`/`LOCKED` dengan `order_id` terisi dibuatkan baris `payment_orders` yang sepadan.
3. `PaymentWebhookController` diberi fallback sementara: jika `order_id` tidak ditemukan di `payment_orders`, baru cari ke `PadelBooking` langsung (jalur lama) — fallback ini dihapus setelah dipastikan tidak ada lagi transaksi lama yang aktif.
4. Kolom `padel_bookings.order_id` **tidak dihapus** — tetap dipakai sebagai referensi tampilan/tiket, sumber kebenaran status pindah ke `payment_orders`.

### FR-05: Refund & Void Generic
Endpoint refund (`/bookings/{id}/refund` milik Padel, dan turunannya di modul lain nanti) memanggil `PaymentOrderService::markAsRefunded($order)`, memastikan status refund tercatat konsisten di satu tempat untuk seluruh jenis layanan — memudahkan rekonsiliasi keuangan lintas modul di Filament Analytics.

## 5. Prinsip Non-Fungsional

1. **Zero Regression**: Tidak satupun logic internal domain Padel (hitung harga, atomic slot lock, state machine booking, generate tiket QR) diubah. Perubahan hanya pada *titik pemicu* setelah pembayaran sukses.
2. **Single Source of Truth**: Status "sudah dibayar atau belum" hanya boleh dibaca dari `payment_orders`, bukan disimpulkan ulang dari kolom status masing-masing tabel domain.
3. **Idempotent**: `onPaymentSuccess()` wajib aman dipanggil lebih dari sekali (misal webhook Midtrans retry) — dicegah dengan pengecekan `if ($order->status === 'PAID') return;` di awal method sebelum efek samping apapun dieksekusi.
4. **Auditability**: `raw_payload` menyimpan payload asli dari gateway/kasir untuk keperluan investigasi dispute pembayaran.

## 6. Dampak ke Modul Lain

| Modul | Perubahan yang Diperlukan |
| :--- | :--- |
| Modul 02 (Padel) | Tambah 1 baris pembuatan `PaymentOrder` di checkout; pindahkan logic "efek setelah bayar" ke method `onPaymentSuccess()` tanpa mengubah isinya. |
| Modul 03/04/05 (Wellness/Salon/Gym) | Saat modul ini diimplementasikan penuh, cukup mengimplementasikan contract `Payable` — tidak perlu menulis webhook sendiri. |
| Modul 08 (POS) | Transaksi kasir memakai `PaymentOrderService::markAsPaid()` untuk jalur settlement manual (cash/EDC/QRIS), disatukan dengan pencatatan yang sama dipakai booking online. |
| Modul 10 (dokumen ini) | Pemilik `payment_orders`, `PaymentOrderService`, dan contract `Payable`. |

## 7. Rencana Pengujian

1. **Regression penuh Modul 02**: seluruh test suite booking Padel (`tests/Feature/Api/*Padel*`) wajib tetap 100% hijau tanpa modifikasi assertion — ini adalah kriteria kelulusan utama migrasi.
2. **Test baru — `PaymentOrderTest`**: verifikasi `onPaymentSuccess()` idempotent (dipanggil 2x, efek hanya terjadi sekali).
3. **Test baru — `PaymentWebhookGenericTest`**: simulasikan webhook masuk untuk `payable_type` fiktif/mock guna memastikan resolusi polymorphic bekerja tanpa hardcode ke Padel.
4. **Test baru — `PosManualSettlementTest`**: verifikasi kasir bisa melunasi order tanpa webhook dan status tersinkron identik dengan jalur online.
5. **Test migrasi/backfill**: jalankan backfill di database salinan production (staging) dan pastikan jumlah `payment_orders` yang tercipta sama dengan jumlah booking `PENDING`/`LOCKED` aktif sebelum migrasi.

## 8. Risiko & Mitigasi

| Risiko | Mitigasi |
| :--- | :--- |
| Regresi ke booking Padel yang sudah stabil | Perubahan pada `PadelBooking` bersifat aditif (menambah, tidak mengganti); full regression suite wajib hijau sebelum merge. |
| Data transaksi lama (`order_id` tanpa baris `payment_order`) tidak ketemu saat webhook lama masih masuk | Fallback lookup langsung ke `PadelBooking` dipertahankan sementara selama periode transisi (lihat FR-04). |
| Webhook retry ganda menyebabkan efek samping dobel (misal tiket ter-generate 2x) | Guard idempotent di awal `onPaymentSuccess()` berdasarkan status `PAID` yang sudah tercatat. |
| Kasir POS dan webhook online balapan mengubah status order yang sama | Update status `payment_orders` dibungkus `DB::transaction` dengan row-level lock (`lockForUpdate`) sebelum transisi status. |
