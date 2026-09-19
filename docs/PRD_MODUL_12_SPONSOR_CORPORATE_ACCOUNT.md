# Product Requirements Document (PRD)
## Modul 12: Akun Sponsor Korporat & Kuota Jam Tim ("Sponsor Team Quota")
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-12-SPONSOR-CORPORATE-ACCOUNT` |
| **Versi** | `v1.0.0-DRAFT` |
| **Status** | **Proposed — Menunggu Persetujuan Implementasi** |
| **Sumber Requirement** | Permintaan langsung Head IT (Pak Sadrakh) melalui diskusi chat internal |
| **Target Pengguna** | Perusahaan/Instansi Sponsor (B2B), Admin Sponsor (penanggung jawab kuota), Member Karyawan Sponsor |
| **Prinsip Utama** | **SCOPED AUTHORIZATION (BUKAN VENUE-WIDE RBAC), ATOMIC QUOTA LEDGER, ZERO TOUCH KE CHECKOUT ENGINE STABIL** |

---

## 1. Latar Belakang & Kebutuhan Bisnis

Saat ini seluruh transaksi booking Padel di Club61 bersifat **individual** — 1 akun customer memesan dan membayar untuk dirinya sendiri. Permintaan bisnis baru datang dari kebutuhan korporat: sebuah perusahaan ingin membeli **paket blok jam lapangan** (misal 200 jam) sebagai fasilitas/benefit karyawan, dengan skema:

1. Perusahaan (disebut **Sponsor**) melakukan pembelian blok jam di muka (misal 200 jam), dicatat sebagai kuota prabayar.
2. Perusahaan menunjuk 1 **Admin Sponsor** (biasanya HR/koordinator internal) yang bertanggung jawab mengelola kuota tersebut.
3. Admin Sponsor dapat **mengundang/menambahkan Member** (karyawan) tertentu ke dalam grup sponsor-nya.
4. Admin Sponsor dapat **membuatkan jadwal booking langsung** untuk member (dipesankan), **dan/atau** **mengalokasikan jatah jam** ke member tertentu agar member tersebut bisa booking mandiri menggunakan jatahnya sendiri.
5. Setiap kali jam lapangan terpakai (baik dipesankan langsung oleh Admin Sponsor, maupun dipesan mandiri oleh Member menggunakan jatahnya), kuota Sponsor **berkurang otomatis** — tanpa transaksi pembayaran uang riil (Midtrans/Cash), karena sudah dibayar di muka secara korporat.

### Mengapa Ini Bukan Sekadar "Tambah Role Baru"

Sistem RBAC Club61 saat ini (Modul 09, berbasis Spatie Permission + Filament Shield) didesain untuk **staf internal venue** — setiap role (`admin`, `cashier`, dst.) berlaku **venue-wide**, tidak ada konsep "hak akses hanya berlaku ke grup tertentu". Admin Sponsor **bukan staf Club61** — dia tetap seorang *customer*, hanya diberi hak istimewa yang **hanya berlaku ke member di bawah organisasinya sendiri**. Memaksakan Admin Sponsor masuk ke sistem role venue-wide berisiko membocorkan hak akses ke seluruh sistem (persis kelas masalah scope-leak yang berulang kali ditemukan dan diperbaiki selama audit keamanan proyek ini). Oleh karena itu, Modul 12 membangun **lapisan otorisasi baru yang terpisah dan di-scope per-organisasi**, bukan memperluas Filament Shield.

---

## 2. Solusi Arsitektur

### 2.1 Entitas Baru: `SponsorOrganization` & `SponsorMember`

```
                    +---------------------------+
                    |   sponsor_organizations    |
                    |  (Perusahaan/Instansi)     |
                    |  - total_hours_purchased   |
                    |  - hours_remaining         |
                    |  - sponsor_admin_user_id   |
                    +--------------+--------------+
                                   |
                                   | hasMany
                                   v
                    +---------------------------+
                    |  sponsor_organization_     |
                    |       members              |
                    |  - user_id (member)         |
                    |  - allocated_hours (nullable)|
                    |  - hours_used               |
                    +---------------------------+
```

- **`sponsor_organizations`**: satu baris = satu perusahaan/kontrak sponsor, menyimpan total kuota jam yang dibeli dan sisa kuota (`hours_remaining`) sebagai **shared pool** default.
- **`sponsor_organization_members`**: menghubungkan `User` (karyawan) ke organisasi sponsor. Kolom `allocated_hours` **opsional**:
  - Jika `null` → member menggunakan **shared pool** langsung (kuota dipotong dari `sponsor_organizations.hours_remaining`).
  - Jika diisi angka → member punya **jatah pribadi** (sub-alokasi) yang dipotong dari jatahnya sendiri, terpisah dari member lain (Admin Sponsor yang menentukan pembagian ini).

### 2.2 Otorisasi Scoped, Terpisah dari Filament Shield

Dibuat `SponsorOrganizationPolicy` yang **tidak bergantung pada Spatie Role** sama sekali. Aturan dasarnya sederhana dan eksplisit:
```php
public function manage(User $user, SponsorOrganization $org): bool
{
    return $org->sponsor_admin_user_id === $user->id;
}
```
Admin Sponsor tetap ber-`role = 'customer'` di sistem — dia **tidak** mendapat akses Filament `/admin` sama sekali. Seluruh interaksi Admin Sponsor (undang member, alokasi jam, buatkan jadwal) terjadi melalui **portal customer** yang sudah ada (`resources/views/customer/...`), bukan panel staf.

### 2.3 Kuota Sebagai "Metode Bayar" Baru (Zero Touch ke Checkout Engine)

Alih-alih mengubah alur `checkout()`/`ManagesCheckoutAndPayments.php` yang sudah stabil dan teruji, kuota sponsor diperlakukan sebagai **metode pembayaran baru**: `SPONSOR_QUOTA`, sejajar dengan `CASH`, `QRIS`, `BANK_TRANSFER` yang sudah ada. Titik sentuhnya **hanya** menambahkan satu cabang kondisi baru sebelum proses hold-slot, bukan mengubah logic inti yang sudah ada:

```
Booking dengan payment_method = 'SPONSOR_QUOTA'
        │
        ▼
1. Validasi & kunci kuota SECARA ATOMIC (lockForUpdate) SEBELUM hold slot
2. Jika kuota cukup: potong kuota, lanjut holdBatchSlots() -> checkout() seperti biasa
3. Order langsung berstatus PAID (tidak ada uang riil, "pembayaran" = debit kuota)
4. Payment dicatat dengan payment_gateway = 'SPONSOR_QUOTA', amount = 0
5. Jika kuota tidak cukup: tolak dengan pesan jelas sebelum slot sempat di-hold
```

Ini mengikuti pola yang sama persis dengan perbaikan atomic-decrement pada `Voucher.quota` di Modul 10 — dikunci dan diperiksa di satu operasi database, bukan baca-lalu-tulis terpisah yang rawan race condition saat banyak member booking bersamaan.

---

## 3. Skema Database

### 3.1 Migration: `sponsor_organizations`
| Kolom | Tipe | Keterangan |
| :--- | :--- | :--- |
| `id` | ULID, PK | Primary key. |
| `name` | string(150) | Nama perusahaan/instansi sponsor. |
| `sponsor_admin_user_id` | ULID, FK → `users.id` | Penanggung jawab kuota (bukan role staf venue). |
| `total_hours_purchased` | decimal(8,2) | Total jam yang dibeli di muka (misal 200.00). |
| `hours_remaining` | decimal(8,2) | Sisa kuota shared pool, berkurang atomic tiap booking terpakai. |
| `status` | string(20), default `ACTIVE` | `ACTIVE`, `EXHAUSTED`, `EXPIRED`, `SUSPENDED`. |
| `valid_until` | date, nullable | Masa berlaku paket (opsional, null = tanpa batas waktu). |
| `notes` | text, nullable | Catatan internal staf (nomor kontrak, PIC, dll). |
| `created_by` | ULID, FK → `users.id` | Staf/admin venue yang mencatat pembelian awal. |
| `timestamps`, `softDeletes` | | |

### 3.2 Migration: `sponsor_organization_members`
| Kolom | Tipe | Keterangan |
| :--- | :--- | :--- |
| `id` | ULID, PK | Primary key. |
| `sponsor_organization_id` | ULID, FK → `sponsor_organizations.id` | Organisasi induk. |
| `user_id` | ULID, FK → `users.id` | Member (karyawan) yang diundang. |
| `allocated_hours` | decimal(8,2), nullable | Jatah pribadi; `null` = pakai shared pool organisasi. |
| `hours_used` | decimal(8,2), default 0 | Akumulasi jam yang sudah dipakai member ini. |
| `status` | string(20), default `ACTIVE` | `INVITED`, `ACTIVE`, `REVOKED`. |
| `invited_at` / `joined_at` | datetime, nullable | Jejak audit undangan. |
| `timestamps` | | |
| — | unique | `(sponsor_organization_id, user_id)` — member tidak boleh dobel di organisasi yang sama. |

### 3.3 Migration Aditif pada `padel_bookings` dan `payments` (Tanpa Mengubah Enum Lama)
| Tabel | Kolom Baru | Keterangan |
| :--- | :--- | :--- |
| `padel_bookings` | `sponsor_organization_id` (nullable, FK) | Menandai booking ini dibiayai sponsor mana (audit trail, laporan konsumsi per klien korporat). |
| `payments` | *(tidak perlu kolom baru)* | Cukup gunakan `payment_gateway = 'SPONSOR_QUOTA'` dan `amount = 0.00` — konsisten dengan pola `CASHIER_POS` yang sudah ada. |

---

## 4. Spesifikasi Kebutuhan Fungsional (Functional Requirements)

### FR-01: Pembuatan Organisasi Sponsor (Staf Venue)
- Pembelian blok jam korporat (misal 200 jam) adalah **transaksi B2B yang dinegosiasikan manual** (kontrak, invoice, transfer bank) — **bukan** melalui checkout online otomatis.
- Staf (`admin`/`super_admin`) mencatat kesepakatan ini lewat halaman Filament baru **"Kelola Sponsor Korporat"**: input nama perusahaan, total jam dibeli, dan menunjuk 1 User existing (atau membuat User baru) sebagai `sponsor_admin_user_id`.
- Setelah tercatat, `hours_remaining` otomatis diisi sama dengan `total_hours_purchased`.

### FR-02: Portal Admin Sponsor (Customer-Facing, Bukan Filament)
- Admin Sponsor login sebagai customer biasa, mendapat 1 menu tambahan "Kelola Tim Sponsor" di portal customer (hanya muncul jika `sponsor_admin_user_id` miliknya ada di tabel `sponsor_organizations`).
- Menampilkan: sisa kuota (`hours_remaining` / `total_hours_purchased`), daftar member, dan riwayat konsumsi jam.

### FR-03: Undang & Kelola Member
- Admin Sponsor memasukkan email/nomor telepon calon member.
- Sistem melakukan **pengecekan & normalisasi nomor telepon** dengan pola yang sama seperti `findOrCreateWalkInCustomer()` (Modul 11) — jika User dengan nomor/email tersebut sudah ada, tautkan langsung; jika belum, kirim undangan (link registrasi) yang begitu diklaim otomatis menautkan ke `sponsor_organization_members`.
- Admin Sponsor dapat mencabut (`REVOKED`) member kapan saja — member yang dicabut tidak bisa lagi memakai kuota organisasi tersebut, namun riwayat booking lamanya tetap utuh (bukan dihapus).

### FR-04: Alokasi Jatah Jam per Member (Opsional)
- Default: member baru tidak punya `allocated_hours` (`null`) → otomatis memakai **shared pool** organisasi.
- Admin Sponsor dapat mengatur `allocated_hours` per member (misal 10 jam per orang) — begitu diisi, member tersebut **hanya** bisa memotong dari jatah pribadinya (`allocated_hours - hours_used`), bukan langsung dari pool bersama, mencegah 1 member menghabiskan seluruh kuota organisasi tanpa sepengetahuan Admin Sponsor.

### FR-05: Booking Menggunakan Kuota Sponsor
Dua jalur booking yang sama-sama berujung pada logic pemotongan kuota yang identik:

1. **Dipesankan oleh Admin Sponsor** (mirip walk-in tapi self-service, tanpa staf): Admin Sponsor pilih member penerima, pilih slot, pilih `payment_method = SPONSOR_QUOTA`.
2. **Dipesan mandiri oleh Member**: saat checkout di app/web, jika member terdaftar aktif di sebuah sponsor dengan sisa kuota mencukupi, muncul opsi tambahan "Bayar dengan Kuota Sponsor [Nama Perusahaan]" di samping Midtrans/Cash.

Method baru `bookWithSponsorQuota()` (trait terpisah, bukan menyisipkan logic ke `checkout()` lama):
```php
DB::transaction(function () use ($member, $organization, $hours) {
    $locked = SponsorOrganizationMember::where('id', $memberRecord->id)->lockForUpdate()->first();
    // Tentukan sumber kuota: jatah pribadi jika ada, kalau tidak shared pool
    if ($locked->allocated_hours !== null) {
        if (($locked->allocated_hours - $locked->hours_used) < $hours) {
            throw new HttpException(422, 'Jatah jam pribadi tidak mencukupi.');
        }
        $locked->increment('hours_used', $hours);
    } else {
        $org = SponsorOrganization::where('id', $organization->id)->lockForUpdate()->first();
        if ($org->hours_remaining < $hours) {
            throw new HttpException(422, 'Kuota jam sponsor tidak mencukupi.');
        }
        $org->decrement('hours_remaining', $hours);
    }
    // Baru setelah kuota terkunci & valid, panggil holdBatchSlots() + checkout() yang SUDAH ADA
});
```
- Jika `holdBatchSlots()` gagal (slot bentrok, `SlotConflictException`), kuota yang sudah dipotong di atas **wajib dikembalikan (rollback)** — seluruh urutan ini dibungkus satu `DB::transaction` agar atomic penuh (kuota & slot sukses bersama, atau gagal bersama).

### FR-06: Pelaporan Konsumsi (Filament, Staf Venue)
- Halaman Filament "Kelola Sponsor Korporat" menampilkan riwayat pemakaian per organisasi: siapa yang booking, kapan, berapa jam terpakai, sisa kuota real-time — berguna untuk laporan tagihan/evaluasi renewal kontrak ke klien korporat.

---

## 5. Kebutuhan Non-Fungsional

1. **Isolasi dari RBAC Venue**: `SponsorOrganizationPolicy` sama sekali tidak menyentuh Spatie Role/Filament Shield. Admin Sponsor tidak pernah mendapat akses `/admin`.
2. **Atomic Quota Ledger**: setiap pemotongan kuota (baik shared pool maupun jatah pribadi) wajib `lockForUpdate()` di dalam `DB::transaction`, mencegah race condition saat beberapa member booking bersamaan mendekati sisa kuota nol.
3. **Zero Regression ke Checkout Engine**: `checkout()`, `holdBatchSlots()`, `PaymentOrchestratorService` **tidak diubah sama sekali** — Modul 12 murni menambah jalur baru di atasnya (metode bayar baru + method baru), konsisten dengan prinsip yang dipegang sepanjang proyek ini.
4. **Auditability**: setiap booking yang dibiayai sponsor tercatat `sponsor_organization_id` di `padel_bookings`, dan `Payment` tetap dibuat (amount 0) demi konsistensi laporan — bukan "booking gratis tanpa jejak".

---

## 6. Rencana Pengujian

1. `test_admin_venue_creates_sponsor_organization_with_initial_quota`: staf mencatat sponsor baru, `hours_remaining = total_hours_purchased`.
2. `test_sponsor_admin_can_invite_existing_and_new_member`: undang by phone — nomor existing langsung tertaut, nomor baru dapat link undangan.
3. `test_member_without_allocation_uses_shared_pool`: booking memotong `hours_remaining` organisasi.
4. `test_member_with_allocated_hours_uses_personal_quota_not_shared_pool`: booking memotong `allocated_hours` member, tidak menyentuh shared pool.
5. `test_booking_rejected_when_quota_insufficient`: kuota kurang dari kebutuhan jam → ditolak sebelum slot sempat di-hold.
6. `test_quota_deduction_rolls_back_when_slot_conflict_occurs`: kuota yang sudah dipotong dikembalikan penuh jika `holdBatchSlots()` gagal karena bentrok.
7. `test_concurrent_bookings_near_zero_quota_do_not_oversell`: dua booking bersamaan mendekati sisa kuota terakhir, hanya satu yang berhasil (uji `lockForUpdate`).
8. `test_revoked_member_cannot_use_sponsor_quota`: member yang di-`REVOKED` ditolak saat mencoba pakai kuota.
9. `test_sponsor_admin_has_no_filament_admin_access`: memastikan akun Admin Sponsor tidak bisa mengakses `/admin` sama sekali.
10. Jalankan `php artisan test` penuh — seluruh test suite lama wajib tetap 100% hijau (zero regression terhadap Modul 02/10/11).

---

## 7. Ruang Lingkup & Batasan (Out of Scope v1)

- Pembelian/top-up kuota tambahan secara online otomatis (self-checkout korporat) — v1 tetap manual dicatat staf.
- Multi-admin per organisasi sponsor (saat ini 1 organisasi = 1 Admin Sponsor).
- Kuota lintas-layanan (saat ini hanya berlaku untuk booking Padel; perluasan ke Gym/Wellness/Salon menyusul setelah modul-modul tersebut aktif, mengikuti pola `item_type` yang sudah dirancang di Modul 10).
- Notifikasi otomatis (email/WhatsApp) saat kuota menipis — dicatat sebagai catatan pengembangan lanjutan, bukan blocker v1.
