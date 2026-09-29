# Product Requirements Document (PRD)
## Modul 15: Kelola Menu F&B & Tambahan (Modifier) — Panel Admin
**Club 61 Sports & Social Club Central System**

---

| Metadata Dokumen | Spesifikasi |
| :--- | :--- |
| **Kode Dokumen** | `PRD-MODUL-15-FNB-MENU-MANAGEMENT` |
| **Versi** | `v1.0.0-DRAFT` |
| **Status** | Draft — menunggu review PM sebelum implementasi |
| **Sumber Requirement** | Permintaan langsung pemilik produk saat melihat layar Kasir POS (`/pos`): "gua butuh Modul admin untuk masukin menu makanan minuman dan additional, kesannya kaya POS pada umumnya." Investigasi lapangan (lihat §1) membuktikan layar `/pos` yang selama ini terlihat berfungsi ternyata **100% HTML/JS statis** — nama menu, harga, dan kategori diketik langsung di Blade, tidak pernah dibaca dari database. |
| **Dependensi Teknis** | `PRD_MODUL_09_DYNAMIC_RBAC_FILAMENT_SHIELD.md` — otorisasi Resource baru menumpang slug permission yang **sudah ada** di `Club61PermissionMatrix.php` (`view_fnb_menu`, `manage_fnb_menu`), tidak membuat mekanisme izin baru. |
| **Target Pengguna** | **Staf F&B/Admin** (role `admin`/`super_admin`, kandidat role "F&B Manager" di masa depan) via panel Filament. |
| **Prinsip Utama** | **PAKAI ULANG TABEL YANG SUDAH ADA (JANGAN BIKIN DUPLIKAT)**, **FILAMENT RESOURCE (BUKAN PAGE)** karena ini katalog dengan jumlah baris tidak terbatas, **UPLOAD FOTO AMAN** meniru pola yang sudah terbukti di Modul 14, **TIDAK MENYENTUH LAYAR `/pos`** — itu pekerjaan modul terpisah. |

---

## 1. Latar Belakang & Masalah yang Coba Diselesaikan

Layar Kasir POS (`/pos`) menampilkan grid menu yang terlihat lengkap — kategori "Coffee & Drinks", "Toast & Meals", item seperti "Iced Spanish Latte" Rp38.000, badge "BAR"/"KITCHEN". Tampilannya meyakinkan, tapi investigasi kode membuktikan **seluruh isi layar itu adalah string yang diketik langsung di `resources/views/pos/index.blade.php`** — tidak ada query database, tidak ada model, tidak ada Livewire/Filament component di baliknya selain modal Check-In Tiket. Tombol "Bayar (Lunas)" hanya menjalankan `alert()` JavaScript; tidak ada order yang benar-benar tersimpan.

Yang lebih penting untuk PRD ini: **tabel database untuk menu F&B SUDAH ADA** sejak migration awal proyek (`fnb_categories`, `fnb_menus`, `fnb_modifier_groups`, `fnb_modifier_options`, lengkap dengan model Eloquent-nya) — tapi:

1. **Tidak ada satu pun halaman admin** untuk staf menambah/mengubah/menonaktifkan menu. Satu-satunya cara mengisi tabel ini adalah lewat `DatabaseSeeder.php` — kalau F&B mau tambah menu baru hari ini, developer harus ubah kode & deploy ulang.
2. **`fnb_modifier_groups`/`fnb_modifier_options` ("Milk Option", "Sugar Level", dst. — persis konsep "tambahan" yang diminta) sama sekali tidak terhubung ke menu manapun.** Tidak ada tabel penghubung (pivot), tidak ada relasi di model manapun. Tabelnya secara harfiah tidak bisa dipakai sampai hari ini.
3. Kolom `fnb_menus.image_url` didesain sebagai teks bebas (staf tempel URL gambar dari mana saja) — bukan upload file yang divalidasi & diproses ulang, pola yang sudah ditandai berisiko di audit keamanan proyek ini dan sudah diperbaiki di Modul 14 (Company Profile) dengan pipeline upload aman.

### Yang TIDAK Sedang Dibangun (Batasan Sadar Sejak Awal, Hasil Konfirmasi PM)

- **Bukan modul terpadu semua kategori POS.** Merchandise (jersey), sewa raket/bola, dan wellness (ice bath) **di luar scope v1** — PM secara eksplisit memilih F&B dulu karena tabelnya paling siap & permintaannya spesifik "menu makanan minuman dan additional". Kategori lain menyusul di modul terpisah kalau dibutuhkan.
- **Bukan penyambungan layar `/pos` ke database.** PM secara eksplisit memilih membangun panel **admin (input data) dulu**; membuat `/pos` benar-benar membaca dari `fnb_menus` & memproses order sungguhan adalah pekerjaan modul lanjutan yang terpisah, di luar cakupan dokumen ini.
- **Bukan modul inventaris bahan baku.** `raw_materials` & `recipe_boms` (Bill of Materials resep) sudah ada di database tapi pengelolaan stok bahan baku bukan bagian dari v1 ini — cukup dipastikan tidak rusak (relasi `cascadeOnDelete` yang sudah ada tetap dipertahankan).

---

## 2. Solusi Arsitektur

### 2.1 Pakai Ulang 4 Tabel yang Sudah Ada — Tidak Ada Tabel Baru untuk Data Intinya

`fnb_categories`, `fnb_menus`, `fnb_modifier_groups`, `fnb_modifier_options` beserta model Eloquent-nya (`App\Models\Fnb\FnbCategory`, `FnbMenu`, `FnbModifierGroup`, `FnbModifierOption`) **sudah lengkap dan benar strukturnya** — modul ini tidak mengubah skema tabel-tabel tersebut, hanya membangun panel admin di atasnya. Satu-satunya tambahan skema adalah tabel penghubung yang memang belum pernah dibuat (lihat §3.2).

### 2.2 Panel Admin: 3 Filament Resource (Bukan Page Tunggal)

Berbeda dari Modul 14 (Company Profile) yang cocok jadi 1 Filament `Page` karena datanya singleton (1 baris pengaturan), menu F&B adalah **katalog dengan jumlah baris tidak terbatas** yang butuh pencarian, paginasi, dan halaman create/edit terpisah per baris — pola yang sudah terbukti benar di proyek ini lewat `MembershipPlanResource` (`app/Filament/Resources/Membership/MembershipPlanResource.php`), bukan pola `MasterData`/`PengaturanBiayaPajak` yang dipakai untuk data singleton/kecil.

Dibuat 3 Resource baru, semua di bawah namespace `App\Filament\Resources\Fnb`:

```
app/Filament/Resources/Fnb/
├── FnbCategoryResource.php        (+ Pages/List, Create, Edit)
├── FnbMenuResource.php            (+ Pages/List, Create, Edit)
└── FnbModifierGroupResource.php   (+ Pages/List, Create, Edit)
```

**Kenapa 3 Resource terpisah, bukan 1 Resource raksasa dengan tab?** Kategori, menu, dan grup modifier adalah 3 entitas yang masing-masing berdiri sendiri (kategori dipakai berkali-kali oleh banyak menu, grup modifier dipakai berkali-kali oleh banyak menu) — memaksakannya jadi 1 halaman tab-tab seperti `MasterData` (yang cocok untuk 2 entitas yang jarang berubah: harga lapangan & alat sewa) akan membuat form menu jadi sangat panjang & sulit dicari. 3 Resource terpisah juga otomatis dapat fitur bawaan Filament (search, sort, pagination) yang tidak ada di pola `Page`.

### 2.3 Menghubungkan Menu ↔ Grup Modifier ("Tambahan") — Ini Bagian Inti yang Selama Ini Hilang

`FnbMenuResource` form-nya punya field `CheckboxList`/`Select` multi-pilih bernama "Grup Tambahan (Modifier)" yang mengambil opsi dari `FnbModifierGroup::pluck('name', 'id')` dan disimpan lewat relasi many-to-many Filament (`->relationship('modifierGroups', 'name')`) ke tabel pivot baru `fnb_menu_modifier_group` (§3.2). Ini yang membuat, misalnya, menu "Iced Spanish Latte" bisa dipasangkan ke grup "Milk Option" + "Sugar Level", sementara menu "Smashed Avocado Toast" tidak dipasangkan grup apa pun — **konsep "tambahan" yang diminta PM, yang sebelumnya cuma ada di skema tapi tidak pernah bisa dipakai.**

`FnbModifierGroupResource` sendiri berisi CRUD grup (`name`, `is_required`, `max_selection`) dengan `Repeater` nested untuk opsi-opsinya (`name`, `extra_price`) — pola persis `Repeater::make('benefits')` di `MembershipPlanResource` yang sudah terbukti bekerja untuk kasus "1 induk, banyak anak yang diedit sekaligus di 1 form".

### 2.4 Upload Foto Menu: Pipeline Aman Sama Persis Modul 14 (Bukan Field URL Bebas)

`fnb_menus.image_url` **tetap kolom yang sama** (tidak perlu migration ubah tipe), tapi cara pengisiannya diganti total: staf upload file lewat `FileUpload` component Filament, lalu file diproses ulang lewat GD **persis pipeline yang sudah dipakai & terbukti di `KelolaKontenWebsite::processFacilityPhotoUpload()`**:

1. Deteksi MIME asli dari isi file (`getimagesize()`), bukan dari ekstensi nama file.
2. Decode ulang via `imagecreatefromjpeg/png/webp` sesuai MIME asli — ini yang membuang EXIF/GPS/payload tersembunyi apa pun yang menumpang di file asli.
3. Resize kalau lebar > 1600px.
4. Re-encode ke JPEG kualitas 82, nama file UUID baru, simpan di `storage/app/public/fnb/menu/`.

Staf **tidak pernah** bisa menempelkan URL gambar eksternal sembarangan lagi (risiko hotlinking/gambar berubah tanpa sepengetahuan/SSRF tidak langsung) — satu-satunya cara mengisi `image_url` adalah lewat upload yang diproses ulang di server.

### 2.5 Otorisasi — Pakai Slug yang Sudah Ada, Tidak Bikin Slug Baru untuk Tiap Sub-Bagian

`Club61PermissionMatrix.php` **sudah punya** slug `view_fnb_menu` dan `manage_fnb_menu` di kategori "F&B Cafe & Kitchen (KDS)" sejak sebelum modul ini dibuat — tapi keduanya *orphan* (tidak ada kode yang memeriksanya). Modul ini yang pertama kali benar-benar memakainya:

```php
// Berlaku sama untuk ketiga Resource (FnbCategoryResource, FnbMenuResource, FnbModifierGroupResource)
public static function canViewAny(): bool { return (bool) auth()->user()?->can('view_fnb_menu'); }
public static function canCreate(): bool { return (bool) auth()->user()?->can('manage_fnb_menu'); }
public static function canEdit($record): bool { return (bool) auth()->user()?->can('manage_fnb_menu'); }
public static function canDelete($record): bool { return (bool) auth()->user()?->can('manage_fnb_menu'); }
```

Pola ini **identik** dengan `MembershipPlanResource` (`view_membership_plans` / `manage_membership_plans`). **Kategori & grup modifier sengaja tidak dapat slug sendiri-sendiri** (mis. `manage_fnb_category`) — keduanya diperlakukan sebagai bagian dari "kelola menu F&B" secara konsep, konsisten dengan prinsip proyek ini untuk tidak menambah abstraksi/slug melebihi kebutuhan nyata. Kalau nanti ada kebutuhan role yang bisa atur kategori tapi tidak boleh atur harga menu, itu baru saat yang tepat memisah slug — bukan sekarang.

`DatabaseSeeder` menggranting `view_fnb_menu` + `manage_fnb_menu` ke role `super_admin` dan `admin` (pola sama seperti akses "Master Data"). Role `kitchen`/`cashier` operasional harian **tidak** diberi akses kelola menu — mereka nanti (di modul lanjutan yang menyambungkan `/pos`) hanya *membaca* menu yang sudah aktif, tidak mengeditnya.

---

## 3. Skema Database

### 3.1 Tabel yang Sudah Ada, Dipakai Ulang Tanpa Perubahan Skema

| Tabel | Kolom Kunci | Status |
| :--- | :--- | :--- |
| `fnb_categories` | `id` (ulid), `name` (50), `sort_order` | Sudah ada, dipakai apa adanya. |
| `fnb_menus` | `id`, `category_id` (FK), `name` (100), `description` (text), `image_url` (text — diisi lewat pipeline upload, lihat §2.4), `base_price` (decimal 12,2), `station` (string 20: `BAR`/`KITCHEN`), `is_available` (bool) | Sudah ada, dipakai apa adanya. |
| `fnb_modifier_groups` | `id`, `name` (50), `is_required` (bool), `max_selection` (int) | Sudah ada, dipakai apa adanya. |
| `fnb_modifier_options` | `id`, `group_id` (FK), `name` (50), `extra_price` (decimal 12,2) | Sudah ada, dipakai apa adanya. |

### 3.2 Migration Baru: Tabel Penghubung `fnb_menu_modifier_group`

Satu-satunya tabel baru yang dibutuhkan modul ini — many-to-many antara menu dan grup modifier yang memang belum pernah dibuat:

```php
Schema::create('fnb_menu_modifier_group', function (Blueprint $table) {
    $table->foreignUlid('menu_id')->constrained('fnb_menus')->cascadeOnDelete();
    $table->foreignUlid('group_id')->constrained('fnb_modifier_groups')->cascadeOnDelete();
    $table->primary(['menu_id', 'group_id']);
});
```

`cascadeOnDelete()` di kedua sisi: hapus menu → baris pivot ikut hilang (bukan hapus grup modifier-nya, cuma keterkaitannya); hapus grup modifier → baris pivot ikut hilang (menu yang tadinya pakai grup itu otomatis kehilangan keterkaitan, tanpa error, tanpa menu ikut terhapus).

Relasi model baru:

```php
// FnbMenu.php
public function modifierGroups(): BelongsToMany
{
    return $this->belongsToMany(FnbModifierGroup::class, 'fnb_menu_modifier_group', 'menu_id', 'group_id');
}

// FnbModifierGroup.php
public function menus(): BelongsToMany
{
    return $this->belongsToMany(FnbMenu::class, 'fnb_menu_modifier_group', 'group_id', 'menu_id');
}
```

---

## 4. Kebutuhan Fungsional (Functional Requirements)

### FR-01: Kelola Kategori Menu (`FnbCategoryResource`)
Staf berizin `manage_fnb_menu` bisa tambah/ubah/hapus kategori (`name`, `sort_order`). **Guard hapus**: kategori yang masih punya ≥1 `FnbMenu` **tidak bisa dihapus** (Filament `canDelete` menolak dengan notifikasi jelas: "Kategori masih dipakai N menu, pindahkan dulu menunya"), mencegah `FnbMenu.category_id` menjadi yatim padahal constraint DB-nya `cascadeOnDelete` (yang justru akan diam-diam menghapus semua menu di kategori itu kalau tidak dijaga di level aplikasi).

### FR-02: Kelola Menu F&B (`FnbMenuResource`)
Staf bisa tambah/ubah/hapus menu: pilih kategori, nama, deskripsi, upload foto (§2.4), harga dasar, station (`BAR`/`KITCHEN`), toggle `is_available`. List halaman menampilkan thumbnail foto, kategori, harga, dan status tersedia dengan kolom yang bisa di-toggle langsung dari tabel (`ToggleColumn`) tanpa perlu buka form edit — mempercepat kerja harian "menu ini lagi habis, matiin dulu".

### FR-03: Kelola Grup Modifier & Opsi / "Tambahan" (`FnbModifierGroupResource`)
Staf bisa tambah/ubah/hapus grup modifier (`name`, `is_required`, `max_selection`) beserta daftar opsinya dalam 1 form (`Repeater` nested: `name` + `extra_price` per opsi) — mis. grup "Ukuran Gula" dengan opsi "Normal" (+0), "Less Sugar" (+0), "Extra Sweet" (+0), atau grup "Tambahan Espresso Shot" dengan opsi "1 Shot" (+8000), "2 Shot" (+15000).

### FR-04: Menghubungkan Menu ke Grup Modifier
Di form `FnbMenuResource`, staf memilih 0 atau lebih grup modifier yang berlaku untuk menu tersebut lewat `CheckboxList` (lihat §2.3). Tersimpan ke tabel pivot §3.2. Ini FR inti yang menjawab kata "additional" di permintaan awal PM.

### FR-05: Toggle Ketersediaan Cepat
Kolom `is_available` di list `FnbMenuResource` bisa diklik langsung (ToggleColumn) — perubahan tersimpan seketika tanpa membuka halaman edit penuh, meniru kebutuhan operasional nyata ("stok susu oat habis, matiin menu ini sekarang juga").

### FR-06: Validasi Foto Upload
Foto menu wajib melalui validasi `image|mimes:jpg,jpeg,png,webp|max:2048` di level form SEBELUM diproses pipeline GD (§2.4) — konsisten dengan validasi yang sudah dipakai `KelolaKontenWebsite`.

---

## 5. Kebutuhan Non-Fungsional

1. **Nol Perubahan Skema pada 4 Tabel Lama** — `fnb_categories`, `fnb_menus`, `fnb_modifier_groups`, `fnb_modifier_options` dipakai apa adanya (§3.1); satu-satunya migration baru adalah tabel pivot (§3.2).
2. **Nol Slug Permission Baru** — memakai `view_fnb_menu`/`manage_fnb_menu` yang sudah ada di matrix untuk ketiga Resource (§2.5), bukan menambah slug per sub-entitas.
3. **Foto Diproses Ulang, Bukan Disimpan Mentah** — pipeline GD yang sama dengan Modul 14 (validasi MIME dari isi file, resize, re-encode, nama file UUID) diterapkan tanpa pengecualian ke semua upload foto menu.
4. **Tidak Menyentuh `/pos`** — layar Kasir POS tetap seperti sekarang (statis) sampai modul penyambungan terpisah dikerjakan. Tidak ada perubahan apa pun ke `resources/views/pos/index.blade.php` di PRD ini.
5. **Tidak Menyentuh `raw_materials`/`recipe_boms`** — relasi `cascadeOnDelete` yang sudah ada di `recipe_boms.menu_id` tetap dipertahankan (hapus menu ikut menghapus baris resepnya, perilaku yang sudah ada sejak awal, bukan perubahan baru).
6. **Zero Regression** — `php artisan test` penuh wajib tetap 100% hijau di luar suite baru modul ini.

---

## 6. Rencana Pengujian

1. `test_staff_without_manage_fnb_menu_permission_cannot_create_category_menu_or_modifier_group` — role tanpa slug ditolak di ketiga Resource.
2. `test_admin_can_create_fnb_category` — CRUD dasar kategori.
3. `test_category_still_referenced_by_menu_cannot_be_deleted` — guard FR-01.
4. `test_admin_can_create_menu_with_photo_upload_and_photo_is_reencoded` — upload via `Livewire::test`, `Storage::fake('public')`, assert file baru berbeda dari file asli (dimensi/hasil re-encode), meniru test foto fasilitas Modul 14.
5. `test_menu_availability_toggle_updates_immediately` — FR-05.
6. `test_admin_can_create_modifier_group_with_nested_options` — FR-03, assert opsi ikut tersimpan lewat repeater.
7. `test_menu_can_be_attached_to_multiple_modifier_groups_and_detached_on_group_delete` — FR-04 + cascade pivot §3.2.
8. `test_deleting_menu_cascades_recipe_bom_rows_without_error` — pastikan relasi lama ke `recipe_boms` tidak rusak oleh Resource baru.
9. Jalankan `php artisan test` penuh — suite lama wajib tetap hijau.

---

## 7. Ruang Lingkup & Batasan (Out of Scope v1)

- **Kategori POS selain F&B** (Merchandise, Sewa Raket/Bola, Wellness) — dikonfirmasi PM di luar scope v1, menyusul di modul terpisah kalau dibutuhkan.
- **Penyambungan layar `/pos` ke database** — dikonfirmasi PM sebagai pekerjaan modul lanjutan terpisah. v1 murni panel admin untuk mengisi data.
- **Modul inventaris bahan baku** (`raw_materials`, `recipe_boms`) — tabelnya tetap ada & tidak rusak, tapi UI kelola stok/resep bukan bagian v1.
- **Riwayat/analitik penjualan per menu** — karena `/pos` belum tersambung ke database (lihat batasan di atas), belum ada data transaksi nyata untuk dianalisis di v1 ini.
- **Multi-varian per menu** (mis. ukuran S/M/L dengan harga beda per varian) — v1 mengikuti skema `fnb_menus` yang sudah ada (1 `base_price` per menu). Kalau dibutuhkan varian bertingkat, itu perubahan skema terpisah yang butuh PRD sendiri.
