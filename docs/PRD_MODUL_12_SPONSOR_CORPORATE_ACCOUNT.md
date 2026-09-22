# Product Requirements Document (PRD)
## Modul 12: Akun Sponsor Korporat & Kuota Jam Tim ("Sponsor Team Quota")
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-12-SPONSOR-CORPORATE-ACCOUNT` |
| **Versi** | `v2.0.0-DRAFT` — **revisi arsitektur: kuota sponsor tidak lagi ledger terpisah, lihat §2.4** |
| **Status** | **Proposed — Menunggu Persetujuan Implementasi** |
| **Sumber Requirement** | Permintaan langsung Head IT (Pak Sadrakh) melalui diskusi chat internal |
| **Dependensi Teknis** | **`PRD_MODUL_05_MEMBERSHIP_SYSTEM.md` (v2) — modul ini dibangun DI ATAS infrastruktur Membership, bukan berdiri sendiri. Wajib dibaca §6 & §8 PRD Modul 05 sebelum implementasi.** |
| **Target Pengguna** | Perusahaan/Instansi Sponsor (B2B), Admin Sponsor (penanggung jawab kuota), Member Karyawan Sponsor |
| **Prinsip Utama** | **SCOPED AUTHORIZATION (BUKAN VENUE-WIDE RBAC), SATU SUMBER KUOTA REAL (MEMBERSHIP BALANCE, BUKAN LEDGER DUPLIKAT), ZERO TOUCH KE CHECKOUT ENGINE STABIL** |

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

### 2.1 Entitas Baru: `SponsorOrganization` & `SponsorMember` (Wrapper di Atas Membership, Bukan Ledger Sendiri)

> **Perubahan dari v1**: Sponsor **bukan** sistem kuota independen. Membeli paket blok jam korporat = membeli 1 `user_memberships` dengan `ownership_type = ORGANIZATIONAL` (lihat PRD Modul 05 §6, §8). `sponsor_organizations` dan `sponsor_organization_members` di bawah ini adalah **lapisan wrapper & alokasi**, bukan tempat menyimpan angka jam yang sesungguhnya.

```
                    +---------------------------------+
                    |         user_memberships          |   <- dari Modul 05
                    |  ownership_type = ORGANIZATIONAL  |
                    +----------------+-------------------+
                                     | 1:1
                                     v
                    +---------------------------------+
                    |      sponsor_organizations         |
                    |  (wrapper metadata, BUKAN ledger)  |
                    |  - user_membership_id (FK)          |
                    |  - name, sponsor_admin_user_id      |
                    +----------------+-------------------+
                                     | hasMany
                                     v
                    +---------------------------------+
                    |   sponsor_organization_members     |
                    |  (lapisan ALOKASI/OTORISASI,        |
                    |   bukan ledger kedua)               |
                    |  - user_id (member)                  |
                    |  - allocated_hours (plafon, nullable)|
                    |  - hours_used (sub-ledger tampilan)  |
                    +---------------------------------+
                                     |
                                     v  setiap pemakaian nyata tetap menulis ke:
                    +---------------------------------+
                    |   user_membership_balances          |   <- SATU-SATUNYA
                    |   remaining_quota (facility=PADEL)   |      sumber kebenaran
                    +---------------------------------+
```

- **`sponsor_organizations`**: satu baris = satu perusahaan/kontrak sponsor, menyimpan **referensi** (`user_membership_id`) ke membership organisasional yang dibeli — **tidak** menyimpan `total_hours_purchased`/`hours_remaining` sendiri. Angka-angka itu dibaca langsung dari `user_membership_balances` milik membership tersebut.
- **`sponsor_organization_members`**: menghubungkan `User` (karyawan) ke organisasi sponsor. Kolom `allocated_hours` **opsional**, berfungsi sebagai **plafon otorisasi**, bukan pool jam terpisah:
  - Jika `null` → member menggunakan **shared pool** langsung (validasi terhadap `remaining_quota` milik membership induk).
  - Jika diisi angka → member punya **jatah pribadi** (plafon) — validasi ganda: (a) `allocated_hours - hours_used >= jam_dibutuhkan`, DAN (b) `remaining_quota` membership induk masih cukup (defensif, seharusnya selalu benar jika invarian alokasi di §2.4 ditegakkan, tapi tetap diperiksa karena ini menyangkut jam nyata).
  - Pemotongan yang **sah secara finansial** selalu terjadi di `user_membership_balances.remaining_quota` lewat `MembershipBalanceService::adjustQuota()` (Modul 05) — `hours_used` di tabel ini murni sub-ledger tampilan/pelaporan per-karyawan, tidak pernah menjadi sumber kebenaran independen.

### 2.4 Invarian Alokasi (Mencegah Over-Allocation)
Saat Admin Sponsor mengisi/menambah `allocated_hours` seorang member, sistem **wajib** memvalidasi:
$$\sum(\text{allocated\_hours seluruh member yang tidak NULL}) \le \text{remaining\_quota membership induk saat itu}$$
Tanpa validasi ini, Admin Sponsor bisa "menjual" plafon jam ke banyak karyawan yang total-nya melebihi jam yang sebenarnya tersisa — member akan gagal booking di detik terakhir meski menurut catatan plafonnya seharusnya masih punya jatah. Validasi ini dijalankan di `SponsorAllocationService`, bukan diasumsikan benar oleh UI.

### 2.2 Otorisasi Scoped, Terpisah dari Filament Shield

Dibuat `SponsorOrganizationPolicy` yang **tidak bergantung pada Spatie Role** sama sekali. Aturan dasarnya sederhana dan eksplisit:
```php
public function manage(User $user, SponsorOrganization $org): bool
{
    return $org->sponsor_admin_user_id === $user->id;
}
```
Admin Sponsor tetap ber-`role = 'customer'` di sistem — dia **tidak** mendapat akses Filament `/admin` sama sekali. Seluruh interaksi Admin Sponsor (undang member, alokasi jam, buatkan jadwal) terjadi melalui **portal customer** yang sudah ada (`resources/views/customer/...`), bukan panel staf.

### 2.3 Kuota Sebagai "Metode Bayar" Baru (Zero Touch ke Checkout Engine, Mendarat di Membership Balance)

Alih-alih mengubah alur `checkout()`/`ManagesCheckoutAndPayments.php` yang sudah stabil dan teruji, kuota sponsor diperlakukan sebagai **metode pembayaran baru**: `SPONSOR_QUOTA`, sejajar dengan `CASH`, `QRIS`, `BANK_TRANSFER` yang sudah ada. Titik sentuhnya **hanya** menambahkan satu cabang kondisi baru sebelum proses hold-slot, bukan mengubah logic inti yang sudah ada:

```
Booking dengan payment_method = 'SPONSOR_QUOTA'
        │
        ▼
1. Cari user_membership_balances (facility=PADEL) milik membership organisasional sponsor ini
2. Validasi plafon allocated_hours member (jika ada) SECARA ATOMIC (lockForUpdate)
3. Validasi & kunci remaining_quota membership induk SECARA ATOMIC (lockForUpdate) SEBELUM hold slot
4. Jika kuota cukup: panggil MembershipBalanceService::adjustQuota() (Modul 05) — SATU-SATUNYA
   jalur yang benar-benar mendekremen remaining_quota — lalu update hours_used member (sub-ledger),
   lanjut holdBatchSlots() -> checkout() seperti biasa
5. Order langsung berstatus PAID (tidak ada uang riil, "pembayaran" = debit kuota)
6. Payment dicatat dengan payment_gateway = 'SPONSOR_QUOTA', amount = 0
7. Jika kuota tidak cukup: tolak dengan pesan jelas sebelum slot sempat di-hold
```

Ini mengikuti pola yang sama persis dengan perbaikan atomic-decrement pada `Voucher.quota` di Modul 10 — dikunci dan diperiksa di satu operasi database, bukan baca-lalu-tulis terpisah yang rawan race condition saat banyak member booking bersamaan. **Perbedaan dari v1**: langkah 4 tidak lagi mendekremen kolom lokal `sponsor_organizations.hours_remaining` — ia memanggil service terpusat milik Modul 05 pada baris `user_membership_balances` yang sama, memastikan hanya ada 1 angka kuota yang pernah dianggap benar di seluruh sistem.

---

## 3. Skema Database

### 3.1 Migration: `sponsor_organizations` (Wrapper — Bukan Ledger)
| Kolom | Tipe | Keterangan |
| :--- | :--- | :--- |
| `id` | ULID, PK | Primary key. |
| `user_membership_id` | ULID, FK → `user_memberships.id` (Modul 05), unique | Membership organisasional yang dibeli — **satu-satunya** sumber `total_hours_purchased`/`remaining_quota` (dibaca via relasi, bukan kolom duplikat di tabel ini). |
| `name` | string(150) | Nama perusahaan/instansi sponsor. |
| `sponsor_admin_user_id` | ULID, FK → `users.id` | Penanggung jawab kuota (bukan role staf venue) — sama dengan `user_memberships.user_id` untuk membership ini. |
| `notes` | text, nullable | Catatan internal staf (nomor kontrak, PIC, dll). |
| `created_by` | ULID, FK → `users.id` | Staf/admin venue yang mencatat pembelian awal. |
| `timestamps`, `softDeletes` | | |

> Kolom `total_hours_purchased`, `hours_remaining`, `status`, `valid_until` yang ada di v1 **dihapus** — masing-masing sudah terwakili oleh `user_membership_balances.initial_quota`/`remaining_quota`, `user_memberships.status`, dan `user_memberships.end_date` milik membership terkait.

### 3.2 Migration: `sponsor_organization_members` (Lapisan Alokasi — Sub-Ledger Tampilan, Bukan Sumber Kebenaran)
| Kolom | Tipe | Keterangan |
| :--- | :--- | :--- |
| `id` | ULID, PK | Primary key. |
| `sponsor_organization_id` | ULID, FK → `sponsor_organizations.id` | Organisasi induk. |
| `user_id` | ULID, FK → `users.id` | Member (karyawan) yang diundang. |
| `allocated_hours` | decimal(8,2), nullable | Plafon otorisasi pribadi; `null` = pakai shared pool organisasi. **Validasi invarian §2.4 wajib dijalankan setiap kali kolom ini diisi/diubah.** |
| `hours_used` | decimal(8,2), default 0 | Akumulasi jam yang sudah dipakai member ini — **sub-ledger tampilan/pelaporan**, selalu ditulis bersamaan (dalam transaksi yang sama) dengan dekremen `user_membership_balances.remaining_quota`, tidak pernah berdiri sendiri sebagai sumber kebenaran. |
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

### FR-01: Pembuatan Organisasi Sponsor = Penjualan Membership Organisasional (Staf Venue)
- Pembelian blok jam korporat (misal 200 jam) adalah **transaksi B2B yang dinegosiasikan manual** (kontrak, invoice, transfer bank) — **bukan** melalui checkout online otomatis, dan **bukan** insert langsung ke tabel sponsor seperti v1.
- Staf (`admin`/`super_admin`) mencatat kesepakatan ini lewat halaman Filament **"Kelola Sponsor Korporat"**, yang di baliknya menjalankan alur penjualan membership Modul 05 dengan `membership_plans.ownership_type = ORGANIZATIONAL`: pilih/buat plan blok jam (misal "Sponsor 200 Jam/Tahun"), tentukan harga kontrak, lalu tunjuk 1 User existing (atau buat User baru) sebagai pemilik nominal (`user_memberships.user_id`).
- Hasil dari langkah ini: 1 baris `user_memberships` (`owner_type = ORGANIZATIONAL`, `status = ACTIVE`) + 1 baris `user_membership_balances` (`facility = PADEL`, `quota_type = HOURS`, `initial_quota = remaining_quota = 200`). Staf **kemudian** mengisi form pendek "Detail Sponsor" (nama perusahaan, PIC, notes) yang menyimpan baris `sponsor_organizations` dengan `user_membership_id` menunjuk ke membership yang baru dibuat.

### FR-02: Portal Admin Sponsor (Customer-Facing, Bukan Filament)
- Admin Sponsor login sebagai customer biasa, mendapat 1 menu tambahan "Kelola Tim Sponsor" di portal customer (hanya muncul jika `sponsor_admin_user_id` miliknya ada di tabel `sponsor_organizations`).
- Menampilkan: sisa kuota (dibaca dari `user_membership_balances.remaining_quota` / `initial_quota` milik membership terkait — **bukan** kolom lokal), daftar member, dan riwayat konsumsi jam (`membership_usage_logs` yang `related` ke booking-booking sponsor ini).

### FR-03: Undang & Kelola Member
- Admin Sponsor memasukkan email/nomor telepon calon member.
- Sistem melakukan **pengecekan & normalisasi nomor telepon** dengan pola yang sama seperti `findOrCreateWalkInCustomer()` (Modul 11) — jika User dengan nomor/email tersebut sudah ada, tautkan langsung; jika belum, kirim undangan (link registrasi) yang begitu diklaim otomatis menautkan ke `sponsor_organization_members`.
- Admin Sponsor dapat mencabut (`REVOKED`) member kapan saja — member yang dicabut tidak bisa lagi memakai kuota organisasi tersebut, namun riwayat booking lamanya tetap utuh (bukan dihapus).

### FR-04: Alokasi Jatah Jam per Member (Opsional)
- Default: member baru tidak punya `allocated_hours` (`null`) → otomatis memakai **shared pool** organisasi (validasi langsung ke `remaining_quota` membership induk).
- Admin Sponsor dapat mengatur `allocated_hours` per member (misal 10 jam per orang) — begitu diisi, member tersebut **hanya** bisa memotong dari jatah pribadinya (`allocated_hours - hours_used`), bukan langsung dari pool bersama, mencegah 1 member menghabiskan seluruh kuota organisasi tanpa sepengetahuan Admin Sponsor.
- **Setiap kali field ini diisi/diubah, invarian §2.4 wajib divalidasi**: total `allocated_hours` seluruh member (yang tidak `null`) tidak boleh melebihi `remaining_quota` membership induk saat itu. Request yang melanggar ini ditolak sebelum tersimpan.

### FR-05: Booking Menggunakan Kuota Sponsor
Dua jalur booking yang sama-sama berujung pada logic pemotongan kuota yang identik:

1. **Dipesankan oleh Admin Sponsor** (mirip walk-in tapi self-service, tanpa staf): Admin Sponsor pilih member penerima, pilih slot, pilih `payment_method = SPONSOR_QUOTA`.
2. **Dipesan mandiri oleh Member**: saat checkout di app/web, jika member terdaftar aktif di sebuah sponsor dengan sisa kuota mencukupi, muncul opsi tambahan "Bayar dengan Kuota Sponsor [Nama Perusahaan]" di samping Midtrans/Cash.

Method baru `bookWithSponsorQuota()` (trait terpisah, bukan menyisipkan logic ke `checkout()` lama) — **mendekremen kuota lewat service Modul 05, bukan kolom lokal**:
```php
DB::transaction(function () use ($member, $organization, $hours, $bookingId, $actingUserId) {
    $locked = SponsorOrganizationMember::where('id', $member->id)->lockForUpdate()->first();

    // Cari SATU-SATUNYA baris kuota real milik membership organisasi ini
    $balance = UserMembershipBalance::where('user_membership_id', $organization->user_membership_id)
        ->where('facility', 'PADEL')
        ->lockForUpdate()
        ->first();

    // Tentukan plafon: jatah pribadi jika ada, kalau tidak shared pool langsung ke $balance
    if ($locked->allocated_hours !== null) {
        if (($locked->allocated_hours - $locked->hours_used) < $hours) {
            throw new HttpException(422, 'Jatah jam pribadi tidak mencukupi.');
        }
    }
    if ($balance->remaining_quota < $hours) {
        throw new HttpException(422, 'Kuota jam sponsor tidak mencukupi.');
    }

    // SATU-SATUNYA jalur yang boleh mendekremen remaining_quota (Modul 05 §5.2)
    app(MembershipBalanceService::class)->adjustQuota(
        balance: $balance,
        changeType: 'DECREMENT',
        quantity: $hours,
        relatedType: PadelBooking::class,
        relatedId: $bookingId,
        performedBy: $actingUserId,
        notes: "Sponsor quota — dipakai oleh member {$member->user_id}",
    );

    if ($locked->allocated_hours !== null) {
        $locked->increment('hours_used', $hours); // sub-ledger tampilan, bukan sumber kebenaran
    }
    // Baru setelah kuota terkunci & valid, panggil holdBatchSlots() + checkout() yang SUDAH ADA
});
```
- Jika `holdBatchSlots()` gagal (slot bentrok, `SlotConflictException`), kuota yang sudah dipotong di atas **wajib dikembalikan (rollback)** — seluruh urutan ini dibungkus satu `DB::transaction` agar atomic penuh (kuota & slot sukses bersama, atau gagal bersama).
- **Guard dua arah** (lihat PRD Modul 05 FR-03 poin 6): saat `payment_method = SPONSOR_QUOTA`, jalur konsumsi membership pribadi milik member (jika ia juga punya membership individual sendiri) **dilewati sepenuhnya** — booking ini hanya boleh mendekremen SATU balance, milik sponsor, tidak dua-duanya.

### FR-06: Pelaporan Konsumsi (Filament, Staf Venue)
- Halaman Filament "Kelola Sponsor Korporat" menampilkan riwayat pemakaian per organisasi: siapa yang booking, kapan, berapa jam terpakai, sisa kuota real-time — berguna untuk laporan tagihan/evaluasi renewal kontrak ke klien korporat.

---

## 5. Kebutuhan Non-Fungsional

1. **Isolasi dari RBAC Venue**: `SponsorOrganizationPolicy` sama sekali tidak menyentuh Spatie Role/Filament Shield. Admin Sponsor tidak pernah mendapat akses `/admin`.
2. **Atomic Quota Ledger, Satu Sumber Kebenaran**: setiap pemotongan kuota (baik shared pool maupun jatah pribadi) wajib `lockForUpdate()` di dalam `DB::transaction` DAN wajib melalui `MembershipBalanceService::adjustQuota()` milik Modul 05 — tidak ada mutasi langsung ke `hours_remaining` lokal karena kolom itu sudah tidak ada (lihat §3.1).
3. **Zero Regression ke Checkout Engine**: `checkout()`, `holdBatchSlots()`, `PaymentOrchestratorService` **tidak diubah sama sekali** — Modul 12 murni menambah jalur baru di atasnya (metode bayar baru + method baru), konsisten dengan prinsip yang dipegang sepanjang proyek ini.
4. **Auditability**: setiap booking yang dibiayai sponsor tercatat `sponsor_organization_id` di `padel_bookings`, dan `Payment` tetap dibuat (amount 0) demi konsistensi laporan — bukan "booking gratis tanpa jejak". Mutasi kuotanya sendiri tercatat di `membership_usage_logs` (Modul 05), bukan log terpisah.
5. **Tidak Ada Over-Allocation**: invarian §2.4 (total `allocated_hours` ≤ `remaining_quota` induk) ditegakkan di service, bukan diasumsikan benar oleh form admin.

---

## 6. Rencana Pengujian

1. `test_staff_creates_sponsor_by_purchasing_organizational_membership_plan`: staf mencatat sponsor baru lewat alur Modul 05 (`ownership_type=ORGANIZATIONAL`), `sponsor_organizations.user_membership_id` terisi benar, `remaining_quota` awal = jam yang dibeli.
2. `test_sponsor_admin_can_invite_existing_and_new_member`: undang by phone — nomor existing langsung tertaut, nomor baru dapat link undangan.
3. `test_member_without_allocation_uses_shared_pool`: booking memotong `remaining_quota` milik `user_membership_balances` induk lewat `adjustQuota()`.
4. `test_member_with_allocated_hours_uses_personal_ceiling_but_still_debits_parent_balance`: booking memotong `allocated_hours`/`hours_used` member SEKALIGUS `remaining_quota` induk (dua-duanya, bukan salah satu) — membuktikan tidak ada dual-ledger yang bisa drift.
5. `test_booking_rejected_when_quota_insufficient`: kuota kurang dari kebutuhan jam → ditolak sebelum slot sempat di-hold.
6. `test_quota_deduction_rolls_back_when_slot_conflict_occurs`: kuota yang sudah dipotong (di `user_membership_balances`, via reversal log) dikembalikan penuh jika `holdBatchSlots()` gagal karena bentrok.
7. `test_concurrent_bookings_near_zero_quota_do_not_oversell`: dua booking bersamaan mendekati sisa kuota terakhir, hanya satu yang berhasil (uji `lockForUpdate` pada baris `user_membership_balances`).
8. `test_revoked_member_cannot_use_sponsor_quota`: member yang di-`REVOKED` ditolak saat mencoba pakai kuota.
9. `test_sponsor_admin_has_no_filament_admin_access`: memastikan akun Admin Sponsor tidak bisa mengakses `/admin` sama sekali.
10. `test_allocating_hours_exceeding_parent_remaining_quota_is_rejected`: invarian §2.4 — total alokasi tidak boleh melebihi sisa kuota induk.
11. `test_member_who_also_has_personal_membership_only_debits_sponsor_balance_when_paying_with_sponsor_quota`: guard dua arah dengan Modul 05 FR-03 poin 6.
12. Jalankan `php artisan test` penuh — seluruh test suite lama wajib tetap 100% hijau (zero regression terhadap Modul 02/05/10/11).

---

## 7. Ruang Lingkup & Batasan (Out of Scope v1)

- Pembelian/top-up kuota tambahan secara online otomatis (self-checkout korporat) — v1 tetap manual dicatat staf lewat halaman "Kelola Sponsor Korporat" (yang secara internal memakai alur pembelian membership Modul 05).
- Multi-admin per organisasi sponsor (saat ini 1 organisasi = 1 Admin Sponsor).
- Kuota lintas-layanan (saat ini hanya berlaku untuk booking Padel; perluasan ke Gym/Sauna menyusul setelah benefit matrix Modul 05 untuk fasilitas tersebut matang, mengikuti pola `facility` yang sama).
- Notifikasi otomatis (email/WhatsApp) saat kuota menipis — dicatat sebagai catatan pengembangan lanjutan, bukan blocker v1.
- Top-up/penambahan jam ke membership organisasional yang sudah `EXPIRED` — mengikuti aturan perpanjangan vs upgrade yang sama seperti membership individual (lihat PRD Modul 05).

**Urutan Implementasi**: modul ini **wajib** dibangun setelah skema inti Modul 05 (`membership_plans`, `membership_plan_benefits`, `user_memberships`, `user_membership_balances`, `MembershipBalanceService::adjustQuota()`) selesai dan lolos test — karena Modul 12 murni lapisan wrapper/alokasi di atasnya, tidak punya ledger sendiri untuk berdiri independen.
