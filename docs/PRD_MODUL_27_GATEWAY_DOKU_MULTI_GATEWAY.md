# Product Requirements Document (PRD)
## Modul 27: Payment Gateway DOKU & Pilihan Gateway (Midtrans / DOKU)
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-27-GATEWAY-DOKU-MULTI-GATEWAY` |
| **Versi** | `v0.3.0` |
| **Status** | **Tahap 1 & 2 selesai di kode (15 Okt 2026)** — lihat §15b & §15c. Uji bayar sungguhan di sandbox DOKU (asumsi §15) belum dilakukan; DOKU baru boleh dipilih di production setelah itu. Keputusan yang sudah disepakati ada di §3. |
| **Sumber Requirement** | Aktivasi Midtrans belum selesai. PM memutuskan menambah DOKU sebagai gateway kedua. Belum pasti permanen atau sementara, jadi gateway dibuat **bisa dipilih** dan dua-duanya bisa aktif. |
| **Dependensi Teknis** | `PRD_MODUL_10_UNIFIED_PAYMENT_GATEWAY.md` (orchestrator & Midtrans), `PRD_MODUL_02_PADEL_BOOKING.md`, `PRD_MODUL_05_MEMBERSHIP_SYSTEM.md`, `PRD_MODUL_11_WALK_IN_OFFLINE_BOOKING.md` (kasir), `PRD_MODUL_17_BUKU_TRANSAKSI_TERPADU.md`, `PRD_MODUL_21_REFUND_NO_SHOW_PEMBAYARAN_BERMASALAH.md`. |
| **Prinsip Utama** | **KODE DOKU TERPISAH DARI MIDTRANS**; **SATU INTI "TANDAI LUNAS" UNTUK SEMUA GATEWAY**; **TAGIHAN SELALU DIURUS GATEWAY YANG MEMBUATNYA**; **SATU TAGIHAN = SATU SESI BAYAR HIDUP**; **CEGAH DOBEL BAYAR, REFUND HANYA PAYUNG**. |

---

## 1. Ringkasan

Club 61 menambah **DOKU** sebagai gateway pembayaran kedua di samping Midtrans:

1. Kode DOKU (bikin tagihan, webhook, cek status, QR kasir) ditulis di **file sendiri**. File Midtrans **tidak diubah**. Kalau DOKU bermasalah, DOKU bisa dimatikan tanpa mengganggu Midtrans, dan error-nya mudah dilacak di log DOKU sendiri.
2. Inti bisnis yang sudah ada (**tandai lunas**: anti-dobel, aktifkan tiket, Buku Transaksi, pembayaran dobel → refund) **dipakai bersama**, tidak diduplikat.
3. Admin memilih gateway untuk **pembayaran online customer** dan untuk **QR otomatis kasir** secara terpisah. Tagihan yang sudah dibuat tetap diurus gateway yang membuatnya.
4. Online hanya **QRIS + Virtual Account**. Keduanya bisa dibatalkan lewat API gateway, jadi ganti metode tidak meninggalkan sesi bayar kedua.
5. Metode pembayaran bisa **ditambah dari admin** tanpa ubah kode (opsi B).

---

## 2. Kondisi Sekarang (per 15 Okt 2026)

| Bagian | Kondisi |
| :--- | :--- |
| Gateway | Hanya Midtrans (Snap). Driver dipilih global lewat `config('services.payment.driver')`. Registry `PaymentManager` sudah ada, isinya `midtrans` dan `mock`. |
| Webhook | `POST /api/v1/padel/webhook/midtrans`, generik `/webhook/{driver}`, dan `/api/v1/payments/webhook` (POS, terkunci ke Midtrans). |
| Tandai lunas | `PaymentOrchestratorService::markOrderAsPaid`: idempoten (5 lapis), sudah netral dari gateway kecuali default `'MIDTRANS'` dan penutupan sesi Snap. |
| Anti-dobel | `SnapSessionGuard` menjamin 1 sesi Snap hidup per tagihan; sesi lama ditutup lewat Status API sebelum sesi baru dibuat. Sapu sesi kembar 48 jam. |
| Cek status | `MidtransReconciliationService` dipanggil langsung oleh pembersih slot, kasir, Kelola Pemesanan, invoice customer, membership, dan POS. |
| Kasir QR otomatis | `PosMidtransQrisService` + `midtrans-qris-modal` (Snap embed). |
| Metode online | Daftar metode ditulis di kode (`OnlinePaymentCatalog`: QRIS, 6 VA, kartu). Admin hanya mengatur aktif/nonaktif, label, deskripsi, badge, urutan, min/max nominal, tampil di kasir. |
| Kartu kredit | Ada di katalog tapi **belum diaktivasi** di Midtrans. |

---

## 3. Keputusan yang Sudah Disepakati (15 Okt 2026)

| # | Keputusan |
| :--- | :--- |
| 1 | DOKU **ditambah**, Midtrans **tetap diproses aktivasinya**. Dua-duanya bisa aktif, dipilih di admin. |
| 2 | Kode DOKU **dipisah**: client, webhook, cek status, rekonsiliasi, QR kasir, log. Midtrans tidak di-refactor. |
| 3 | Online hanya **QRIS + VA** untuk Midtrans maupun DOKU. Kartu kredit & e-wallet langsung **tidak dipakai** (e-wallet tetap bisa bayar lewat QRIS). |
| 4 | Pilihan gateway **global per kanal**: (a) pembayaran online customer, (b) QR otomatis kasir. Per-metode bisa ditambah nanti. |
| 5 | Kasir tetap punya mode **Manual** (QRIS statis / EDC) dan **Otomatis** (QR dari Midtrans atau DOKU sesuai pilihan admin). |
| 6 | Metode pembayaran bisa **ditambah dari admin** (opsi B), kode kanal dipilih dari daftar resmi tiap gateway. |
| 7 | Biaya MDR / biaya admin per metode **ditunda** sampai gateway aktif. |
| 8 | Target: **tidak ada dobel bayar**. Refund dobel tetap ada sebagai payung. |

---

## 4. Fakta DOKU yang Dipakai (dari dokumentasi resmi)

| Topik | Isi | Sumber |
| :--- | :--- | :--- |
| Produk | **DOKU Checkout** (halaman bayar DOKU, popup JS atau redirect). Satu integrasi untuk banyak kanal. Satu metode di `payment_method_types` → customer langsung ke metode itu. | [doku-checkout](https://developers.doku.com/accept-payments/doku-checkout.md), [supported methods](https://developers.doku.com/accept-payments/doku-checkout/configuration/supported-payment-methods.md) |
| Base URL | Sandbox `https://api-sandbox.doku.com`, production `https://api.doku.com`. JS Checkout sandbox `https://sandbox.doku.com/jokul-checkout-js/v1/jokul-checkout-1.0.0.js`, production `https://jokul.doku.com/...`. | [frontend integration](https://developers.doku.com/accept-payments/doku-checkout/integration-guide/frontend-integration.md) |
| Sandbox | Akun **terpisah**: daftar di `sandbox.doku.com/bo/sandbox-registration`. Kunci di Settings → Account → API Keys (Secret Key lewat OTP). | [explore sandbox](https://jokul.doku.com/docs/docs/getting-started/explore-sandbox/), [set up integration](https://docs.doku.com/get-started/manage-business/set-up-integration) |
| Tanda tangan request | Header `Client-Id`, `Request-Id`, `Request-Timestamp` (UTC ISO 8601), `Signature`. `Digest = base64(sha256(body mentah))`. String: `Client-Id:…\nRequest-Id:…\nRequest-Timestamp:…\nRequest-Target:{path}\nDigest:…` → `Signature = "HMACSHA256=" + base64(hmac_sha256(string, SecretKey))`. GET tanpa Digest. | [signature non-SNAP](https://developers.doku.com/get-started-with-doku-api/signature-component/non-snap/signature-component-from-request-header.md) |
| Bikin tagihan | `POST /checkout/v1/payment`: `order.amount` (integer IDR), `order.invoice_number` (maks 64 karakter), `line_items` (jumlah = amount), `payment.payment_due_date` (menit, default 60), `payment.payment_method_types`. Respons: `payment.url`, `token_id`, `expired_date` (yyyyMMddHHmmss, **WIB**). | [backend integration](https://developers.doku.com/accept-payments/doku-checkout/integration-guide/backend-integration.md) |
| Webhook | URL diatur di dashboard **per kanal**, harus publik (ngrok tidak didukung). Override per request harus path yang sama. Header sama dengan request (Client-Id, Request-Id, Request-Timestamp, Signature). Diverifikasi dengan rumus yang sama, `Request-Target` = **path URL kita**, Digest dari **body mentah**. Balas **2xx segera**. | [setup URL](https://developers.doku.com/get-started-with-doku-api/notification/setup-notification-url.md), [best practice](https://developers.doku.com/get-started-with-doku-api/notification/best-practice.md) |
| Webhook ulang | Hanya **3×**: +30 menit, +6 jam, +12 jam. Notifikasi **bisa dobel**. Tidak ada daftar IP resmi. VA hanya kirim notifikasi saat SUCCESS. Notifikasi kedaluwarsa harus dinyalakan di dashboard. FAILED pada Checkout boleh diabaikan. | [retry](https://developers.doku.com/get-started-with-doku-api/notification/retry-notification.md), [sample](https://developers.doku.com/get-started-with-doku-api/notification/http-notification-sample-non-snap.md) |
| Cek status | `GET /orders/v1/status/{invoice_number}` (bertanda tangan, tanpa Digest). Status: PENDING, SUCCESS, FAILED, EXPIRED, REFUNDED, TIMEOUT, REDIRECT. | [check status](https://developers.doku.com/get-started-with-doku-api/check-status-api/non-snap.md) |
| Batal | `POST /checkout/v3/cancellations` (`order.invoice_number`, `payment.original_request_id`). Hanya tagihan **belum bayar & belum kedaluwarsa**, hanya **QRIS & VA**, harus **dinyalakan** di dashboard (Order Settings). | [cancel order](https://developers.doku.com/accept-payments/doku-checkout/order-and-notification-handling/cancel-order-api.md) |
| Idempotensi | `Request-Id` = kunci idempotensi: request ulang yang sama → respons lama (HTTP 409). Perilaku `invoice_number` dipakai ulang **tidak didokumentasikan**. | [idempotency](https://developers.doku.com/get-started-with-doku-api/idempotency-request.md) |
| Refund | Non-kartu: manual lewat tiket / care@doku.com. | [refund](https://docs.doku.com/get-started/manage-business/manage-finances/refund) |
| SDK PHP | `doku/doku-php-library` hanya SNAP (belum Checkout). **Tidak dipakai**: integrasi pakai HTTP Laravel biasa. | [libraries](https://developers.doku.com/developer-kit/libraries-and-sdk.md) |
| Aktivasi | QRIS: akun Corporate + KTP/NPWP, ±1–2 hari kerja setelah dokumen terverifikasi. Settlement QRIS T+1. | [QRIS](https://docs.doku.com/accept-payments/no-integration-products/qris) |

---

## 5. Arsitektur

### 5.1 File baru (DOKU, terpisah)

| File | Tugas |
| :--- | :--- |
| `app/Services/Payment/Doku/DokuClient.php` | Kirim request ke DOKU + tanda tangan, timeout pendek, log ke kanal `doku`. |
| `app/Services/Payment/Doku/DokuSignature.php` | Bikin & verifikasi tanda tangan (request keluar, respons, webhook masuk). `hash_equals`. |
| `app/Services/Payment/Doku/DokuCheckoutService.php` | Bikin tagihan Checkout (1 metode per sesi), batalkan tagihan, terjemahkan status → `PAID / PENDING / NOT_PAID / ERROR`. |
| `app/Services/Payment/Doku/DokuSessionGuard.php` | Aturan "1 tagihan = 1 sesi hidup" versi DOKU (lihat §8). |
| `app/Services/Payment/Doku/DokuReconciliationService.php` | Cek status ke DOKU, lunasi lewat inti bersama, tutup sesi sisa. |
| `app/Services/Payment/Doku/DokuPosQrisService.php` | QR otomatis di kasir lewat DOKU (§9). |
| `app/Http/Controllers/Api/V1/Payment/DokuNotificationController.php` | Webhook DOKU: verifikasi, catat, balas 2xx, proses. |
| `app/Models/Payment/DokuNotification.php` + migrasi `doku_notifications` | Jejak setiap notifikasi masuk (§11). |
| `app/Console/Commands/ReconcileDokuPayments.php` | `payment:reconcile-doku`, tiap 5 menit. |
| `app/Services/Payment/OnlineGatewayRouter.php` | **Pengarah** (§5.3). |
| `resources/views/customer/partials/doku-checkout.blade.php` | Muat JS Checkout DOKU, buka popup / redirect. |
| `resources/views/pos/partials/doku-qris-modal.blade.php` | Popup QR DOKU di kasir. |
| `config/services.php` blok `doku`, `config/logging.php` kanal `doku` (`storage/logs/doku.log`). |
| Route: `POST /api/v1/payments/doku/notification`. |

### 5.2 Yang dipakai bersama (tidak diduplikat)

- `PaymentOrchestratorService::markOrderAsPaid`: tandai lunas, kunci baris, anti-dobel, fulfillment, Buku Transaksi, pembayaran dobel → `DUPLICATE` + refund PENDING. DOKU memanggil dengan `payment_gateway = 'DOKU'`.
- Tabel `orders` & `payments` (tanpa kolom baru), `PaymentFulfillmentRegistry`, voucher, ledger.

### 5.3 Pengarah (`OnlineGatewayRouter`)

Pengarah membaca `payments.payment_gateway` tagihan, lalu meneruskan ke service gateway itu. Hasilnya diseragamkan.

| Method pengarah | MIDTRANS (tidak diubah) | DOKU (baru) |
| :--- | :--- | :--- |
| `reconcileOrder(Order)` | `MidtransReconciliationService::reconcileOrder` | `DokuReconciliationService::reconcileOrder` |
| `hasOnlinePaymentAttempt(Order)` | `MidtransReconciliationService` | `DokuReconciliationService` |
| `prepareSession(bill, method, amount)` | `SnapSessionGuard::prepare` | `DokuSessionGuard::prepare` |
| `cancelOpenSessions(Order / Payment)` | `MidtransService::cancelTransaction` | `DokuCheckoutService::cancel` |
| `createPayment(...)` untuk tagihan baru | ikut **gateway online yang dipilih admin** | |

### 5.4 Titik yang disentuh (cuma baris pemanggilan → pengarah)

| Titik | File |
| :--- | :--- |
| Pembersih slot hangus (cek sudah dibayar sebelum slot dilepas, batalkan sesi setelah batal) | `ManagesScheduleAndSlots.php` (±453–487, 421, 653) |
| Kasir sebelum terima pelunasan | `BookOfflineCourt.php` (±205–215), `ManagesRescheduleAndCashier.php` (±703–722, 1002) |
| Tutup sesi online setelah kasir terima pelunasan | `PaymentOrchestratorService.php` (±239) |
| Tombol cek pembayaran di Kelola Pemesanan (+ teks nama gateway dinamis) | `KelolaPemesanan.php` (±376–393) |
| Invoice customer menunggu lunas | `PadelBookingController.php` (±269), `MembershipController.php` (±355), `MembershipOnlinePaymentService.php` (±56, 163, 197) |
| Bikin / pakai ulang / tutup sesi saat checkout & ganti metode | `ManagesCheckoutAndPayments.php` (±375, 531, 577, 699, 801, 838, 869), `MembershipOnlinePaymentService.php` (±77–108) |
| Tampilan checkout / invoice / membership (pilih Snap atau DOKU dari respons `driver`) | `checkout.blade.php`, `invoice-scripts.blade.php`, `membership.blade.php` |
| Label struk & Buku Transaksi (baca payload DOKU) | `formatPaymentMethodLabel`, `LedgerWriter::paymentReference`, `LedgerTransactionPresenter` |

**Tidak disentuh:** `MidtransService`, `MidtransReconciliationService`, `SnapSessionGuard`, `PaymentWebhookController`, `PosMidtransQrisService`, `payment:reconcile-midtrans`.

---

## 6. Pengaturan Admin

### 6.1 Pilihan gateway (halaman Metode Pembayaran Online)

| Setelan | Pilihan | Catatan |
| :--- | :--- | :--- |
| Gateway pembayaran online customer | Midtrans / DOKU | Berlaku untuk tagihan **baru**. |
| Gateway QR otomatis kasir | Midtrans / DOKU | Mode Manual tetap ada di layar kasir. |

- Gateway yang belum dikonfigurasi (kunci kosong) atau dimatikan tidak bisa dipilih.
- Perubahan tercatat di Log Aktivitas (kritis).

### 6.2 Saklar `DOKU_ENABLED`

- `false` → DOKU tidak dipakai untuk tagihan **baru** dan hilang dari pilihan admin. Kalau DOKU sedang dipilih, tagihan baru otomatis pakai Midtrans.
- Webhook & rekonsiliasi DOKU **tetap jalan** untuk tagihan DOKU yang sudah ada sampai lunas / kedaluwarsa.

### 6.3 Metode bisa ditambah dari admin (opsi B)

- Tombol **"+ Tambah Metode"**: nama, deskripsi, badge, jenis (QRIS / VA), kode kanal Midtrans dan kode kanal DOKU dipilih dari **dropdown daftar resmi** tiap gateway, min/max nominal, tampil di kasir.
- Daftar kode resmi tetap disimpan di kode (jarang berubah). Admin tidak bisa mengetik kode bebas.
- Metode tanpa kode untuk gateway yang aktif otomatis disembunyikan dari customer.
- Jenis selain QRIS / VA (kartu, e-wallet langsung) **tidak bisa dipilih** sampai pengaman kunci ganti metode dibuat (§8 no. 8).
- Pemetaan awal 7 metode yang sudah ada:

| Metode | Midtrans | DOKU |
| :--- | :--- | :--- |
| QRIS | `other_qris`, `gopay`, `shopeepay` | `QRIS` |
| BCA VA | `bca_va` | `VIRTUAL_ACCOUNT_BCA` |
| Mandiri VA | `echannel` | `VIRTUAL_ACCOUNT_BANK_MANDIRI` |
| BNI VA | `bni_va` | `VIRTUAL_ACCOUNT_BNI` |
| BRI VA | `bri_va` | `VIRTUAL_ACCOUNT_BRI` |
| CIMB VA | `cimb_va` | `VIRTUAL_ACCOUNT_BANK_CIMB` |
| VA Bank Lain (Permata, BSI) | `permata_va`, `other_va` | `VIRTUAL_ACCOUNT_BANK_PERMATA`, `VIRTUAL_ACCOUNT_BANK_SYARIAH_MANDIRI` (BSI, dari dokumentasi resmi) |
| Kartu kredit | `credit_card` | — (tidak dipakai) |

---

## 7. Alur Pembayaran Online lewat DOKU

1. Customer memilih metode di halaman checkout Club 61 (sama seperti sekarang).
2. Server: `OnlineGatewayRouter` → `DokuSessionGuard::prepare` (§8) → `DokuCheckoutService` membuat tagihan:
   - `invoice_number` = id sesi unik (maks 30 karakter, tidak pernah dipakai ulang),
   - `payment_method_types` = kode DOKU metode itu saja,
   - `payment_due_date` = **sisa waktu bayar tagihan** (sama dengan batas pelepasan slot), bukan default 60 menit,
   - `line_items` dijumlah = `amount` (dicek sebelum dikirim).
3. Sesi dicatat di `payload_log.doku_sessions[invoice] = {method, amount, payment_url, token_id, request_id, expires_at, status}`; `payments.payment_gateway = 'DOKU'`.
4. Respons ke halaman: `driver = doku`, `payment_url` → popup JS DOKU (redirect sebagai cadangan).
5. Customer bayar. DOKU mengirim webhook → `DokuNotificationController`:
   1. Verifikasi tanda tangan (path kita, body mentah). Gagal → 401, dicatat.
   2. Simpan ke `doku_notifications` (dedupe `Request-Id`). Sudah pernah diproses → 200.
   3. Balas 200 **segera**; proses lunas setelah respons terkirim (sama pola dengan sinyal realtime).
   4. SUCCESS → **dikonfirmasi ulang ke API status DOKU**, cocokkan `invoice_number` & `amount` dengan tagihan → `markOrderAsPaid`.
   5. EXPIRED → tandai sesi mati. FAILED → abaikan (masih bisa dibayar sampai kedaluwarsa).
6. Redirect balik ke web **bukan** bukti bayar. Halaman invoice menunggu status dari server (polling yang sudah ada, lewat pengarah).
7. Webhook hilang → `payment:reconcile-doku` tiap 5 menit menanyakan status tagihan DOKU yang masih menunggu.

---

## 8. Aturan Anti-Dobel Bayar (DOKU)

| # | Aturan |
| :--- | :--- |
| 1 | **Satu tagihan = satu sesi DOKU hidup.** Klik bayar lagi dengan metode & nominal sama → sesi lama dipakai ulang. |
| 2 | **Satu sesi = satu metode** (`payment_method_types` satu kode). Customer tidak bisa pindah metode di dalam halaman DOKU. |
| 3 | **Ganti metode:** sesi lama **dibatalkan** di DOKU, lalu **dibuktikan lewat API status** (EXPIRED / batal). Baru sesi baru dibuat. |
| 4 | Sesi lama ternyata **sudah dibayar** → tidak ada sesi baru, tagihan dilunasi. |
| 5 | DOKU tidak bisa dihubungi / sesi belum pasti mati → **ditolak** ("coba lagi sebentar, jangan bayar memakai VA/QR lama"). |
| 6 | Batas ganti metode per tagihan sama dengan Midtrans (maks 6 sesi). |
| 7 | **Batas bayar DOKU = batas pelepasan slot.** Sama dengan Midtrans: bayar / bayar ulang **ditolak kalau sisa waktu < 2 menit** (`MIN_RETRY_MINUTES`) dengan pesan "buat booking baru"; `payment_due_date` = sisa waktu, jadi DOKU sendiri menolak pembayaran lewat batas; slot baru dilepas setelah DOKU memastikan belum dibayar (pembersih slot lewat pengarah, jeda `GATEWAY_UNCERTAIN_MAX_MINUTES`). |
| 8 | Hanya QRIS & VA. Kalau kelak kartu / e-wallet langsung dibuka: sesi jenis itu **mengunci tombol ganti metode** sampai kedaluwarsa (batas waktu pendek), karena tidak bisa dibatalkan. |
| 9 | **Booking online hanya dilunasi online.** Customer yang mau bayar di kasir = booking walk-in baru. Kasir hanya melunasi **selisih reschedule** (`LOCKED` + `reschedule_count > 0`), dan tetap menolak kalau sesi online selisih itu masih hidup (pengaman yang sudah ada, lewat pengarah). Sudah diterapkan 15 Okt 2026 (§15a). |
| 10 | Payung: kalau tetap terjadi dobel (kejadian di luar kendali, mis. bank), jalur `DUPLICATE` + refund PENDING yang sudah ada + `[ALERT]`. |

---

## 9. Kasir

### 9.1 QR otomatis lewat DOKU

- Mode **Otomatis** di kasir memakai gateway yang dipilih admin (§6.1). Alur sama dengan Midtrans sekarang: QR tampil di popup, status dipantau, lunas → struk, batal / kedaluwarsa → slot dilepas.
- Cara menampilkan QR **ditentukan setelah uji sandbox** (§13):
  - **A.** Halaman Checkout DOKU khusus QRIS di popup kasir (iframe). Paling cepat, kalau DOKU mengizinkan halaman Checkout ditampilkan dalam iframe.
  - **B.** QRIS langsung (SNAP): kita menggambar QR sendiri. Paling mirip popup Midtrans, tapi butuh kunci RSA + token B2B.
- Sapu QR kasir yang ditinggal: perluas jadwal `pos:sweep-auto-payments` dengan versi DOKU (file DOKU sendiri).

---

## 10. Keamanan

| Risiko | Penanganan |
| :--- | :--- |
| Webhook palsu | Verifikasi HMAC (`hash_equals`), body mentah, path kita. Tanpa kunci → tolak (fail-closed), sama dengan Midtrans. |
| Webhook diputar ulang / dobel | Dedupe `Request-Id` (`doku_notifications`), konfirmasi ulang SUCCESS ke API status, idempotensi `markOrderAsPaid`. |
| Nominal / invoice dimanipulasi | Cocokkan `order.amount` & `invoice_number` dengan tagihan sebelum lunas; selisih → `[ALERT]`, tidak dilunasi otomatis. |
| Redirect palsu | Redirect tidak pernah dipakai sebagai bukti bayar. |
| Kunci bocor | Kunci hanya di `.env`. Tidak pernah ditulis ke log / `payload_log` / `doku_notifications`; header `Signature` tidak disimpan. |
| Salah lingkungan | `DOKU_IS_PRODUCTION` menentukan base URL. Override URL notifikasi tidak berlaku di production (sama dengan Midtrans). |
| Mode mock | Tidak ada mock DOKU: DOKU hanya bisa dipilih kalau kunci terisi; tanpa kunci → Midtrans / checkout ditolak (§15c). |
| Respons DOKU | Tanda tangan respons tidak diverifikasi (rumus tidak terdokumentasi); dipercaya lewat TLS ke domain DOKU + pencocokan invoice & nominal (§15c). |

### 10.1 Masalah umum payment gateway & penanganannya

| # | Masalah | Penanganan di modul ini |
| :--- | :--- | :--- |
| 1 | **Webhook dobel** | Dedupe `Request-Id` + `markOrderAsPaid` idempoten (kunci baris, sesi yang sudah SUCCESS dilewati). |
| 2 | **Webhook datang tidak berurutan** (mis. EXPIRED / PENDING sampai setelah SUCCESS) | Status **tidak pernah mundur**: tagihan SUCCESS tidak bisa jadi PENDING / EXPIRED / FAILED lagi; notifikasi yang lebih lama dicatat sebagai `ignored`. |
| 3 | **Webhook datang sebelum sesi tercatat** (customer bayar sangat cepat) | **Catat dulu, baru panggil DOKU**: sesi disimpan (`CREATING`) dan di-commit sebelum request ke DOKU, lalu diisi URL-nya. Webhook selalu menemukan tagihannya. DOKU gagal → sesi ditandai `FAILED`. |
| 4 | **Request bikin tagihan timeout** (DOKU mungkin sudah membuat tagihan, respons hilang) | Ulangi dengan **`Request-Id` yang sama** (DOKU membalas respons asli, HTTP 409). Masih gagal → sesi tidak dipakai dan dibatalkan oleh rekonsiliasi; customer tidak pernah menerima URL-nya. |
| 5 | **Dibayar dua kali di VA / QR yang sama** | Hanya VA **nominal tetap, sekali bayar** dan **QRIS dinamis** (sekali pakai). Dibuktikan di Tahap 0. |
| 6 | **Nominal kurang / lebih** | Nominal integer IDR, `line_items` = `amount`, dibulatkan sama persis dengan total tagihan. Nominal webhook beda → `[ALERT]`, tidak dilunasi otomatis. |
| 7 | **Dibayar mepet / setelah batas waktu** | Dicegah, bukan di-refund (sama dengan Midtrans): sisa waktu < 2 menit → bayar ditolak, customer diminta booking ulang; sesi DOKU kedaluwarsa tepat di batas waktu sehingga DOKU sendiri menolak pembayaran terlambat; slot tidak dilepas selama DOKU masih "pending". Kalau gateway tetap mengirim lunas untuk order yang sudah batal (seharusnya tidak terjadi) → dicatat + `[ALERT]` ke admin, booking tidak diaktifkan diam-diam. |
| 8 | **Refund / chargeback dari gateway** (status REFUNDED) | Dicatat + `[ALERT]` ke admin; booking tidak diubah otomatis. |
| 9 | **Tanda tangan gagal karena teknis** (body dirapikan middleware, path beda karena proxy) | Pakai `$request->getContent()` (body mentah) dan path dari route kita (`/api/v1/payments/doku/notification`), bukan URL yang dilihat proxy. Diuji di Tahap 0 lewat Nginx sandbox. |
| 10 | **Jam server melenceng** | Server sinkron NTP; timestamp dibuat di satu fungsi (UTC untuk request, WIB untuk `expired_date`). |
| 11 | **URL webhook salah / belum didaftarkan per kanal** | Halaman admin menampilkan **"notifikasi DOKU terakhir diterima"**; kalau tagihan lunas lewat rekonsiliasi tapi webhook-nya tidak pernah datang → `[ALERT]` "cek URL notifikasi di dashboard DOKU". |
| 12 | **Rekonsiliasi membanjiri API DOKU** | Hanya sesi yang masih menunggu (maks 48 jam), dibatasi jumlah per putaran, jeda antar request. |
| 13 | **Klik "Bayar" berkali-kali** | Sesi dipakai ulang (§8 no. 1) di bawah kunci baris tagihan; tombol dikunci saat request berjalan. |
| 14 | **Gateway diganti di tengah jalan** | Pengarah mengikuti gateway tagihan (§5.3). |
| 15 | **Uang di laporan ≠ uang yang benar-benar masuk** | Di luar modul ini: dicocokkan saat Tutup Hari (Modul 25) dengan laporan settlement DOKU. |

---

## 11. Data & Migrasi

| Perubahan | Isi |
| :--- | :--- |
| `payments` | Tidak ada kolom baru. `payment_gateway = 'DOKU'`; sesi di `payload_log.doku_sessions` + `doku_invoice_ids`. |
| Tabel baru `doku_notifications` | `id`, `request_id` (unique), `invoice_number`, `status`, `amount`, `verified` (bool), `result` (processed / ignored / rejected + alasan), `payload` (json, tanpa header Signature), `received_at`. Dipangkas otomatis setelah 90 hari (ikut `audit:prune` atau command sendiri). |
| `online_payment_methods` | Tambah kolom `kind` (QRIS / VA), `midtrans_channels` (json), `doku_channels` (json), `is_custom`. Isi awal dari katalog sekarang (migrasi data). |
| `settings` / tabel pengaturan | `online_gateway` (MIDTRANS / DOKU), `pos_qris_gateway` (MIDTRANS / DOKU). |

---

## 12. Konfigurasi (`.env`, nama saja, nilai tidak pernah di-commit)

```
DOKU_ENABLED=false
DOKU_CLIENT_ID=
DOKU_SECRET_KEY=
DOKU_IS_PRODUCTION=false
DOKU_NOTIFICATION_URL=      # opsional, path harus sama dengan URL di dashboard
```

URL notifikasi yang didaftarkan di dashboard DOKU (per kanal):
- Sandbox: `https://sandbox.club61.id/api/v1/payments/doku/notification`
- Production: `https://club61.id/api/v1/payments/doku/notification`

---

## 13. Tahapan

| Tahap | Isi | Syarat selesai |
| :--- | :--- | :--- |
| **0. Uji sandbox** (sebelum coding Tahap 2) | Buktikan di sandbox DOKU semua asumsi §15: pembatalan sebelum halaman dibuka, `invoice_number` dipakai ulang, iframe, isi & header notifikasi QRIS / VA, kode VA BSI, format `expired_date`, berhenti mengirim ulang setelah 200, VA sekali bayar, `Request-Id` 409, tanda tangan lewat Nginx. | Catatan hasil di §15. |
| **1. Pengarah + metode dari admin** | `OnlineGatewayRouter` (awalnya hanya Midtrans), titik §5.4 dialihkan ke pengarah, kolom metode (§11), tombol Tambah Metode, setelan gateway (hanya Midtrans yang bisa dipilih). | Semua test lama hijau. Perilaku Midtrans **tidak berubah**. |
| **2. DOKU online** | Client, tanda tangan, Checkout, `DokuSessionGuard`, webhook, `doku_notifications`, rekonsiliasi, tampilan popup DOKU, label struk / ledger, saklar `DOKU_ENABLED`. | Test baru (§14) hijau + uji bayar sungguhan di sandbox (QRIS & tiap VA). |
| **3. Kasir** | QR otomatis DOKU (§9.1), sapu QR DOKU. | Test + uji di tablet kasir. |
| **4. Go-live** | Kunci production, URL notifikasi production per kanal, aktifkan fitur Cancel Order di dashboard, pilih gateway di admin. | Transaksi nyata kecil berhasil + rekonsiliasi jalan. |

---

## 14. Rencana Test

- Tanda tangan: contoh resmi DOKU (request, GET tanpa Digest, webhook) cocok; satu karakter body diubah → ditolak.
- Webhook: valid → lunas sekali; dobel `Request-Id` → diproses sekali; tanda tangan salah → 401 & tidak lunas; SUCCESS tapi API status bilang PENDING → tidak lunas; nominal beda → `[ALERT]`, tidak lunas; EXPIRED → sesi mati; FAILED → diabaikan.
- Anti-dobel: pakai ulang sesi; ganti metode menutup sesi lama dulu; sesi lama ternyata lunas → tidak ada sesi baru; DOKU mati → ditolak; maks 6 sesi.
- Pengarah: tagihan MIDTRANS tetap ke Midtrans walau admin memilih DOKU (dan sebaliknya); `DOKU_ENABLED=false` → tagihan baru Midtrans, tagihan DOKU lama tetap direkonsiliasi.
- Pembersih slot: tagihan DOKU lunas tapi webhook telat → slot tidak dilepas.
- Kasir: tolak pelunasan saat sesi DOKU hidup.
- Batas waktu: bayar ulang DOKU dengan sisa < 2 menit ditolak ("buat booking baru"); `payment_due_date` = sisa waktu.
- Metode admin: tambah metode, kode hanya dari daftar resmi, metode tanpa kode DOKU tersembunyi saat DOKU dipilih, kartu tidak bisa dipilih.
- Masalah umum (§10.1): EXPIRED setelah SUCCESS tidak menurunkan status; webhook sebelum URL sesi tersimpan tetap menemukan tagihan; timeout bikin tagihan → request ulang `Request-Id` sama, tagihan yatim dibatalkan; bayar setelah order batal → refund PENDING + `[ALERT]`; REFUNDED → `[ALERT]`; lunas lewat rekonsiliasi tanpa webhook → `[ALERT]` URL notifikasi.
- Semua HTTP DOKU di-fake (`Http::fake`); tidak ada kunci asli di test.

---

## 15. Asumsi yang Wajib Dibuktikan di Sandbox (Tahap 0)

| # | Asumsi | Kalau ternyata salah |
| :--- | :--- | :--- |
| 1 | Tagihan QRIS / VA bisa dibatalkan walau halaman belum dibuka customer | Aturan §8 no. 3 diganti: sesi lama dibiarkan kedaluwarsa (batas waktu pendek) dan ganti metode dikunci sampai itu. |
| 2 | `invoice_number` yang sama ditolak DOKU | Tetap aman: id sesi kita selalu unik. |
| 3 | Halaman Checkout boleh di iframe | Kasir pakai opsi B (SNAP QRIS). |
| 4 | DOKU berhenti mengirim ulang setelah balasan 2xx | Dedupe `Request-Id` tetap menahan. |
| 5 | Satu `payment_method_types` = customer tidak bisa memilih metode lain di halaman DOKU | Kalau bisa, perlakukan sebagai multi-metode dan kunci lewat status. |
| 6 | VA DOKU bernominal tetap & hanya bisa dibayar sekali; QRIS dinamis sekali pakai | Kalau VA bisa dibayar berkali-kali: VA langsung dibatalkan setelah lunas (closeSiblings versi DOKU) dan dobel ditangkap jalur refund. |
| 7 | Request ulang dengan `Request-Id` sama membalas tagihan asli (409) | Timeout bikin tagihan → tagihan yatim dibatalkan rekonsiliasi. |
| 8 | Tanda tangan webhook lolos lewat Nginx sandbox (body & path utuh) | Perbaiki konfigurasi proxy sebelum Tahap 2 lanjut. |

_Hasil uji (20 Okt 2026, server pre-production `sandbox.club61.id` + DOKU sandbox):_
- **#8 lolos** — notifikasi DOKU lewat Nginx diterima, tanda tangan sah (notifikasi #1, VA). URL notifikasi harus diisi di halaman kanal **biasa (non-SNAP)**; URL di halaman konfigurasi SNAP tidak dipakai DOKU Checkout.
- Notifikasi SUCCESS bisa datang **sebelum** API status DOKU ikut SUCCESS (±16 detik) → dicatat `retry`, lalu lunas lewat cek status. Sesuai rancangan (notifikasi tidak pernah dipercaya tanpa konfirmasi API status).
- `DOKU_IS_PRODUCTION=true` dengan kunci sandbox → `400 invalid_client_id` (permintaan ke `api.doku.com`). Mode production hanya bersama Client ID + Secret Key production.
- QRIS **tidak bisa** disimulasikan di sandbox DOKU (Simulator hanya OVO, ShopeePay, LinkAja) → jalur QR kasir diuji dengan VA di sandbox; QRIS dibuktikan dengan transaksi kecil di production (Tahap 4).

### 15a. Keputusan 15 Okt 2026 — booking online belum dibayar tidak bisa dibayar di kasir (sudah diterapkan)

Alasan: booking online yang bisa dilunasi di kasir menjadi celah menahan slot tanpa membayar. Selisih reschedule tetap boleh di kasir karena booking-nya sudah lunas dan customer hanya pindah jadwal.

| Lapisan | Perubahan |
| :--- | :--- |
| Kelola Pemesanan | Tombol **"Bayar di POS Walk-In"** hanya muncul untuk selisih reschedule (`LOCKED` + `reschedule_count > 0` + ada tagihan). Tombol cek status pembayaran online tetap ada. |
| POS (`?tagihan=` / `startSettlement`) | Booking online belum dibayar (`PENDING_PAYMENT` / `PENDING` / keranjang `LOCKED` tanpa reschedule) → ditolak dengan pemberitahuan "Dibayar Online Saja". Label tagihan selalu "Selisih Reschedule". |
| Server (`adminSettleCashierPayment`) | Menolak yang sama dengan HTTP 400 — tidak bisa ditembus lewat request langsung. |
| Test | `PadelBookingApiTest::test_cashier_cannot_settle_unpaid_online_booking` (baru). Dua test lama yang menyimulasikan selisih reschedule dengan `PENDING_PAYMENT` / tanpa `reschedule_count` disamakan dengan data asli `adminRescheduleBooking`. Full suite 838 lulus. |

---

### 15b. Hasil Tahap 1 — pengarah + metode dari admin (15 Okt 2026)

Perilaku Midtrans tidak berubah: semua jalur pengarah diteruskan ke service Midtrans yang sama. Full suite 854 lulus (838 lama + 16 baru).

| Bagian | Isi |
| :--- | :--- |
| Pengarah | `app/Services/Payment/OnlineGatewayRouter.php`: `reconcileOrder`, `hasOnlinePaymentAttempt`, `hasGatewayPayment`, `prepareSession`, `settlePreparedSession`, `cancelSessions`, `gatewayLabel`, `onlineGateway()`, `posQrisGateway()`. Hasil beberapa gateway digabung: PAID → PENDING → ERROR → NOT_PAID. Service gateway diambil saat dipanggil (mock / binding tetap terpakai). DOKU belum terpasang → selalu jatuh ke Midtrans walau tersimpan DOKU. |
| Titik yang dialihkan | Pembersih slot (`ManagesScheduleAndSlots`), kasir (`ManagesRescheduleAndCashier`, `BookOfflineCourt`), Kelola Pemesanan (pesan & tooltip pakai nama gateway), tutup sesi setelah kasir (`PaymentOrchestratorService`), invoice (`PadelBookingController`, `MembershipController`), membership (`MembershipOnlinePaymentService`), checkout padel (`ManagesCheckoutAndPayments`: siapkan sesi & lunasi sesi lama). `SnapSessionGuard::record` / `reusedPayment` tetap dipakai langsung (format sesi Midtrans) — dipilih per gateway di Tahap 2. |
| Tidak disentuh | `MidtransReconciliationService`, `SnapSessionGuard`, `PaymentWebhookController`, `PosMidtransQrisService`, `payment:reconcile-midtrans`. `MidtransService` hanya 1 baris: kanal Snap dibaca lewat `OnlinePaymentMethodService::midtransChannels` supaya metode tambahan admin terpetakan. |
| Metode | Migrasi `2026_10_15_100001`: kolom `kind`, `midtrans_channels`, `doku_channels`, `is_custom` (metode bawaan diisi dari katalog; kanal bawaan tetap dibaca dari katalog & dikunci di model). Katalog memuat daftar kode resmi (`MIDTRANS_CHANNELS`, `DOKU_CHANNELS`, dicek dari dokumentasi 15 Okt 2026). Kode DOKU bawaan: QRIS `QRIS`, BCA `VIRTUAL_ACCOUNT_BCA`, Mandiri `VIRTUAL_ACCOUNT_BANK_MANDIRI`, BNI `VIRTUAL_ACCOUNT_BNI`, BRI `VIRTUAL_ACCOUNT_BRI`, CIMB `VIRTUAL_ACCOUNT_BANK_CIMB`, VA Bank Lain `VIRTUAL_ACCOUNT_BANK_PERMATA` + `VIRTUAL_ACCOUNT_BANK_SYARIAH_MANDIRI` (BSI), kartu tidak ada. |
| Tambah Metode | Tombol **Tambah Metode** (izin `manage_online_payment_methods`): jenis QRIS / VA saja, kanal dari daftar resmi, satu kanal = satu metode, minimal satu kanal, QRIS maks. Rp10 juta, kode otomatis dari nama (mis. `DANAMON_VA`, tidak bentrok dengan kode bawaan / kasir), dibuat **nonaktif**. Kanal metode tambahan bisa diedit; jenisnya tidak. Semua validasi diulang di server. |
| Penyaringan | Metode tanpa kanal di gateway yang dipakai disembunyikan dari customer (`available`, `forFrontend`, `assertSelectable`) dan dari kasir (`forPos` ikut gateway QR kasir). Nonaktifkan metode ditolak kalau tidak tersisa metode yang bisa dibayar lewat gateway aktif. |
| Pilihan gateway | Tabel `payment_gateway_settings` (`online_gateway`, `pos_qris_gateway`, migrasi `2026_10_15_100002`), tombol **Gateway Pembayaran** dengan izin baru `manage_payment_gateway` (khusus super_admin), gateway yang belum siap tidak bisa dipilih (alasannya ditampilkan), tercatat di Log Aktivitas (CRITICAL). Kartu info halaman menampilkan gateway yang dipakai. |
| Config | `config/services.php` blok `doku` + `.env.example` (`DOKU_ENABLED`, `DOKU_CLIENT_ID`, `DOKU_SECRET_KEY`, `DOKU_IS_PRODUCTION`, `DOKU_NOTIFICATION_URL`). Belum dipakai sampai Tahap 2. |
| Test | `tests/Feature/Payment/OnlineGatewayRouterTest.php` (16 test). Dua test lama hanya disesuaikan teks tooltip ("Cek Status Pembayaran ke Gateway"). |

Catatan untuk Tahap 2: tagihan baru membuat sesi lewat `onlineGateway()` (sekarang `PaymentManager` + Snap), `gatewaysOf()` mengenali tagihan DOKU dari `payment_gateway` / `payload_log.doku_invoice_ids`, `cancelSessions()` menerima gateway DOKU, `assertSelectable` untuk bayar ulang tagihan lama memakai gateway tagihan itu.

### 15c. Hasil Tahap 2 — DOKU online (15 Okt 2026)

Full suite 877 lulus (854 lama + 23 `tests/Feature/Payment/DokuGatewayTest.php`); semua HTTP DOKU di-fake.

| Bagian | Isi |
| :--- | :--- |
| File DOKU | `app/Services/Payment/Doku/`: `DokuSignature`, `DokuClient`, `DokuCheckoutService`, `DokuSessionGuard`, `DokuReconciliationService`, `DokuNotificationProcessor`. `DokuNotificationController` (route `POST /api/v1/payments/doku/notification`, throttle 300/menit), model `App\Models\Payment\DokuNotification` + migrasi `2026_10_15_120001`, command `payment:reconcile-doku` (tiap 5 menit) & prune 90 hari, log `storage/logs/doku.log`. |
| Tanda tangan | Rumus dari dokumentasi + contoh kode PHP Jokul: komponen digabung `\n` tanpa baris baru di akhir, `Digest` hanya untuk request ber-body, body dikirim mentah (Digest = byte yang dikirim). Notifikasi: `Client-Id` harus sama, `Request-Target` = path route kita, `hash_equals`. |
| Bikin tagihan | Invoice `{nomor order}-DK-{waktu}{acak}` (maks. 64, huruf/angka/strip, tidak dipakai ulang). Satu metode per sesi, `payment_due_date` = sisa waktu bayar, jumlah `line_items` = nominal. Koneksi putus → diulang sekali dengan `Request-Id` sama (DOKU membalas respons asli, 409). Respons harus berisi URL `https://*.doku.com`, selain itu ditolak (503, checkout di-rollback). |
| Webhook | Verifikasi → simpan (unik `Request-Id`, tanpa data pribadi customer & header Signature) → balas 200 → proses setelah respons. SUCCESS dikonfirmasi ke API status + invoice & nominal dicocokkan; belum sinkron → `retry`, tagihan belum tersimpan → `unmatched`, keduanya diproses ulang rekonsiliasi. EXPIRED hanya menandai sesi aktif (sesi lunas / ganda tidak pernah diturunkan). Nominal beda → `[ALERT]`, tidak dilunasi otomatis. |
| Anti-dobel | `DokuSessionGuard` meniru `SnapSessionGuard`: metode sama → sesi dipakai lagi; ganti metode → sesi lama dibatalkan (Cancel API dengan `original_request_id`) lalu dibuktikan lewat API status; belum terbukti → 503, tidak ada sesi kedua; sesi lama ternyata lunas → dilunasi, tidak ada sesi baru; maks. 6 sesi; sesi saudara ditutup setelah lunas & disapu 48 jam (bayar ganda → `DUPLICATE` + refund PENDING). |
| Pengarah | DOKU terpasang. Order tanpa jejak DOKU = Midtrans saja (perilaku lama); order hanya-DOKU tidak ditanyakan ke Midtrans. Tagihan yang sudah punya sesi diurus gateway pembuatnya selama masih bisa dipakai; DOKU dimatikan → bayar ulang pindah ke Midtrans setelah sesi DOKU ditutup & dibuktikan. QR kasir tetap Midtrans (Tahap 3). |
| Titik yang disambung | Checkout padel, bayar ulang, selisih reschedule, checkout & lanjut bayar membership, pembatalan membership, pembatalan booking / slot hangus (`cancelOrderSessions`), tutup tagihan selisih, pelunasan kasir (sesi DOKU dibatalkan, id kasir sendiri), `ownsGatewayId`, penutupan sesi saudara, Buku Transaksi (nomor VA & kanal DOKU), teks admin / kasir tanpa "Midtrans" tetap. |
| Halaman customer | DOKU memakai **redirect** ke halaman bayar DOKU (callback ke invoice). Cabang DOKU di checkout diletakkan SEBELUM cabang "tanpa snap_token = sudah lunas". Data internal sesi (`request_id`, token) tidak dikirim ke browser. |

**Keputusan yang berbeda dari draft:**
- **Redirect, bukan popup.** Dokumentasi DOKU tidak menyediakan callback selesai / tutup untuk popup JS; redirect juga tidak memuat skrip pihak ketiga.
- **Tidak ada mode mock DOKU.** DOKU hanya bisa dipilih kalau kunci terisi, jadi tidak ada jalur "lunas tanpa uang".
- **"Catat dulu baru panggil DOKU" (§10.1 no. 3) diganti.** Checkout membuat sesi di dalam transaksi yang mengunci slot; membongkarnya berisiko. Pengganti: notifikasi yang datang sebelum sesi tersimpan disimpan sebagai `unmatched` dan diproses ulang, dan rekonsiliasi status menangkap pembayarannya begitu sesi tersimpan.
- **VA BTN, BNC & BPD (BJB) dikeluarkan dari daftar kanal DOKU** — Cancel API DOKU tidak mendukungnya, sesi lama tidak bisa ditutup saat ganti metode.
- **Tanda tangan respons DOKU tidak diverifikasi** — dokumentasi tidak memberi rumus yang bisa diuji; kepercayaan pada respons = TLS ke `api(-sandbox).doku.com` + pencocokan invoice & nominal.

### 15d. Hasil Tahap 3 — QR kasir lewat DOKU (19 Okt 2026, draft belum di-commit)

| Hal | Hasil |
| :--- | :--- |
| Cara tampil (§9.1) | **Opsi A, versi resmi.** Dokumentasi DOKU Checkout (Frontend Integration) punya mode pop-up: `jokul-checkout-1.0.0.js` + `loadJokulCheckout(payment.url)`. Isi script itu (dicek 19 Okt): membuat `<iframe src="{payment.url}?view=iframe">` di modal + menutup modal saat DOKU mengirim pesan `closeJokul`. Header halaman DOKU sandbox: `x-frame-options: ALLOW-FROM sandbox.doku.com` (tidak dikenali Chrome, tanpa CSP). SNAP (opsi B) tidak diperlukan. |
| Keamanan | Script DOKU **tidak** dimuat di halaman admin (menimpa `window.onclick`, menyusun HTML dari URL lewat `innerHTML`, modal menumpuk & tidak pernah dihapus, dan berjalan dengan akses penuh ke sesi kasir). Kasir memakai iframe sendiri dengan URL yang sama (`DokuCheckoutService::popupUrl`, hanya https *.doku.com), `sandbox` **tanpa** `allow-top-navigation` (halaman DOKU tidak bisa memindahkan layar kasir), dan sesi `auto_redirect=false`. Lunas hanya dari webhook bertanda tangan / API status (jalur online yang sama, nominal dicocokkan), bukan dari iframe. |
| File | `Doku/DokuPosQrisService.php`, `pos/partials/doku-qris-modal.blade.php`; pemilih gateway kasir `Pos/PosAutoPayment.php` (dipakai POS Walk-In, Jual Membership, F&B, `pos:sweep-auto-payments`); pembatalan order bersama `Pos/PosPendingOrderCanceller.php` (dipindah dari `PosMidtransQrisService`, perilaku Midtrans sama). |
| Batal | Urutan: cek DOKU (sudah bayar → transaksi diteruskan) → sesi DOKU dibatalkan & dibuktikan tertutup → baru order batal. Kasir menekan Batalkan tapi DOKU belum bisa memastikan tertutup (mis. Order Cancellation belum aktif) → ditolak, QR tetap tampil. Kedaluwarsa → tetap dibatalkan (uang terlambat = pembayaran ganda + refund PENDING). |
| Rekap shift | Provider `DOKU_QRIS` → baris "QRIS Otomatis DOKU (Kasir)", sumber "Dashboard pembayaran online". |
| Pengaturan | Gateway QR kasir = DOKU bisa dipilih kalau DOKU aktif & kunci terisi; ditolak kalau belum ada metode "Tampil di Kasir" berkanal gateway itu. |
| Test | `tests/Feature/Pos/DokuQrisPosTest.php` (7). |

**Wajib dicek sebelum dipakai di production (Tahap 3):** QR tampil di iframe di tablet kasir (sandbox, lalu production — header `x-frame-options` production belum dicek); tombol di dalam halaman DOKU (salin / unduh QR) jalan di dalam sandbox iframe; fitur Order Cancellation aktif (tanpa itu kasir tidak bisa membatalkan QR DOKU sebelum kedaluwarsa).

**Temuan sandbox 20 Okt 2026 (ganti metode gagal 503):** `POST /checkout/v3/cancellations` menjawab HTTP 200 `message: "SUCCESS"`, tapi API status masih `PENDING` sesaat sesudahnya. Dokumentasi DOKU: order yang berhasil dibatalkan langsung tidak bisa dibayar lagi. Maka `DokuCheckoutService::close()` sekarang menganggap sesi **tertutup** kalau pembatalan diterima (2xx + message `SUCCESS`, string atau daftar) **dan** status bukan lunas — sama dengan aturan SNAP. Jawaban batal lain (ditolak / tanpa `SUCCESS`) tetap diblok (503 / kasir tetap PENDING). Test: `DokuGatewayTest` (batal diterima + status PENDING → ganti metode jalan; jawaban tanpa `SUCCESS` → 503).

### 15e. Modul 27b — DOKU SNAP untuk QRIS & VA BCA (20 Okt 2026, draft belum di-commit)

Alasan: sales DOKU mengonfirmasi API non-SNAP akan dihentikan; di akun production VA BCA hanya tersedia sebagai SNAP. Scope pertama (kejar tayang): **QRIS + VA BCA**. Metode DOKU lain tetap lewat DOKU Checkout sampai dipindah.

| Hal | Isi |
| :--- | :--- |
| Saklar | `.env`: `DOKU_SNAP_ENABLED=true` + kunci lengkap → metode yang kanalnya `QRIS` / `VIRTUAL_ACCOUNT_BCA` otomatis lewat SNAP; kunci tidak lengkap / saklar mati → kembali DOKU Checkout. Gateway tetap "DOKU" di admin. |
| Kunci (`.env`, path file) | `DOKU_SNAP_CLIENT_ID`, `DOKU_SNAP_CLIENT_SECRET`, `DOKU_SNAP_PRIVATE_KEY_PATH` (+ passphrase), `DOKU_SNAP_DOKU_PUBLIC_KEY_PATH`, `DOKU_SNAP_QRIS_MERCHANT_ID`, `DOKU_SNAP_QRIS_TERMINAL_ID`, `DOKU_SNAP_QRIS_POSTAL_CODE`, `DOKU_SNAP_BCA_PARTNER_SERVICE_ID`, `DOKU_SNAP_VA_LENGTH` (16). Mode sandbox/production ikut `DOKU_IS_PRODUCTION`. |
| Keluar (kita → DOKU) | Token B2B `POST /authorization/v1/access-token/b2b` (SHA256withRSA `clientId|X-TIMESTAMP`, cache 15 menit). Transaksi: HMAC-SHA512(clientSecret, `METHOD:path:token:sha256(body):X-TIMESTAMP`), header `X-PARTNER-ID`, `X-EXTERNAL-ID` (angka), `CHANNEL-ID`. QRIS: `qr-mpm-generate` / `qr-mpm-query` / `qr-expire`. VA: `create-va` (tipe C, 16 digit, `reusableStatus=false`) / `orders/v1.0/transfer-va/status` / `delete-va`. Rumus dicocokkan dengan SDK resmi `doku/doku-php-library` (tanpa memasang library). |
| Masuk (DOKU → kita) | Token: `POST /api/v1/payments/doku-snap/v1.0/access-token/b2b` (tanda tangan RSA DOKU dicek dengan public key DOKU, jam ±5 menit) → JWT RS256 kita. Notifikasi VA: `/api/v1/payments/doku-snap/v1.1/transfer-va/payment`; QRIS: `/api/v1/payments/doku-snap/v1.0/qr/qr-mpm-notify` — wajib membawa JWT kita, disimpan di `doku_notifications`, dilunasi HANYA setelah API status SNAP menyatakan lunas & nominal cocok. |
| Tampilan | Halaman bayar sendiri: invoice (padel & membership) menampilkan QR (digambar di browser, koreksi M) / nomor VA + salin + cara bayar BCA + hitung mundur; checkout & beli membership langsung ke invoice. Kartu kasir menggambar QR / nomor VA sendiri. Tidak ada halaman DOKU → request `sse-subscribe` halaman DOKU hilang. |
| Jalur yang dipakai ulang | Sesi SNAP dicatat di format `DokuSessionGuard` (`api: SNAP`); `DokuCheckoutService::state/close/cancel` meneruskannya ke `DokuSnapService`, jadi ganti metode, rekonsiliasi, pembayaran ganda, kasir, dan pembersih tidak diubah. |
| Test | `tests/Feature/Payment/DokuSnapTest.php` (10). Uji mutasi: token notifikasi selalu sah → gagal; rumus HMAC diubah → gagal. |

**Belum terbukti (wajib dicek di sandbox SNAP):** kode `responseCode` sukses tiap API, isi `paymentFlagStatus` VA, format & header notifikasi VA/QRIS (termasuk `X-SIGNATURE` notifikasi — sekarang yang dicek JWT kita), path notifikasi yang dipanggil DOKU, dan balasan yang diterima DOKU (`2002500` VA, `2005200` QRIS). Semua tercatat di `storage/logs/doku-*.log` (`SNAP … → HTTP …` + `responseCode`).

**Wajib dibuktikan di sandbox sebelum production** (§15): status API untuk order yang dibatalkan (kode menganggap tertutup hanya kalau `transaction.status` EXPIRED / `order.status` ORDER_EXPIRED — kalau DOKU melaporkan status lain, ganti metode akan selalu ditolak 503 dan perlu disesuaikan); jawaban API status untuk invoice tidak dikenal (diasumsikan HTTP 404); isi notifikasi QRIS & tiap VA; perilaku `override_notification_url`; tanda tangan lewat Nginx sandbox.

---

## 16. Di Luar Cakupan

- Kartu kredit & e-wallet langsung (Midtrans maupun DOKU).
- Biaya MDR / biaya admin per metode.
- Gateway per metode (misal QRIS lewat DOKU, VA lewat Midtrans).
- Refund otomatis lewat API gateway (refund tetap manual, Modul 21).
- Refactor file Midtrans menjadi gaya baru.
