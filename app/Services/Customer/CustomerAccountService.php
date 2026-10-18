<?php

namespace App\Services\Customer;

use App\Models\Membership\UserMembership;
use App\Models\Pos\Voucher;
use App\Models\Sponsor\SponsorMemberVoucher;
use App\Models\Sponsor\SponsorOrganization;
use App\Models\Sponsor\SponsorOrganizationMember;
use App\Models\User;
use App\Services\Finance\VoucherService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Barang milik customer untuk portal (Modul 23): membership aktif & Voucher Saya. Satu sumber untuk navbar, Profile,
 * My Club, dan Voucher Saya — dulu query membership aktif ditulis ulang di navbar, Home, dan My Club.
 *
 * Kartu voucher berbentuk seragam (voucher saldo, voucher jam corporate, nanti promo) supaya halaman — dan API
 * aplikasi mobile kelak — cukup menampilkan satu daftar.
 */
class CustomerAccountService
{
    public const AVAILABLE = 'AVAILABLE';

    public const USED = 'USED';

    public const EXPIRED = 'EXPIRED';

    public const INACTIVE = 'INACTIVE';

    public const TIMEZONE = 'Asia/Jakarta';

    /** Masa berlaku ≤ ini → label "Berakhir N hari lagi". */
    public const EXPIRING_SOON_DAYS = 7;

    public function __construct(private VoucherService $vouchers) {}

    /** Membership aktif (status ACTIVE & belum lewat end_date) yang paling cepat berakhir. */
    public function activeMembership(User $user): ?UserMembership
    {
        return UserMembership::with(['plan', 'balances'])
            ->where('user_id', $user->id)
            ->where('status', 'ACTIVE')
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', now()->toDateString()))
            ->orderByRaw('end_date IS NULL, end_date ASC')
            ->first();
    }

    public function isSponsorPic(User $user): bool
    {
        return SponsorOrganization::where('sponsor_admin_user_id', $user->id)->exists();
    }

    /**
     * Semua voucher milik customer, dibagi Tersedia (paling cepat berakhir dulu) & Riwayat (terbaru dulu).
     *
     * @return array{available: Collection<int, array>, history: Collection<int, array>}
     */
    public function vouchers(User $user): array
    {
        // toBase(): hasil map dari koleksi Eloquent yang kosong tetap koleksi Eloquent — merge-nya memanggil getKey() pada array.
        $cards = $this->creditCards($user)->toBase()->merge($this->corporateCards($user)->toBase());

        return [
            'available' => $cards->where('status', self::AVAILABLE)
                ->sortBy(fn (array $c) => $c['expires_at']?->getTimestamp() ?? PHP_INT_MAX)->values(),
            'history' => $cards->where('status', '!=', self::AVAILABLE)
                ->sortByDesc(fn (array $c) => ($c['expires_at'] ?? $c['issued_at'])?->getTimestamp() ?? 0)->values(),
        ];
    }

    /** Jumlah voucher yang masih bisa dipakai (angka di dropdown akun). */
    public function availableVoucherCount(User $user): int
    {
        return $this->vouchers($user)['available']->count();
    }

    /** Voucher saldo (refund yang ditolak). */
    private function creditCards(User $user): Collection
    {
        return Voucher::where('user_id', $user->id)
            ->where('discount_type', Voucher::TYPE_CREDIT)
            ->get()
            ->map(function (Voucher $v) {
                $balance = (float) $v->balance;
                $status = match (true) {
                    ! $v->is_active => self::INACTIVE,
                    $balance < 1 => self::USED,
                    $v->valid_until !== null && $v->valid_until->isPast() => self::EXPIRED,
                    default => self::AVAILABLE,
                };
                $available = $status === self::AVAILABLE ? $this->vouchers->availableBalance($v) : $balance;

                return $this->card(
                    kind: 'CREDIT',
                    id: $v->id,
                    title: 'Credit voucher (refund)',
                    value: 'Rp'.$this->shortRupiah($status === self::AVAILABLE ? $available : (float) $v->discount_value),
                    code: $v->code,
                    terms: array_values(array_filter([
                        'Padel court booking',
                        'Rp '.number_format($balance, 0, ',', '.').' of Rp '.number_format((float) $v->discount_value, 0, ',', '.').' left',
                        $status === self::AVAILABLE && $available < $balance - 0.5 ? 'Rp '.number_format($balance - $available, 0, ',', '.').' held by an unpaid booking' : null,
                    ])),
                    status: $status,
                    expiresAt: $v->valid_until,
                    issuedAt: $v->created_at,
                );
            });
    }

    /** Voucher jam main dari perusahaan (Modul 12). */
    private function corporateCards(User $user): Collection
    {
        $members = SponsorOrganizationMember::where('user_id', $user->id)->with('organization')->get()->keyBy('id');
        if ($members->isEmpty()) {
            return collect();
        }

        return SponsorMemberVoucher::whereIn('sponsor_organization_member_id', $members->keys())
            ->orderByDesc('issued_at')
            ->get()
            ->map(function (SponsorMemberVoucher $v) use ($members) {
                $member = $members[$v->sponsor_organization_member_id];
                $remaining = $v->remainingHours();
                $status = match (true) {
                    // Anggota dicabut, atau organisasinya Ditangguhkan admin → voucher tidak bisa dipakai di checkout.
                    $member->status !== 'ACTIVE', (bool) $member->organization?->isSuspended() => self::INACTIVE,
                    $remaining <= 0 => self::USED,
                    $v->isExpired() => self::EXPIRED,
                    default => self::AVAILABLE,
                };

                return $this->card(
                    kind: 'CORPORATE',
                    id: $v->id,
                    title: $member->organization?->name ?? 'Corporate team',
                    value: $this->hours($status === self::AVAILABLE ? $remaining : (float) $v->hours_granted).' HRS',
                    code: null,
                    terms: array_values(array_filter([
                        'Free play hours from your company',
                        $this->hours($remaining).' of '.$this->hours((float) $v->hours_granted).' hours left',
                        $status === self::AVAILABLE ? 'Applied automatically at checkout' : null,
                    ])),
                    status: $status,
                    expiresAt: $v->expires_at,
                    issuedAt: $v->issued_at,
                    isNew: $status === self::AVAILABLE && ! $v->isAcknowledged(),
                );
            });
    }

    private function card(string $kind, string $id, string $title, string $value, ?string $code, array $terms, string $status, ?CarbonInterface $expiresAt, ?CarbonInterface $issuedAt, bool $isNew = false): array
    {
        // Hari kalender WIB (sama dengan tanggal "berlaku s/d" yang tampil), bukan selisih jam yang dibulatkan ke bawah.
        $daysLeft = $expiresAt && $status === self::AVAILABLE
            ? (int) now(self::TIMEZONE)->startOfDay()->diffInDays($expiresAt->copy()->timezone(self::TIMEZONE)->startOfDay(), false)
            : null;

        return [
            'kind' => $kind,
            'id' => $id,
            'title' => $title,
            'value' => $value,
            'code' => $code,
            'terms' => $terms,
            'status' => $status,
            'expires_at' => $expiresAt,
            'issued_at' => $issuedAt,
            'days_left' => $daysLeft,
            'expiring_soon' => $daysLeft !== null && $daysLeft <= self::EXPIRING_SOON_DAYS,
            'is_new' => $isNew,
        ];
    }

    /** "150rb", "1,05jt" — singkatan rupiah yang lazim (bukan "M": dibaca miliar). */
    private function shortRupiah(float $amount): string
    {
        if ($amount >= 1000000) {
            return rtrim(rtrim(number_format($amount / 1000000, 2, ',', '.'), '0'), ',').'jt';
        }
        if ($amount >= 1000 && fmod($amount, 1000) === 0.0) {
            return number_format($amount / 1000, 0, ',', '.').'rb';
        }

        return number_format($amount, 0, ',', '.');
    }

    private function hours(float $hours): string
    {
        return rtrim(rtrim(number_format($hours, 1, '.', ''), '0'), '.');
    }
}
