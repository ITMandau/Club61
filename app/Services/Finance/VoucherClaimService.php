<?php

namespace App\Services\Finance;

use App\Models\Pos\Voucher;
use App\Models\Pos\VoucherClaim;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Modul 22 tahap 3 — cara sebar C (klaim ke akun). Customer membuka link promo → login → Klaim → voucher masuk Voucher
 * Saya dan muncul otomatis di checkout / kasir. Kuota C = jumlah klaim (dipotong di sini, bukan saat order lunas);
 * satu akun satu klaim. Aturan pemakaian tetap di VoucherService::resolve().
 */
class VoucherClaimService
{
    public function __construct(private VoucherService $vouchers, private PromoVoucherService $promos) {}

    /** Promo klaim aktif berdasarkan kode di link (null = tidak ada / bukan promo klaim). */
    public function findClaimable(string $code): ?Voucher
    {
        $voucher = Voucher::where('code', strtoupper(trim($code)))->first();

        return $voucher && $voucher->isClaim() ? $voucher : null;
    }

    /** Status tombol klaim untuk akun ini: CLAIMABLE | CLAIMED | NOT_STARTED | ENDED | SOLD_OUT | INACTIVE. */
    public function stateFor(Voucher $voucher, ?User $user): string
    {
        return match (true) {
            $user && VoucherClaim::where('voucher_id', $voucher->id)->where('user_id', $user->id)->exists() => 'CLAIMED',
            ! $voucher->is_active => 'INACTIVE',
            $voucher->valid_until && $voucher->valid_until->lt(now()) => 'ENDED',
            $voucher->valid_from && $voucher->valid_from->gt(now()) => 'NOT_STARTED',
            $voucher->quota !== null && $voucher->quota <= 0 => 'SOLD_OUT',
            default => 'CLAIMABLE',
        };
    }

    /**
     * Klaim promo untuk akun ini. Baris voucher dikunci supaya dua klaim bersamaan di kuota terakhir tidak sama-sama
     * lolos; unique (voucher, user) menahan klik ganda.
     */
    public function claim(Voucher $voucher, User $user): VoucherClaim
    {
        if (! $voucher->isClaim()) {
            throw new HttpException(404, 'Promo tidak ditemukan.');
        }

        try {
            return DB::transaction(function () use ($voucher, $user) {
                $locked = Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();

                $message = match ($this->stateFor($locked, $user)) {
                    'CLAIMED' => 'Promo ini sudah kamu klaim — cek Voucher Saya.',
                    'INACTIVE' => 'Promo ini sudah tidak aktif.',
                    'ENDED' => 'Periode promo ini sudah berakhir.',
                    'NOT_STARTED' => 'Promo ini baru bisa diklaim mulai '.$locked->valid_from->timezone(LedgerReport::TIMEZONE)->format('d M Y').'.',
                    'SOLD_OUT' => 'Kuota klaim promo ini sudah habis.',
                    default => null,
                };
                if ($message) {
                    throw new HttpException(422, $message);
                }

                $claim = VoucherClaim::create(['voucher_id' => $locked->id, 'user_id' => $user->id, 'claimed_at' => now()]);
                if ($locked->quota !== null) {
                    $locked->decrement('quota');
                }

                return $claim;
            });
        } catch (UniqueConstraintViolationException) {
            throw new HttpException(422, 'Promo ini sudah kamu klaim — cek Voucher Saya.');
        }
    }

    /**
     * Promo hasil klaim milik akun ini beserta statusnya (untuk checkout, kasir, Voucher Saya).
     * usable = bisa dipakai sekarang (aktif, sudah mulai, belum berakhir, belum dipakai / tidak sedang dipesan).
     *
     * @return Collection<int, array{voucher: Voucher, code: string, name: ?string, headline: string, rules: list<string>, valid_until: ?string, status: string, usable: bool, claimed_at: Carbon}>
     */
    public function claimedFor(User $user): Collection
    {
        return VoucherClaim::where('user_id', $user->id)->with('voucher')->latest('claimed_at')->get()
            ->filter(fn (VoucherClaim $claim) => $claim->voucher && $claim->voucher->isClaim())
            ->map(function (VoucherClaim $claim) use ($user) {
                $voucher = $claim->voucher;
                $status = match (true) {
                    $this->vouchers->promoUsedBy($voucher, $user) => 'USED',
                    ! $voucher->is_active => 'INACTIVE',
                    $voucher->valid_until && $voucher->valid_until->lt(now()) => 'EXPIRED',
                    $voucher->valid_from && $voucher->valid_from->gt(now()) => 'NOT_STARTED',
                    default => 'AVAILABLE',
                };
                $terms = $this->promos->terms($voucher);

                return [
                    'voucher' => $voucher,
                    'code' => $voucher->code,
                    'name' => $voucher->name,
                    'headline' => $terms['headline'],
                    'rules' => $terms['rules'],
                    'valid_until' => $voucher->valid_until?->timezone(LedgerReport::TIMEZONE)->format('d M Y'),
                    'status' => $status,
                    'usable' => $status === 'AVAILABLE',
                    'claimed_at' => $claim->claimed_at,
                ];
            })->values();
    }

    /** Bentuk ringkas untuk API / halaman (tanpa objek model). */
    public function usableForApi(User $user): array
    {
        return $this->claimedFor($user)->where('usable', true)
            ->map(fn (array $c) => collect($c)->only(['code', 'name', 'headline', 'rules', 'valid_until'])->all())
            ->values()->all();
    }
}
