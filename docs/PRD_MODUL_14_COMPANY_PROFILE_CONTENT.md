# Product Requirements Document (PRD)
## Modul 14: Company Profile (Compro) — Konten Landing Page yang Bisa Diedit Staf
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-14-COMPANY-PROFILE-CONTENT` |
| **Versi** | `v1.0.0-DRAFT` |
| **Status** | Draft — menunggu review PM sebelum implementasi |
| **Sumber Requirement** | Permintaan langsung pemilik produk, dipicu oleh insiden nyata: revisi copy dari PM ("lapangan cuma 3, bukan 4") ternyata harus dikejar manual di **2 file Blade terpisah** (`welcome.blade.php` & `auth/login.blade.php`) yang isinya nyaris identik tapi tidak saling terhubung — bukti konkret bahwa hardcode konten marketing rawan drift. |
| **Dependensi Teknis** | `PRD_MODUL_09_DYNAMIC_RBAC_FILAMENT_SHIELD.md` — otorisasi panel admin modul ini **sepenuhnya** menumpang mekanisme yang sudah ada (`HasPageShield` + `Club61PermissionMatrix`), tidak membuat sistem izin baru. |
| **Target Pengguna** | **Staf pengedit** (Admin/Super Admin, dan kandidat role "Marketing" di masa depan) via panel Filament; **Pengunjung publik** (calon member, siapa saja yang buka `/` atau `/login`) sebagai pembaca konten. |
| **Prinsip Utama** | **SATU SUMBER KEBENARAN KONTEN LINTAS HALAMAN**, **PAKAI ULANG POLA FILAMENT SETTINGS PAGE YANG SUDAH ADA** (meniru `PengaturanBiayaPajak`), **TIDAK BUAT RESOURCE/PANEL/SISTEM IZIN BARU**, **FIELD TERSTRUKTUR (BUKAN PAGE-BUILDER BEBAS)** |

---

## 1. Latar Belakang & Masalah yang Coba Diselesaikan

Saat ini teks & data fasilitas yang tampil di halaman depan publik (`welcome.blade.php`) dan panel kiri halaman login (`auth/login.blade.php`) **di-hardcode terpisah di 2 file**, walau isinya menceritakan hal yang sama: jumlah lapangan, daftar fasilitas (arena, wellness, cafe), status venue, alamat, dsb.

Masalah ini baru saja terbukti nyata: PM meminta jumlah lapangan diperbaiki dari "4" menjadi "3" (fakta fisik venue), tapi angka "4" itu ternyata muncul di **4 titik berbeda** di 2 file yang tidak saling tahu satu sama lain (subtitle hero, kartu fasilitas Arena, badge status venue di halaman login, dan deskripsi lain). Revisi kecil seperti ini butuh developer menyisir manual satu-per-satu dan berisiko ada yang kelewat — persis yang terjadi di sesi ini sebelum ketauan dan dibetulin manual.

Selain itu, bisnis ingin halaman depan (compro) benar-benar menceritakan **apa saja yang Club 61 punya** — bukan cuma 4 kartu generik, tapi representasi fasilitas & program yang sudah dibangun di modul-modul lain (booking padel, membership, sponsor korporat, dst.) — dan **staf non-developer** (marketing/admin) harus bisa mengubah teks/angka ini kapan saja tanpa minta tolong developer & tanpa deploy ulang kode.

### Yang TIDAK Sedang Dibangun (Batasan Sadar Sejak Awal)

Ini **bukan** page-builder bebas gaya WordPress/Webflow yang bisa menyusun halaman apa saja dengan drag-drop. Scope-nya sengaja sempit: **1 narasi konten landing** (hero + daftar fasilitas + info venue), dipakai ulang di 2 tempat tampil (halaman depan & panel login). Field-nya terstruktur & bertipe jelas, bukan blok generik bebas — konsisten dengan prinsip proyek ini untuk tidak membangun abstraksi yang lebih besar dari kebutuhan nyata saat ini.

---

## 2. Solusi Arsitektur

### 2.1 Satu Sumber Data, Dipakai Ulang di Banyak Halaman

Dibuat 1 tabel **singleton** (selalu cuma 1 baris, sama persis pola `ClubFinanceSetting::getSettings()` yang sudah dipakai halaman "Pengaturan Biaya & Pajak") bernama `company_profile_settings`. Baik `welcome.blade.php` maupun panel kiri `auth/login.blade.php` **berhenti hardcode teksnya sendiri-sendiri** — keduanya me-render lewat 1 Blade component bersama:

```
company_profile_settings (1 baris, sumber kebenaran)
        │
        ▼
CompanyProfileSetting::current()   <- accessor singleton, di-cache request-scoped
        │
        ├──> <x-company-profile.hero-panel variant="light" />   dipakai di welcome.blade.php
        └──> <x-company-profile.hero-panel variant="dark" />    dipakai di auth/login.blade.php (panel kiri)
```

`variant` cuma mengatur styling (tema terang vs gelap sesuai desain masing-masing halaman) — **isi teks & angkanya identik**, diambil dari baris yang sama. Begitu staf ubah "jumlah lapangan" jadi 3 di satu tempat, kedua halaman otomatis konsisten — kelas bug yang baru saja terjadi (angka beda-beda di 2 file) **tidak bisa terjadi lagi secara struktural**.

### 2.2 Panel Admin: Filament Page Baru, Meniru Pola yang Sudah Ada (Bukan Resource, Bukan Panel Baru)

Konten ini **bukan** data tabular (tidak ada banyak "baris" untuk di-CRUD, cuma 1 baris pengaturan) — jadi polanya **Filament `Page`**, sama persis seperti `PengaturanBiayaPajak.php` (`app/Filament/Pages/PengaturanBiayaPajak.php`), bukan `Resource`. Class baru: `app/Filament/Pages/KelolaKontenWebsite.php`.

```php
class KelolaKontenWebsite extends Page
{
    use HasPageShield; // <- mekanisme otorisasi, PERSIS sama dengan 13 Page lain yang sudah ada

    protected string $view = 'filament.pages.kelola-konten-website';

    public string $heroBadgeText = '';
    public string $heroHeadlineLine1 = '';
    public string $heroHeadlineHighlight = '';
    public string $heroHeadlineLine2 = '';
    public string $heroSubtitle = '';
    public string $venueStatusLabel = '';
    public int $courtCount = 3;
    public array $facilityCards = [];   // <- Filament Repeater, lihat §2.3
    public string $addressLine = '';
    public string $operatingHoursText = '';
    public string $portalDomainText = '';

    public function mount(): void
    {
        $settings = CompanyProfileSetting::current();
        // ...isi semua public property dari $settings
    }

    public function save(): void
    {
        // ...validasi + persist ke baris singleton + catat updated_by = auth()->id()
    }
}
```

### 2.3 Kartu Fasilitas: Filament Repeater + Ikon dari Daftar Tertutup (Bukan Upload SVG Bebas)

Daftar kartu fasilitas (Arena, Wellness, Cafe, dst.) disimpan sebagai kolom **JSON** (`facility_cards`), diedit lewat Filament `Repeater` form component (tambah/hapus/urutkan kartu bebas, tanpa migration baru tiap ada fasilitas baru). Tiap item repeater:

| Field Repeater | Tipe Input | Keterangan |
| :--- | :--- | :--- |
| `icon_key` | `Select` dari daftar TERTUTUP (`arena`, `wellness`, `cafe`, `gate`, `tournament`, `star`, dst.) | **Sengaja bukan upload SVG/HTML bebas.** Checklist keamanan proyek ini eksplisit menandai SVG sebagai vektor XSS ("SVG diperlakukan kayak HTML") — jadi ikon dipetakan dari key ke path SVG yang sudah di-hardcode developer (`resources/views/components/company-profile/icons.blade.php`), staf cuma pilih dari dropdown, tidak pernah menyuntik markup mentah. |
| `title` | `TextInput`, max 40 karakter | Judul kartu, mis. "Padel Arena". |
| `subtitle` | `TextInput`, max 60 karakter | Sub-teks kartu, mis. "+ Panoramic Courts". |

Urutan array = urutan tampil. Tidak ada batas jumlah kartu keras di level kode, tapi form kasih peringatan lembut (bukan blocking) kalau staf menambah lebih dari 6 kartu (rusak tata letak grid).

### 2.4 Otorisasi — Jawaban ke Pertanyaan "Bedain Role yang Bisa Akses Gimana?"

**Tidak ada mekanisme baru sama sekali** — dipakai ulang persis yang sudah terbukti benar dari audit keamanan sebelumnya:

1. `KelolaKontenWebsite` pakai trait `HasPageShield`, sama seperti 13 Filament Page lain di aplikasi ini (`BookingSystem`, `MasterData`, `PengaturanBiayaPajak`, dst.). Trait ini yang membuat Filament otomatis memanggil `auth()->user()->can('View:KelolaKontenWebsite')` sebelum halaman bisa diakses.
2. Karena proyek ini **tidak** menjalankan `php artisan shield:generate` (slug dikelola manual, lihat `app/Services/Permission/Club61PermissionMatrix.php`), slug `View:KelolaKontenWebsite` **wajib ditambahkan manual** ke matrix — masuk kategori baru "13. Konten Website (Company Profile)".
3. `DatabaseSeeder` menggranting slug ini ke role `super_admin` dan `admin` saja (pola sama seperti akses ke "Pengaturan Biaya & Pajak" dan "Master Data" — konfigurasi venue-wide, bukan operasional harian kasir/dapur). **Tidak** digranting ke `cashier`/`kitchen`.
4. Kalau nanti bisnis mau role khusus "Marketing" yang HANYA bisa edit konten website tapi tidak bisa lihat data booking/keuangan — itu tinggal: buat role baru lewat menu "Roles & Hak Akses" yang sudah ada, centang **cuma** permission `View:KelolaKontenWebsite`. Tidak perlu perubahan kode apapun, karena granularitas per-slug ini sudah jadi fondasi RBAC proyek sejak Modul 09.

Tidak ada isolasi/scoping tambahan yang dibutuhkan (beda dengan Modul 12 Sponsor yang butuh policy ter-scope per organisasi) — ini murni pengaturan venue-wide, jadi cukup satu slug on/off.

---

## 3. Skema Database

### 3.1 Migration: `company_profile_settings` (Singleton)

| Kolom | Tipe | Keterangan |
| :--- | :--- | :--- |
| `id` | unsignedTinyInteger, PK, selalu `1` | Singleton — sama pola dengan `club_finance_settings`. Dijaga di level Model (`firstOrCreate(['id' => 1], [...default...])`), bukan constraint DB eksplisit. |
| `hero_badge_text` | string(150) | Mis. "Medan Flagship Venue • Gedung Indosat • Open Daily". |
| `hero_headline_line1` | string(80) | Mis. "The Sanctuary for". |
| `hero_headline_highlight` | string(80) | Mis. "Padel Athletes" (bagian yang ditampilkan italic/gold). |
| `hero_headline_line2` | string(80) | Mis. "in Medan." |
| `hero_subtitle` | text | Deskripsi fasilitas, boleh sisipkan placeholder `{court_count}` yang otomatis diganti nilai `court_count` saat render (lihat §4 FR-03) — supaya angka lapangan tidak perlu diketik ulang manual di kalimat ini. |
| `venue_status_label` | string(40) | Mis. "VENUE LIVE". |
| `court_count` | unsignedTinyInteger | **Satu-satunya** sumber angka jumlah lapangan — dipakai di badge "X COURTS OPEN" (login) dan subtitle/kartu Arena (welcome). Akar masalah "4 vs 3 tersebar" tidak bisa terjadi lagi karena hanya ada 1 kolom ini. |
| `facility_cards` | json | Array `{icon_key, title, subtitle}`, urutan = urutan tampil. Lihat §2.3. |
| `address_line` | string(200) | Alamat venue untuk footer. |
| `operating_hours_text` | string(60) | Mis. "Open 06:00 – 23:00". |
| `portal_domain_text` | string(60) | Mis. "portal.club61padel.com". |
| `updated_by` | ULID, FK → `users.id`, nullable | Audit siapa terakhir mengubah. |
| `timestamps` | | |

> Tidak ada tabel anak (`company_profile_section_items` dsb.) — `facility_cards` cukup sebagai kolom JSON karena jumlah item kecil (≤6) dan tidak butuh query/relasi per-item.

---

## 4. Kebutuhan Fungsional (Functional Requirements)

### FR-01: Panel Edit Konten (Filament Page, Staf Berizin)
Staf dengan permission `View:KelolaKontenWebsite` membuka menu "Konten Website" di Filament, melihat form 1 halaman (bukan list/CRUD) berisi semua field §3.1, termasuk Repeater kartu fasilitas. Simpan → validasi → persist ke baris singleton → `Notification::success()`.

### FR-02: Render Publik dari Sumber Tunggal
`welcome.blade.php` dan panel kiri `auth/login.blade.php` **tidak lagi punya teks hardcode sendiri** untuk bagian hero/fasilitas/status venue — keduanya memanggil `<x-company-profile.hero-panel :settings="\App\Models\Setting\CompanyProfileSetting::current()" variant="..." />`. Styling (warna, ukuran, tema gelap/terang) tetap beda sesuai desain masing-masing halaman lewat prop `variant`, tapi sumber teks & angkanya satu.

### FR-03: Interpolasi `{court_count}` di Subtitle
Saat render, `hero_subtitle` melalui `str_replace('{court_count}', $settings->court_count, ...)` sebelum ditampilkan — supaya staf yang mengedit teks bebas tidak perlu ingat mengetik ulang angka lapangan secara manual di tengah kalimat; cukup ubah `court_count`, kalimat ikut menyesuaikan otomatis di mana pun placeholder itu dipasang.

### FR-04: Fallback Aman Saat Baris Belum Ada
`CompanyProfileSetting::current()` memakai `firstOrCreate(['id' => 1], [...default berisi teks & angka yang SEKARANG live di production...])` — migration awal langsung mengisi baris ini dengan konten yang sudah benar (3 lapangan, dst.), supaya deploy modul ini **tidak pernah** menyebabkan halaman publik tiba-tiba kosong/error.

### FR-05: Preview Sebelum Simpan (Nice-to-Have, Bukan Blocker v1)
Tombol "Lihat Preview" yang membuka `welcome.blade.php` di tab baru memakai data form saat ini (belum disimpan) via query string sementara/session — supaya staf bisa cek tampilan sebelum publish. Ditandai opsional; boleh dikerjakan belakangan kalau waktu memungkinkan.

---

## 5. Kebutuhan Non-Fungsional

1. **Tidak Ada Sistem Izin Baru**: seluruh otorisasi memakai `HasPageShield` + `Club61PermissionMatrix` yang sudah ada — nol penambahan konsep RBAC baru (lihat §2.4).
2. **Tidak Ada Resource/Panel Baru**: menu ini adalah 1 Filament `Page` tunggal di panel admin yang sudah ada, bukan panel/subdomain terpisah.
3. **Anti-XSS pada Ikon**: ikon kartu fasilitas dipilih dari daftar tertutup (`Select`), tidak pernah menerima markup SVG/HTML mentah dari staf — konsisten dengan checklist keamanan proyek ini soal SVG.
4. **Konsistensi Lintas Halaman Terjamin Struktural**: karena render publik cuma boleh membaca dari 1 accessor singleton (§2.1), tidak mungkin lagi ada 2 halaman menampilkan angka/teks berbeda untuk fakta yang sama — kelas bug pemicu PRD ini tertutup oleh desain, bukan oleh disiplin manual developer.
5. **Audit Trail Minimal**: `updated_by` + `updated_at` cukup untuk tahu siapa & kapan terakhir ubah konten publik — tidak perlu versioning/rollback penuh di v1 (lihat §7 out of scope).
6. **Zero Regression ke Halaman yang Sudah Ada**: refactor `welcome.blade.php`/`auth/login.blade.php` ke component bersama tidak boleh mengubah struktur/gaya visual di luar bagian teks yang memang dipindah ke database — styling, layout, gambar background tetap seperti sekarang.

---

## 6. Rencana Pengujian

1. `test_company_profile_setting_singleton_always_returns_same_row`: panggil `current()` berkali-kali, pastikan tidak pernah membuat baris kedua.
2. `test_staff_without_permission_cannot_access_kelola_konten_website`: role tanpa `View:KelolaKontenWebsite` (mis. cashier) ditolak, sesuai pola `SponsorAndMembershipResourcePermissionTest` yang sudah ada.
3. `test_admin_role_can_view_and_update_company_profile_content`: admin bisa buka halaman & submit perubahan, baris singleton ter-update, `updated_by` terisi user yang benar.
4. `test_welcome_page_renders_court_count_from_database_not_hardcoded`: ubah `court_count` jadi angka lain di DB, assert teks itu muncul di response `GET /` — membuktikan halaman publik benar-benar baca dari DB, bukan hardcode sisa.
5. `test_login_page_and_welcome_page_show_identical_court_count_and_facility_cards`: assert kedua halaman menampilkan angka & daftar fasilitas yang SAMA persis dari 1 baris data — test regresi khusus untuk bug yang memicu PRD ini.
6. `test_facility_card_icon_key_rejects_value_outside_closed_list`: submit `icon_key` di luar daftar yang diizinkan → validasi gagal, tidak tersimpan.
7. `test_hero_subtitle_court_count_placeholder_interpolates_correctly`: subtitle berisi `{court_count}` ter-render jadi angka asli saat halaman publik dibuka.
8. Jalankan `php artisan test` penuh — suite lama wajib tetap 100% hijau (zero regression, sama seperti standar semua modul lain di proyek ini).

---

## 7. Ruang Lingkup & Batasan (Out of Scope v1)

- **Page-builder bebas / multi-halaman arbitrer** — v1 cuma 1 narasi konten (hero + fasilitas + info venue), dipakai ulang di 2 titik tampil yang sudah ada. Bukan alat bikin halaman baru sembarang.
- **Upload gambar** (foto hero, logo, dsb.) — tetap pakai asset statis di `public/images` untuk v1. Upload gambar butuh validasi MIME/ukuran/re-encode sesuai checklist keamanan yang baru saja diaudit; dijadwalkan v2 kalau memang dibutuhkan, supaya tidak terburu-buru menambah attack surface baru.
- **Draft/Publish workflow & version history/rollback** — v1 langsung live begitu staf klik Simpan (sama seperti pola "Pengaturan Biaya & Pajak" yang sudah ada). Tidak ada draft terpisah atau riwayat versi untuk di-rollback.
- **SEO meta tag editor & multi-bahasa** — di luar cakupan v1.
- **Role "Marketing" khusus** — tidak dibuat di v1 (lihat §2.4 poin 4, cukup dicatat sebagai jalur lanjutan yang sudah didukung fondasinya, tinggal dibuat rolenya kapan pun dibutuhkan tanpa perubahan kode).
