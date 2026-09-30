<?php

namespace App\Services\Sponsor;

use App\Models\Membership\UserMembership;
use App\Models\Sponsor\SponsorMemberVoucher;
use App\Models\Sponsor\SponsorOrganization;
use App\Models\Sponsor\SponsorOrganizationMember;
use App\Models\User;
use App\Services\Membership\MembershipBalanceService;
use App\Support\PhoneNumber;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SponsorOrganizationService
{
    public function __construct(protected MembershipBalanceService $balanceService) {}

    /**
     * Otomatis bikin wrapper SponsorOrganization begitu ada UserMembership ORGANIZATIONAL yang
     * baru aktif — dipanggil dari MembershipFulfillmentHandler, jadi berlaku SAMA RATA untuk
     * SEMUA jalur pembelian (POS Jual Membership walk-in maupun checkout online), tanpa perlu
     * duplikasi logic di masing-masing kanal. Pembeli (user_id membership) otomatis jadi PIC.
     * Idempotent: kalau organisasi buat membership ini sudah ada (mis. dipanggil ulang), tidak
     * bikin duplikat. Tidak melakukan apa-apa untuk membership INDIVIDUAL.
     */
    public function ensureOrganizationForMembership(UserMembership $membership): ?SponsorOrganization
    {
        if ($membership->owner_type !== 'ORGANIZATIONAL') {
            return null;
        }

        // withTrashed: baris yang di-soft-delete tetap memegang unique user_membership_id.
        $existing = SponsorOrganization::withTrashed()->where('user_membership_id', $membership->id)->first();
        if ($existing) {
            if ($existing->trashed()) {
                $existing->restore();
            }

            return $existing;
        }

        return SponsorOrganization::create([
            'name' => trim(($membership->user->name ?? 'Corporate')).' — Corporate',
            'user_membership_id' => $membership->id,
            'sponsor_admin_user_id' => $membership->user_id,
            'status' => 'ACTIVE',
        ]);
    }

    /**
     * Pintu kedua selain pembelian: super_admin memberikan paket ORGANIZATIONAL langsung ke
     * PIC (mis. kontrak sponsor yang dibayar di luar sistem / kompensasi). Lewat jalur yang
     * SAMA dengan pembelian (purchasePlan + activateMembership) supaya kuota & ledger identik,
     * tapi dengan diskon 100% + alasan wajib — jadi omzet tidak tercatat palsu dan jejaknya
     * (siapa yang memberi, kenapa) tetap ada di kartu membership.
     */
    public function grantCorporateMembership(User $pic, \App\Models\Membership\MembershipPlan $plan, string $companyName, User $grantedBy, string $reason): SponsorOrganization
    {
        if ($plan->ownership_type !== 'ORGANIZATIONAL') {
            throw new DomainException('Paket yang dipilih bukan paket Corporate / Sponsor (ORGANIZATIONAL).');
        }

        if (trim($reason) === '') {
            throw new DomainException('Alasan pemberian membership wajib diisi.');
        }

        return DB::transaction(function () use ($pic, $plan, $companyName, $grantedBy, $reason) {
            $membership = $this->balanceService->purchasePlan($pic, $plan, [
                'owner_type' => 'ORGANIZATIONAL',
                'manual_discount_percent' => 100,
                'manual_discount_reason' => 'Diberikan langsung oleh '.$grantedBy->name.' (tanpa pembelian): '.trim($reason),
                'sold_by_admin_id' => $grantedBy->id,
            ]);

            $membership = $this->balanceService->activateMembership($membership);

            $organization = $this->ensureOrganizationForMembership($membership);
            $organization->update(['name' => trim($companyName)]);

            \App\Services\Audit\ActivityLogger::record(
                module: 'MEMBERSHIP',
                event: 'membership.granted_free',
                description: "Memberikan paket corporate \"{$plan->name}\" GRATIS (tanpa pembelian) ke {$pic->name} untuk ".trim($companyName),
                subject: $organization,
                meta: [
                    'paket' => $plan->name,
                    'harga_normal' => (float) $plan->price,
                    'pic' => $pic->name,
                    'perusahaan' => trim($companyName),
                    'alasan' => trim($reason),
                ],
                severity: \App\Services\Audit\ActivityLogger::CRITICAL,
                causer: $grantedBy,
            );

            return $organization;
        });
    }

    /**
     * Tambah anggota tim secara manual (Nama, No HP, jam voucher awal opsional). Kalau nomor
     * HP sudah terdaftar sebagai user, akun itu ditautkan langsung. Kalau belum, dibuatkan akun
     * customer baru dengan password default 6 digit terakhir nomor HP.
     */
    public function addMemberManually(SponsorOrganization $organization, string $name, string $phone, ?float $initialHours = null, ?User $issuedBy = null): SponsorOrganizationMember
    {
        return DB::transaction(function () use ($organization, $name, $phone, $initialHours, $issuedBy) {
            $user = $this->findOrCreateUserByPhone($name, $phone);

            $existing = SponsorOrganizationMember::where('sponsor_organization_id', $organization->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status === 'ACTIVE') {
                throw new DomainException("Anggota dengan nomor HP {$user->phone} sudah aktif di roster tim ini.");
            }

            if ($existing) {
                $member = tap($existing)->update(['status' => 'ACTIVE']);
            } else {
                $member = SponsorOrganizationMember::create([
                    'sponsor_organization_id' => $organization->id,
                    'user_id' => $user->id,
                    'status' => 'ACTIVE',
                ]);
            }

            if ($initialHours !== null && $initialHours > 0) {
                $this->releaseVoucher($member, $initialHours, 'MANUAL_RELEASE', $issuedBy);
            }

            return $member;
        });
    }

    /**
     * Terbitkan voucher jam baru buat 1 anggota (expired 1 bulan dari sekarang). SENGAJA
     * selalu bikin baris baru — kalau anggota masih punya sisa voucher lama yang belum
     * expired, sisa itu TETAP jalan terpisah (anggota jadi punya >1 voucher aktif), tidak
     * digabung/direset. Ini keputusan bisnis eksplisit dari PM: "yang lama biarin aja, jadi
     * dia punya 2 voucher".
     */
    public function releaseVoucher(SponsorOrganizationMember $member, float $hours, string $source = 'MANUAL_RELEASE', ?User $issuedBy = null, ?string $period = null): SponsorMemberVoucher
    {
        if ($hours <= 0) {
            throw new DomainException('Jumlah jam voucher harus lebih dari 0.');
        }

        return DB::transaction(function () use ($member, $hours, $source, $issuedBy, $period) {
            $voucher = SponsorMemberVoucher::create([
                'sponsor_organization_member_id' => $member->id,
                'hours_granted' => $hours,
                'hours_used' => 0,
                'issued_at' => now(),
                'expires_at' => now()->addMonth(),
                'source' => $source,
                'period' => $period,
                'issued_by_user_id' => $issuedBy?->id,
            ]);

            $this->deductFromOrganizationQuota(
                $member->organization,
                $hours,
                'Rilis voucher '.$source.' ke '.($member->user->name ?? 'anggota tim'),
                $voucher->id
            );

            return $voucher;
        });
    }

    /**
     * Rilis voucher CSV_IMPORT untuk 1 periode (format "YYYY-MM") — dipakai khusus oleh CSV
     * import supaya idempotent per bulan: kalau anggota ini SUDAH punya voucher CSV_IMPORT
     * untuk periode yang sama (mis. file yang sama ke-upload dobel tidak sengaja, atau PIC
     * mengoreksi jam di minggu ke-2 bulan yang sama), baris yang sudah ada di-UPDATE jumlah
     * jamnya — TIDAK bikin baris baru yang menumpuk. Upload di bulan/periode BERBEDA tetap
     * membuat voucher baru terpisah (sesuai kebijakan "voucher bulanan, use-it-or-lose-it").
     * Release manual (releaseVoucher() biasa) tidak memakai method ini — tetap selalu menumpuk.
     *
     * @return array{voucher: SponsorMemberVoucher, action: 'created'|'updated'}
     */
    public function releaseVoucherForPeriod(SponsorOrganizationMember $member, float $hours, string $period, ?User $issuedBy = null): array
    {
        $existing = SponsorMemberVoucher::where('sponsor_organization_member_id', $member->id)
            ->where('source', 'CSV_IMPORT')
            ->where('period', $period)
            ->lockForUpdate()
            ->first();

        if ($existing) {
            return ['voucher' => $this->updateVoucherHours($existing, $hours), 'action' => 'updated'];
        }

        return ['voucher' => $this->releaseVoucher($member, $hours, 'CSV_IMPORT', $issuedBy, $period), 'action' => 'created'];
    }

    /**
     * Rilis voucher manual ke BANYAK anggota sekaligus dalam 1 aksi — dipakai buat tombol
     * "Bulk Release" di dashboard PIC. $hoursByMemberId bisa berisi jam yang SAMA buat semua
     * anggota (mode "uniform" — controller yang assemble array-nya jadi seragam), ATAU jam yang
     * BEDA per anggota (mode "per_member"). Tiap anggota tetap diperlakukan lewat releaseVoucher()
     * biasa (selalu menumpuk voucher baru, bukan periode/CSV) — kalau 1 anggota gagal (mis. bukan
     * anggota aktif), anggota lain tetap lanjut diproses (tidak saling menggagalkan satu sama lain).
     *
     * @param  array<string, float>  $hoursByMemberId  [member_id => jam]
     * @return array{released: int, failed: array<int, array{member_id: string, reason: string}>}
     */
    public function bulkReleaseVoucher(SponsorOrganization $organization, array $hoursByMemberId, ?User $issuedBy = null): array
    {
        $result = ['released' => 0, 'failed' => []];

        foreach ($hoursByMemberId as $memberId => $hours) {
            if ($hours === null || $hours <= 0) {
                continue;
            }

            $member = SponsorOrganizationMember::where('id', $memberId)
                ->where('sponsor_organization_id', $organization->id)
                ->where('status', 'ACTIVE')
                ->first();

            if (! $member) {
                $result['failed'][] = ['member_id' => $memberId, 'reason' => 'Anggota tidak ditemukan atau tidak aktif.'];

                continue;
            }

            try {
                $this->releaseVoucher($member, (float) $hours, 'MANUAL_RELEASE', $issuedBy);
                $result['released']++;
            } catch (\Throwable $e) {
                $result['failed'][] = ['member_id' => $memberId, 'reason' => $e->getMessage()];
            }
        }

        return $result;
    }

    /**
     * Koreksi jumlah jam sebuah voucher yang SUDAH terbit (mis. salah ketik saat rilis manual
     * atau salah baca kolom CSV) — beda dari releaseVoucher() yang selalu bikin baris baru,
     * ini mengedit baris yang sama. Tidak boleh diset di bawah jam yang sudah terpakai
     * (hours_used), supaya remaining tidak pernah negatif / histori pemakaian tidak "hilang".
     */
    public function updateVoucherHours(SponsorMemberVoucher $voucher, float $newHoursGranted): SponsorMemberVoucher
    {
        if ($newHoursGranted <= 0) {
            throw new DomainException('Jumlah jam voucher harus lebih dari 0.');
        }

        if ($newHoursGranted < (float) $voucher->hours_used) {
            throw new DomainException("Tidak bisa diset di bawah jam yang sudah terpakai ({$voucher->hours_used} jam).");
        }

        // Cuma nambah jam (delta positif) yang perlu dipotong dari sisa kuota kontrak —
        // mengurangi jam voucher justru MENGEMBALIKAN sisa kuota, jadi selalu diizinkan.
        $delta = $newHoursGranted - (float) $voucher->hours_granted;

        return DB::transaction(function () use ($voucher, $newHoursGranted, $delta) {
            if ($delta > 0) {
                $this->deductFromOrganizationQuota($voucher->member->organization, $delta, 'Koreksi voucher (jam ditambah)', $voucher->id);
            } elseif ($delta < 0) {
                $this->refundToOrganizationQuota($voucher->member->organization, abs($delta), 'Koreksi voucher (jam dikurangi)', $voucher->id);
            }

            $voucher->update(['hours_granted' => $newHoursGranted]);

            return $voucher;
        });
    }

    /**
     * Potong sisa kuota kontrak (saldo UserMembershipBalance facility PADEL) organisasi ini lewat
     * ledger MembershipBalanceService yang SAMA dipakai fasilitas lain — audit trail-nya otomatis
     * tercatat di MembershipUsageLog, dan otomatis menolak (DomainException) kalau sisa saldo
     * tidak cukup. Organisasi yang paketnya tidak dikonfigurasi kuota jam PADEL (null) tidak
     * dibatasi sama sekali — dilewati diam-diam.
     */
    private function deductFromOrganizationQuota(SponsorOrganization $organization, float $hours, string $notes, ?string $voucherId = null): void
    {
        $balance = $organization->padelHourBalance();

        if ($balance === null) {
            return;
        }

        $this->balanceService->adjustQuota(
            balanceId: $balance->id,
            changeType: 'DECREMENT',
            quantity: $hours,
            notes: $notes,
            relatedType: SponsorMemberVoucher::class,
            relatedId: $voucherId
        );
    }

    /**
     * Kembalikan jam ke sisa kuota kontrak organisasi (kebalikan dari deductFromOrganizationQuota)
     * — dipakai saat voucher dihapus atau jumlah jamnya dikoreksi turun.
     */
    private function refundToOrganizationQuota(SponsorOrganization $organization, float $hours, string $notes, ?string $voucherId = null): void
    {
        $balance = $organization->padelHourBalance();

        if ($balance === null) {
            return;
        }

        $this->balanceService->adjustQuota(
            balanceId: $balance->id,
            changeType: 'REVERSAL',
            quantity: $hours,
            notes: $notes,
            relatedType: SponsorMemberVoucher::class,
            relatedId: $voucherId
        );
    }

    /**
     * Hapus voucher yang salah input. Hanya diizinkan kalau BELUM ada jam yang terpakai sama
     * sekali (hours_used = 0) — kalau sudah pernah dipakai buat booking, harus dikoreksi lewat
     * updateVoucherHours() saja supaya jejak pemakaiannya tidak hilang.
     */
    public function deleteVoucher(SponsorMemberVoucher $voucher): void
    {
        if ((float) $voucher->hours_used > 0) {
            throw new DomainException('Voucher ini sudah pernah terpakai, tidak bisa dihapus. Gunakan koreksi jumlah jam saja.');
        }

        DB::transaction(function () use ($voucher) {
            $this->refundToOrganizationQuota($voucher->member->organization, (float) $voucher->hours_granted, 'Hapus voucher salah input', $voucher->id);
            $voucher->delete();
        });
    }

    public function revokeMember(SponsorOrganizationMember $member): SponsorOrganizationMember
    {
        // Sengaja TIDAK menyentuh voucher yang sudah terbit — voucher yang masih berlaku tetap
        // bisa dipakai sampai expired sendiri (keputusan bisnis eksplisit dari PM), revoke cuma
        // menghentikan status keanggotaan roster (mis. anggota baru tidak dapat top-up baru lagi).
        $member->update(['status' => 'REVOKED']);

        return $member;
    }

    public function reactivateMember(SponsorOrganizationMember $member): SponsorOrganizationMember
    {
        $member->update(['status' => 'ACTIVE']);

        return $member;
    }

    /**
     * Import roster + voucher jam dari file CSV (header toleran: name/nama, phone/no_hp/
     * nomor_telepon, jam/hours/durasi_jam). Dibaca baris-per-baris pakai fgetcsv() native PHP
     * (streaming, aman untuk file besar & encoding UTF-8/BOM).
     *
     * @return array{imported: int, reactivated: int, skipped_duplicate: int, vouchers_issued: int, vouchers_updated: int, failed_rows: array<int, array{row: int, reason: string}>}
     */
    public function importMembersFromCsv(SponsorOrganization $organization, string $filePath, ?User $issuedBy = null): array
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            throw new DomainException('File CSV tidak dapat dibaca.');
        }

        // Periode bulan berjalan (format "YYYY-MM") — jadi kunci idempotensi CSV import (lihat
        // releaseVoucherForPeriod()). Diambil sekali di awal supaya konsisten untuk seluruh baris
        // dalam 1 file, walau proses import-nya lama.
        $period = now()->format('Y-m');

        $result = ['imported' => 0, 'reactivated' => 0, 'skipped_duplicate' => 0, 'vouchers_issued' => 0, 'vouchers_updated' => 0, 'failed_rows' => []];

        try {
            $header = fgetcsv($handle);

            if ($header === false) {
                throw new DomainException('File CSV kosong.');
            }

            // Strip UTF-8 BOM dari sel pertama kalau ada.
            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);

            $columns = $this->mapCsvColumns($header);

            if ($columns === null) {
                throw new DomainException('Format CSV tidak dikenali. Wajib ada kolom nama (name/nama) dan nomor telepon (phone/no_hp/nomor_telepon).');
            }

            $rowNumber = 1; // baris 1 = header

            while (($row = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if (count(array_filter($row, fn ($cell) => trim((string) $cell) !== '')) === 0) {
                    continue; // baris kosong, lewati diam-diam
                }

                $name = trim((string) ($row[$columns['name']] ?? ''));
                $phone = trim((string) ($row[$columns['phone']] ?? ''));
                $hoursRaw = $columns['hours'] !== null ? trim((string) ($row[$columns['hours']] ?? '')) : '';

                if ($name === '' || $phone === '') {
                    $result['failed_rows'][] = ['row' => $rowNumber, 'reason' => 'Nama atau nomor HP kosong.'];
                    continue;
                }

                if (! PhoneNumber::looksLikePhone($phone)) {
                    $result['failed_rows'][] = ['row' => $rowNumber, 'reason' => "Nomor HP \"{$phone}\" tidak valid."];
                    continue;
                }

                $hours = null;
                if ($hoursRaw !== '') {
                    if (! is_numeric($hoursRaw) || (float) $hoursRaw <= 0) {
                        $result['failed_rows'][] = ['row' => $rowNumber, 'reason' => "Jam \"{$hoursRaw}\" tidak valid."];
                        continue;
                    }
                    $hours = (float) $hoursRaw;
                }

                try {
                    DB::transaction(function () use ($organization, $name, $phone, $hours, $period, $issuedBy, &$result) {
                        $user = $this->findOrCreateUserByPhone($name, $phone);

                        $existing = SponsorOrganizationMember::where('sponsor_organization_id', $organization->id)
                            ->where('user_id', $user->id)
                            ->lockForUpdate()
                            ->first();

                        if ($existing && $existing->status === 'ACTIVE') {
                            $member = $existing;
                            $result['skipped_duplicate']++;
                        } elseif ($existing) {
                            $existing->update(['status' => 'ACTIVE']);
                            $member = $existing;
                            $result['reactivated']++;
                        } else {
                            $member = SponsorOrganizationMember::create([
                                'sponsor_organization_id' => $organization->id,
                                'user_id' => $user->id,
                                'status' => 'ACTIVE',
                            ]);
                            $result['imported']++;
                        }

                        if ($hours !== null) {
                            $release = $this->releaseVoucherForPeriod($member, $hours, $period, $issuedBy);
                            $release['action'] === 'created' ? $result['vouchers_issued']++ : $result['vouchers_updated']++;
                        }
                    });
                } catch (\Throwable $e) {
                    $result['failed_rows'][] = ['row' => $rowNumber, 'reason' => 'Gagal diproses: '.$e->getMessage()];
                }
            }
        } finally {
            fclose($handle);
        }

        return $result;
    }

    /**
     * @return array{name: int, phone: int, hours: int|null}|null
     */
    private function mapCsvColumns(array $header): ?array
    {
        $normalized = array_map(fn ($col) => strtolower(trim((string) $col)), $header);

        $nameIndex = null;
        $phoneIndex = null;
        $hoursIndex = null;

        foreach ($normalized as $index => $col) {
            if ($nameIndex === null && in_array($col, ['name', 'nama'], true)) {
                $nameIndex = $index;
            }
            if ($phoneIndex === null && in_array($col, ['phone', 'no_hp', 'nomor_telepon', 'telepon', 'hp', 'nomor_hp'], true)) {
                $phoneIndex = $index;
            }
            if ($hoursIndex === null && in_array($col, ['jam', 'hours', 'durasi_jam', 'jam_voucher'], true)) {
                $hoursIndex = $index;
            }
        }

        if ($nameIndex === null || $phoneIndex === null) {
            return null;
        }

        return ['name' => $nameIndex, 'phone' => $phoneIndex, 'hours' => $hoursIndex];
    }

    private function findOrCreateUserByPhone(string $name, string $phone): User
    {
        $normalizedPhone = PhoneNumber::normalize($phone);

        if (! $normalizedPhone) {
            throw new DomainException("Nomor HP \"{$phone}\" tidak valid.");
        }

        $user = User::where('phone', $normalizedPhone)->first();

        if ($user) {
            return $user;
        }

        return User::create([
            'name' => $name,
            'email' => 'corp_'.$normalizedPhone.'@club61.id',
            'phone' => $normalizedPhone,
            'password' => Hash::make(substr($normalizedPhone, -6)),
            'role' => 'CUSTOMER',
            'is_active' => true,
        ]);
    }
}
