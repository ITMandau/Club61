# Product Requirements Document (PRD)
## Modul 16: Log Aktivitas & Jejak Audit (Activity / Audit Log) — Panel Admin
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-16-ACTIVITY-AUDIT-LOG` |
| **Versi** | `v1.0.0-DRAFT` |
| **Status** | Draft — menunggu keputusan PM atas §9 (Pertanyaan Terbuka) sebelum implementasi |
| **Sumber Requirement** | Permintaan pemilik produk: "1 panel log yang merekam semua aktivitas user — kasir, resepsionis, POS, semuanya — yang melakukan transaksi dan perubahan (tambah, edit, hapus), lengkap dengan jam, nama pengguna, dan perubahannya." |
| **Dependensi Teknis** | `PRD_MODUL_09_DYNAMIC_RBAC_FILAMENT_SHIELD.md` (izin baru masuk `Club61PermissionMatrix`). Tidak ada package baru yang wajib. |
| **Target Pengguna** | **Super Admin / Owner** (default), bisa diberikan ke role lain lewat menu Roles & Hak Akses. |
| **Prinsip Utama** | **SATU TABEL, SATU PINTU TULIS**, **TIDAK BISA DIUBAH / DIHAPUS DARI APLIKASI**, **SIMPAN "SEBELUM → SESUDAH", BUKAN CUMA "ADA YANG BERUBAH"**, **JANGAN PERNAH MENYIMPAN RAHASIA** (password, token, key). |

---

## 1. Latar Belakang & Masalah

Saat ini tidak ada cara menjawab pertanyaan operasional paling dasar:

- *"Siapa yang mengubah harga lapangan kemarin sore?"*
- *"Kasir mana yang melakukan refund Rp600.000 untuk BK-PAD-XXXX?"*
- *"Kenapa menu Iced Latte tiba-tiba nonaktif?"*
- *"Siapa yang menambahkan izin refund ke role admin?"*

Yang ada hanya log per modul yang terpisah-pisah dan tidak lengkap:

| Yang sudah ada | Merekam | Kekurangan |
| :--- | :--- | :--- |
| `membership_usage_logs` | Mutasi kuota membership | Khusus kuota; tidak mencatat perubahan data lain |
| `payments.payload_log` | Payload gateway/EDC per pembayaran | Bukan jejak "siapa melakukan apa"; menempel di baris pembayaran |
| `pos_cashier_shifts` | Buka/tutup shift + rekonsiliasi | Hanya ringkasan shift |
| `refunds` | Refund resmi | Tidak ada siapa yang menyetujui/mengubah |
| `storage/logs/laravel.log` | Error teknis | Bukan untuk bisnis, tidak bisa dicari/difilter oleh owner |

Tidak ada package audit (mis. `spatie/laravel-activitylog`) yang terpasang.

### Yang TIDAK Sedang Dibangun (v1)

- **Bukan log baca/lihat halaman** (siapa membuka halaman apa). Volume sangat besar dan nilai auditnya rendah — kecuali akses ke data sensitif (lihat §9, pertanyaan 3).
- **Bukan pengganti log teknis** (`laravel.log`) untuk debugging error.
- **Bukan sistem notifikasi real-time.** Alert otomatis (mis. refund di atas nominal tertentu) masuk fase 3.

---

## 2. Solusi Arsitektur

### 2.1 Satu Tabel `activity_logs` + Satu Pintu Tulis `ActivityLogger`

Semua jejak — dari kasir, resepsionis, admin, customer, maupun sistem (scheduler, webhook Midtrans) — masuk ke **satu tabel** lewat **satu service** `App\Services\Audit\ActivityLogger`. Tidak ada kode lain yang boleh menulis langsung ke tabel ini.

Dua cara pencatatan:

1. **Otomatis (model event)** — trait `RecordsActivity` dipasang di model penting. Setiap `created` / `updated` / `deleted` / `restored` otomatis tercatat beserta **hanya kolom yang berubah** (sebelum → sesudah). Tidak perlu menyentuh setiap tombol di setiap halaman.
2. **Eksplisit (event bisnis)** — untuk aksi yang maknanya lebih dari sekadar "kolom berubah": transaksi lunas, refund, check-in, buka/tutup shift, login, akses ditolak. Dipanggil manual: `ActivityLogger::record('booking.refunded', $booking, 'Refund Rp600.000 via Transfer', [...])`.

### 2.2 Rekomendasi: Bangun Sendiri (Bukan Package)

| Opsi | Kelebihan | Kekurangan |
| :--- | :--- | :--- |
| **Bangun sendiri (direkomendasikan)** | 1 tabel, tanpa dependensi baru; label & deskripsi Bahasa Indonesia; kontrol penuh atas redaksi data sensitif; skema pas dengan kebutuhan (channel POS/KDS/API, snapshot nama & role) | Perlu ditulis & dites sendiri (±1 trait, 1 service, 1 page) |
| `spatie/laravel-activitylog` | Matang, banyak dipakai | Dependensi baru; skema generik (tanpa channel, snapshot role, severity); tetap perlu kustomisasi untuk event bisnis & redaksi |

Kebutuhan di sini cukup spesifik (channel, snapshot, event bisnis, redaksi), sehingga keuntungan package tidak sebanding dengan kustomisasinya.

### 2.3 Log Tidak Bisa Diubah atau Dihapus dari Aplikasi

- Model `ActivityLog` **menolak `update()` dan `delete()`** (melempar exception). Tidak ada tombol edit/hapus di panel — termasuk untuk super_admin.
- Penghapusan hanya lewat **command retensi** terjadwal (`audit:prune`, default simpan 24 bulan — lihat §9) yang dijalankan sistem, bukan user.
- Fase 3 (opsional): **hash berantai** — setiap baris menyimpan hash dari baris sebelumnya, sehingga manipulasi langsung di database pun terdeteksi.

### 2.4 Ditulis di Transaksi Database yang Sama

Log dari model event ikut transaksi aksinya. Kalau aksi gagal & di-rollback, log-nya ikut hilang — jadi log **tidak pernah mengklaim sesuatu terjadi padahal tidak**. Pengecualian: event "percobaan gagal" (login gagal, akses ditolak) sengaja ditulis di luar transaksi agar tetap tercatat.

---

## 3. Skema Database

### 3.1 Tabel Baru `activity_logs`

| Kolom | Tipe | Keterangan |
| :--- | :--- | :--- |
| `id` | ULID | Primary key (urut waktu) |
| `created_at` | timestamp | **Jam kejadian** (tidak ada `updated_at` — log tidak pernah diubah) |
| `module` | string(30) | `PADEL`, `POS_WALKIN`, `FNB`, `MEMBERSHIP`, `SPONSOR`, `FINANCE`, `MASTER_DATA`, `USER_ROLE`, `CONTENT`, `AUTH`, `SYSTEM` |
| `event` | string(60) | Mis. `booking.refunded`, `fnb_menu.updated`, `auth.login_failed` |
| `severity` | string(10) | `INFO` / `WARNING` / `CRITICAL` (lihat §4) |
| `description` | string(255) | Kalimat siap baca: *"Refund Rp600.000 untuk BK-PAD-ZNEUXNEX via Transfer Bank"* |
| `subject_type` / `subject_id` | string / string(26) | Data yang disentuh (mis. `PadelBooking` + ULID) |
| `subject_label` | string(120) | **Snapshot** label manusiawi (kode booking, nomor order, nama menu) — tetap terbaca walau datanya kelak dihapus |
| `causer_id` | ULID nullable | User pelaku; `null` untuk sistem |
| `causer_name` / `causer_role` | string | **Snapshot** nama & role saat kejadian (tidak ikut berubah kalau user diganti nama/role/dihapus) |
| `actor_type` | string(15) | `USER` / `SYSTEM` (scheduler) / `WEBHOOK` (Midtrans) / `CUSTOMER` |
| `channel` | string(20) | `ADMIN_PANEL`, `POS_FNB`, `POS_WALKIN`, `KDS`, `CUSTOMER_WEB`, `API`, `SCHEDULER`, `WEBHOOK` |
| `ip_address` / `user_agent` | string nullable | Asal perangkat |
| `url` / `http_method` | string nullable | Halaman/endpoint asal aksi |
| `changes` | JSON nullable | `{"base_price": {"old": 38000, "new": 42000}, "is_available": {"old": true, "new": false}}` |
| `meta` | JSON nullable | Konteks tambahan (nominal, metode bayar, alasan refund, shift id, dll.) |
| `batch_id` | UUID nullable | Mengelompokkan banyak log dari **satu request** (mis. checkout F&B: 1 order + 3 item + 1 pembayaran) |

**Indeks:** `created_at`; `(causer_id, created_at)`; `(subject_type, subject_id)`; `(module, event)`; `batch_id`.

### 3.2 Redaksi Wajib (Tidak Pernah Disimpan)

`password`, `remember_token`, `snap_token`, `payment_url`, `qr_code_hash`, `qr_pass_hash`, server/client key apa pun, dan kolom `payload_log` mentah (diringkas ke `meta` yang aman saja). Daftar ini dipusatkan di satu konstanta di `ActivityLogger` supaya model baru otomatis aman.

---

## 4. Kebutuhan Fungsional — Apa Saja yang Direkam

### FR-01: Perubahan Data Otomatis (Tambah / Edit / Hapus)

Trait `RecordsActivity` dipasang di model berikut. Setiap perubahan tercatat dengan diff kolom:

| Modul | Model | Contoh kejadian |
| :--- | :--- | :--- |
| Padel | `PadelCourt`, `CourtEquipment`, `PadelBooking` | Tarif lapangan diubah, alat sewa dinonaktifkan |
| F&B | `FnbCategory`, `FnbMenu`, `FnbModifierGroup`, `FnbModifierOption` | Harga menu naik, menu dimatikan |
| Keuangan | `ClubFinanceSetting`, `Voucher` | Pajak PB1 diubah dari 10% → 11% (**CRITICAL**) |
| Membership | `MembershipPlan`, `MembershipPlanBenefit`, `UserMembership` | Benefit kuota paket diubah |
| Sponsor | `SponsorOrganization`, `SponsorOrganizationMember`, `SponsorAccessSchedule` | PIC sponsor diganti |
| User & Akses | `User`, `Role` + perubahan izin role (`role_has_permissions`) | Role user diganti, izin refund ditambahkan ke admin (**CRITICAL**) |
| Konten | `CompanyProfileSetting`, `CompanyProfileFacility`, `CompanyProfileValueProp` | Teks hero diubah |

**Catatan penting — mass update tidak memicu model event.** Kode seperti `PadelBooking::where(...)->update([...])` (dipakai job expiry & sinkronisasi status) **melewati** model event. Titik-titik ini wajib dicatat eksplisit sebagai satu log ringkasan (mis. *"Sistem menghanguskan 3 booking yang tidak dibayar"* + daftar kode di `meta`).

### FR-02: Transaksi & Event Bisnis (Eksplisit)

| Event | Dicatat di (kode yang sudah ada) | Severity |
| :--- | :--- | :--- |
| `walkin.checkout_paid` | `BookOfflineCourt` (checkout walk-in) | INFO |
| `fnb.checkout_paid` | `FnbCashierTerminal::submitFnbCheckout()` | INFO |
| `membership.sold` | `JualMembership` | INFO |
| `membership.granted_free` | `ListSponsorOrganizations` (Berikan Membership Corporate) | **CRITICAL** |
| `booking.refunded` / `booking.cancelled` | `KelolaPemesanan::executeCancelRefund()` | **CRITICAL** |
| `booking.rescheduled` | `KelolaPemesanan::executeReschedule()` | WARNING |
| `booking.settled_at_cashier` | `KelolaPemesanan::executeSettleSupplemental()` | INFO |
| `booking.checked_in` / `booking.completed` | Kelola Pemesanan, Monitoring Lapangan, `/pos/check-in` | INFO |
| `equipment.returned` | `KelolaPemesanan::executeReturnEquipment()` | INFO |
| `shift.opened` / `shift.closed` | `BookOfflineCourt`, `FnbCashierTerminal` | INFO / **WARNING** kalau ada selisih settlement |
| `payment.paid_via_webhook` | `PaymentWebhookController` | INFO |
| `payment.paid_via_reconcile` | `MidtransReconciliationService` | WARNING (webhook gagal — perlu dicek) |
| `payment.late_settlement_refund` | `PaymentOrchestratorService` (order batal tapi uang masuk) | **CRITICAL** |
| `booking.expired_by_system` | `releaseExpiredLocks()` (ringkasan per putaran) | INFO |

### FR-03: Autentikasi & Keamanan

| Event | Severity |
| :--- | :--- |
| `auth.login` / `auth.logout` | INFO |
| `auth.login_failed` (email/HP yang dicoba, tanpa password) | WARNING |
| `auth.access_denied` — percobaan membuka halaman / menjalankan aksi tanpa izin (mis. refund oleh admin) | WARNING |
| `user.password_reset_by_staff` | **CRITICAL** |

### FR-04: Panel "Log Aktivitas" (Filament Page, Read-Only)

- **Tabel**: Jam · Pengguna (nama + badge role) · Modul · Aksi · Keterangan · Channel · Severity.
- **Filter**: rentang tanggal (default hari ini), pengguna, role, modul, jenis aksi, channel, severity, dan **pencarian bebas** (kode booking, nomor order, nama menu, nama pelanggan).
- **Detail (klik baris)**: tabel perubahan **Kolom | Sebelum | Sesudah**, konteks (`meta`), IP & perangkat, halaman asal, serta **semua log lain dalam request yang sama** (`batch_id`) — misalnya satu checkout F&B terlihat utuh.
- **Riwayat per data (fase 2)**: tombol "Riwayat" di Kelola Pemesanan / Menu F&B / Kelola Sponsor untuk melihat semua log satu booking/menu/sponsor.
- **Export CSV** untuk rentang yang difilter (izin terpisah).
- **Tanpa tombol edit/hapus sama sekali.**

### FR-05: Otorisasi

Slug baru di `Club61PermissionMatrix` (modul "11. Master Data & Hak Akses"):

| Slug | Keterangan | Default |
| :--- | :--- | :--- |
| `View:ActivityLog` | Akses menu Log Aktivitas | super_admin saja (masuk `BACKDOOR_PERMISSIONS`) |
| `export_activity_logs` | Export CSV log | super_admin saja |

Alasan default super_admin saja: log berisi siapa melakukan refund, perubahan izin, dan data pelanggan. Kalau admin boleh melihat, bisa diberikan eksplisit lewat Roles & Hak Akses.

---

## 5. Kebutuhan Non-Fungsional

| Aspek | Target |
| :--- | :--- |
| **Performa tulis** | 1 INSERT per event, di request yang sama. Tidak butuh queue (aplikasi saat ini memang tidak menjalankan queue worker). |
| **Volume** | Estimasi ±300–1.500 baris/hari (puluhan transaksi × beberapa log per transaksi + login + perubahan admin). 24 bulan ≈ <1 juta baris — ringan untuk MySQL dengan indeks di atas. |
| **Performa baca** | Filter default "hari ini" + paginasi 25 baris; semua filter memakai indeks. |
| **Retensi** | `audit:prune` harian, hapus log lebih tua dari N bulan (konfigurasi `.env`, default 24). |
| **Kebal kegagalan** | Kegagalan menulis log **tidak boleh** menggagalkan transaksi bisnis di luar transaksi DB-nya (event gagal-login/akses-ditolak) — cukup masuk `laravel.log`. |
| **Privasi** | Redaksi wajib §3.2; tidak menyimpan password yang dicoba saat login gagal. |

---

## 6. Rencana Implementasi Bertahap

| Fase | Isi | Hasil untuk owner |
| :--- | :--- | :--- |
| **Fase 1** | Migration `activity_logs`, `ActivityLogger`, trait `RecordsActivity` di model FR-01, log login/logout/gagal, panel Log Aktivitas read-only + filter + detail diff, izin `View:ActivityLog` | Sudah bisa menjawab "siapa mengubah apa & kapan" untuk semua data master, harga, menu, user & role |
| **Fase 2** | Event bisnis FR-02 (transaksi, refund, reschedule, check-in, shift, pembayaran), akses ditolak, riwayat per data, log ringkasan mass update sistem | Semua transaksi kasir/resepsionis/POS & aksi sensitif terekam lengkap |
| **Fase 3** | Export CSV, retensi terjadwal, hash berantai anti-manipulasi, alert (mis. refund > Rp1 juta / perubahan pajak → notifikasi super_admin) | Audit formal & deteksi dini |

---

## 7. Rencana Pengujian

1. Edit harga menu F&B → 1 log `fnb_menu.updated`, `changes` hanya berisi `base_price` (old/new), pelaku & role benar.
2. Checkout F&B → log order, item, pembayaran berbagi satu `batch_id`; transaksi yang gagal (rollback) **tidak** meninggalkan log.
3. Refund oleh super_admin → `booking.refunded` severity CRITICAL dengan nominal & alasan di `meta`.
4. Admin mencoba refund (tanpa izin) → `auth.access_denied` tercatat walau aksinya ditolak.
5. Ubah izin role → log berisi izin yang ditambah/dicabut.
6. Password, `snap_token`, `qr_code_hash` **tidak pernah** muncul di `changes`/`meta` (test redaksi).
7. `ActivityLog::update()` / `delete()` melempar exception; panel tidak menampilkan tombol edit/hapus.
8. User tanpa `View:ActivityLog` tidak melihat menu & mendapat 403.
9. Webhook Midtrans & scheduler tercatat dengan `actor_type` WEBHOOK/SYSTEM dan `causer_id` null.
10. User yang namanya diganti / dihapus → log lama tetap menampilkan nama saat kejadian (snapshot).

---

## 8. Ruang Lingkup & Batasan (Out of Scope v1)

- Log akses baca/lihat halaman biasa.
- Rollback / "undo" perubahan dari panel log (log hanya catatan, bukan mesin pemulih).
- Integrasi ke sistem eksternal (SIEM, Slack) — bisa menyusul setelah fase 3.

---

## 9. Pertanyaan Terbuka (Butuh Keputusan PM)

1. **Retensi**: simpan berapa lama? Usulan 24 bulan (cukup untuk audit tahunan + perbandingan tahun lalu).
2. **Siapa yang boleh melihat**: super_admin saja (usulan), atau admin juga boleh melihat log **miliknya sendiri / timnya**?
3. **Log akses data sensitif**: perlu mencatat siapa yang *membuka* halaman Customer & Member VIP / Analytics Keuangan? (Di luar prinsip "tidak log baca", tapi berguna untuk data pribadi pelanggan.)
4. **Aktivitas customer**: booking & pembayaran customer ikut tampil di panel yang sama (usulan: ya, dengan `actor_type = CUSTOMER`), atau dipisah?
5. **Alert fase 3**: ambang nominal refund yang memicu notifikasi ke super_admin?
