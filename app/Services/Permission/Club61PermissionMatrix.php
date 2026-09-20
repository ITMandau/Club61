<?php

namespace App\Services\Permission;

use Spatie\Permission\Models\Permission;

class Club61PermissionMatrix
{
    /**
     * Definisi lengkap 11 kategori modul beserta sub-modul dan aksi izin.
     * Semua teks murni tanpa emoji maupun ikon.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getMatrix(): array
    {
        return [
            'padel_arena' => [
                'title' => '1. Padel Arena & Bookings',
                'submodules' => [
                    'booking_system' => [
                        'label' => 'Booking System (Kalender & Slot)',
                        'actions' => [
                            'View:BookingSystem' => 'Akses Halaman Kalender & Slot',
                            'View:BookOfflineCourt' => 'Akses Halaman Walk-In Booking',
                            'manage_court_slots' => 'Kelola Jadwal & Ketersediaan Slot',
                            'process_walkin_booking' => 'Proses Pemesanan & Pembayaran Walk-In',
                        ],
                    ],
                    'padel_bookings' => [
                        'label' => 'Kelola Pemesanan & Tiket',
                        'actions' => [
                            'View:KelolaPemesanan' => 'Akses Halaman Kelola Pemesanan',
                            'view_padel_bookings' => 'Lihat Daftar Pemesanan Lapangan',
                            'checkin_padel_ticket' => 'Check-in Tiket Pemesan Lapangan',
                            'reschedule_padel_booking' => 'Reschedule Jadwal Pemesanan',
                            'cancel_refund_padel' => 'Batalkan & Refund Pemesanan (Admin / Staf)',
                            'cancel_padel_booking' => 'Batalkan Pesanan Lapangan (Tombol Batal)',
                            'print_padel_invoice' => 'Cetak Invoice & Bukti Pembayaran',
                        ],
                    ],
                ],
            ],

            'pos_cashier' => [
                'title' => '2. Point of Sale (POS) & Kasir',
                'submodules' => [
                    'pos_terminal' => [
                        'label' => 'Terminal Kasir Frontdesk',
                        'actions' => [
                            'access_pos_terminal' => 'Akses Layar Kasir Frontdesk (/pos)',
                            'pos_cash_payment' => 'Proses Pembayaran Tunai (Cash)',
                            'pos_qris_payment' => 'Generate & Konfirmasi QRIS Dinamis',
                            'settle_unpaid_booking' => 'Pelunasan Pesanan Pending di Kasir',
                            'apply_pos_voucher' => 'Terapkan Voucher Diskon & Promosi',
                            'open_pos_shift' => 'Buka Sesi Shift Kasir Baru',
                            'close_pos_shift' => 'Tutup Sesi Shift & Rekonsiliasi Kas',
                        ],
                    ],
                ],
            ],

            'fnb_kitchen' => [
                'title' => '3. F&B Cafe & Kitchen (KDS)',
                'submodules' => [
                    'kitchen_kds' => [
                        'label' => 'Kitchen Display System (KDS)',
                        'actions' => [
                            'view_kitchen_kds' => 'Akses Layar Monitor Dapur (/kitchen)',
                            'update_kitchen_order_status' => 'Update Status Pesanan Dapur (Proses/Siap)',
                        ],
                    ],
                    'fnb_menu' => [
                        'label' => 'Menu & Resep Dapur',
                        'actions' => [
                            'view_fnb_menu' => 'Lihat Daftar Menu Makanan & Minuman',
                            'manage_fnb_menu' => 'Kelola Menu, Varian & Harga Jual',
                            'manage_recipe_bom' => 'Kelola Formula Resep & Bill of Materials',
                            'manage_raw_materials' => 'Kelola Stok Bahan Baku & Inventaris Dapur',
                        ],
                    ],
                ],
            ],

            'gym_fitness' => [
                'title' => '4. Gym & Fitness Center',
                'submodules' => [
                    'gym_passes' => [
                        'label' => 'Paket & Tiket Gym',
                        'actions' => [
                            'view_gym_passes' => 'Lihat Data Paket & Tiket Masuk Gym',
                            'create_gym_pass' => 'Buat Tiket Masuk / Member Baru Gym',
                            'manage_gym_packages' => 'Kelola Paket Langganan & Tarif Gym',
                        ],
                    ],
                    'gym_checkin' => [
                        'label' => 'Check-In Member Gym',
                        'actions' => [
                            'checkin_gym_member' => 'Check-in Akses Masuk Fasilitas Gym',
                        ],
                    ],
                ],
            ],

            'wellness_suite' => [
                'title' => '5. Wellness & Recovery Suite',
                'submodules' => [
                    'sauna_icebath' => [
                        'label' => 'Sauna & Ice Bath Cold Plunge',
                        'actions' => [
                            'view_wellness_slots' => 'Lihat Jadwal & Slot Sesi Wellness',
                            'book_wellness_session' => 'Reservasi Sesi Sauna / Ice Bath',
                            'checkin_wellness' => 'Check-in Akses Ruang Recovery',
                        ],
                    ],
                    'wellness_maintenance' => [
                        'label' => 'Pemeliharaan Fasilitas',
                        'actions' => [
                            'View:KelolaClub' => 'Akses Halaman Fasilitas Club',
                            'manage_wellness_maintenance' => 'Kelola Pemeliharaan Ruang Wellness',
                        ],
                    ],
                ],
            ],

            'salon_treatment' => [
                'title' => '6. Salon & Hair Treatment',
                'submodules' => [
                    'salon_bookings' => [
                        'label' => 'Booking & Layanan Salon',
                        'actions' => [
                            'view_salon_bookings' => 'Lihat Jadwal Booking Layanan Salon',
                            'book_salon_service' => 'Reservasi Jadwal Hair Treatment & Stylist',
                            'checkin_salon' => 'Check-in Tamu & Konfirmasi Layanan Selesai',
                        ],
                    ],
                    'salon_services' => [
                        'label' => 'Daftar Tarif & Layanan',
                        'actions' => [
                            'manage_salon_services' => 'Kelola Master Layanan & Tarif Salon',
                        ],
                    ],
                ],
            ],

            'merchandise' => [
                'title' => '7. Merchandise & Pro Shop',
                'submodules' => [
                    'merch_catalog' => [
                        'label' => 'Produk & Stok Apparel',
                        'actions' => [
                            'view_merch_products' => 'Lihat Katalog Produk Merchandise',
                            'manage_merch_products' => 'Kelola Produk, Varian & Harga Retail',
                            'manage_merch_stock' => 'Kelola Mutasi Stok Masuk / Keluar',
                        ],
                    ],
                ],
            ],

            'staff_management' => [
                'title' => '8. Karyawan, Pelatih & Staf',
                'submodules' => [
                    'staff_records' => [
                        'label' => 'Manajemen Karyawan',
                        'actions' => [
                            'View:KelolaKaryawan' => 'Akses Halaman Kelola Karyawan',
                            'view_staff' => 'Lihat Daftar Staf, Pelatih & Resepsionis',
                            'create_staff' => 'Tambah Data Karyawan Baru',
                            'update_staff' => 'Ubah Data Karyawan & Jabatan',
                            'delete_staff' => 'Hapus / Nonaktifkan Data Karyawan',
                        ],
                    ],
                ],
            ],

            'tournament_marketing' => [
                'title' => '9. Turnamen, Marketing & Voucher',
                'submodules' => [
                    'tournaments' => [
                        'label' => 'Turnamen & Pertandingan',
                        'actions' => [
                            'View:KelolaTurnamen' => 'Akses Halaman Kelola Turnamen',
                            'view_tournaments' => 'Lihat Data Turnamen & Pertandingan',
                            'manage_tournaments' => 'Kelola Bracket, Peserta & Skor Turnamen',
                        ],
                    ],
                    'marketing' => [
                        'label' => 'Marketing Promo & Kupon',
                        'actions' => [
                            'View:Marketing' => 'Akses Halaman Marketing',
                            'view_marketing' => 'Lihat Informasi Promo & Kampanye',
                            'manage_vouchers' => 'Kelola Kupon Diskon & Kode Promo',
                        ],
                    ],
                ],
            ],

            'analytics_reports' => [
                'title' => '10. Analytics & Laporan Keuangan',
                'submodules' => [
                    'revenue_reports' => [
                        'label' => 'Laporan Omset & Revenue',
                        'actions' => [
                            'View:Analytics' => 'Akses Halaman Analytics Keuangan',
                            'view_financial_reports' => 'Lihat Rincian Laporan Omset Venue',
                            'export_reports' => 'Export Laporan Keuangan (Excel / PDF)',
                        ],
                    ],
                ],
            ],

            'master_data' => [
                'title' => '11. Master Data & Hak Akses',
                'submodules' => [
                    'dashboard_access' => [
                        'label' => 'Dashboard & Portal Member',
                        'actions' => [
                            'View:Dashboard' => 'Akses Halaman Utama Dashboard Admin',
                            'View:Kustomer' => 'Akses Halaman Kustomer & Member VIP',
                        ],
                    ],
                    'user_accounts' => [
                        'label' => 'Akun Pengguna (Users)',
                        'actions' => [
                            'view_users' => 'Lihat Daftar Seluruh Akun Pengguna',
                            'create_users' => 'Tambah Akun Pengguna Baru',
                            'update_users' => 'Ubah Akun, Data Diri & Reset Sandi',
                            'delete_users' => 'Hapus / Blokir Akun Pengguna',
                        ],
                    ],
                    'role_matrix' => [
                        'label' => 'Roles & Hak Akses (Matrix)',
                        'actions' => [
                            'View:RoleResource' => 'Akses Menu Role & Hak Akses',
                            'view_roles' => 'Lihat Daftar Peran & Home Route',
                            'create_roles' => 'Tambah Peran Pengguna Baru',
                            'update_roles' => 'Ubah Konfigurasi Matriks Izin Peran',
                            'delete_roles' => 'Hapus Peran Pengguna',
                        ],
                    ],
                    'pricing_equipment' => [
                        'label' => 'Tarif Lapangan & Fasilitas',
                        'actions' => [
                            'View:MasterData' => 'Akses Halaman Master Data',
                            'View:PengaturanBiayaPajak' => 'Akses Halaman Pengaturan Biaya & Pajak',
                            'manage_court_pricing' => 'Kelola Tarif Sewa Lapangan Per Jam',
                            'manage_court_equipment' => 'Kelola Tarif Sewa Raket & Bola Padel',
                            'manage_tax_and_fees' => 'Kelola Pengaturan Biaya Layanan & Pajak',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Dapatkan daftar seluruh slug permission unik dari matrix.
     *
     * @return array<int, string>
     */
    public static function getAllPermissionSlugs(): array
    {
        $matrix = self::getMatrix();
        $slugs = [];

        foreach ($matrix as $cat) {
            foreach ($cat['submodules'] as $sub) {
                foreach ($sub['actions'] as $slug => $label) {
                    $slugs[] = $slug;
                }
            }
        }

        return array_values(array_unique($slugs));
    }

    /**
     * Sinkronisasikan seluruh permission dari matrix ke database Spatie.
     *
     * @param string $guardName
     * @return int Jumlah permission yang terdaftar
     */
    public static function syncAllPermissions(string $guardName = 'web'): int
    {
        $slugs = self::getAllPermissionSlugs();

        foreach ($slugs as $slug) {
            Permission::firstOrCreate([
                'name' => $slug,
                'guard_name' => $guardName,
            ]);
        }

        // Reset cache permission Spatie
        app('cache')
            ->store(config('permission.cache.store') !== 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));

        return count($slugs);
    }
}
