<?php

namespace App\Services\Coaching;

use App\Models\Coaching\Coach;
use App\Models\Coaching\CoachingBooking;
use App\Models\Padel\PadelBooking;
use App\Models\Pos\Order;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Booking coaching di POS Walk-In (Modul 24 Opsi B): validasi data coaching dari kasir (isi form bisa direkayasa),
 * cek bentrok jadwal coach, lalu menempelkan tarif setoran ke slot lapangan yang sudah ditahan.
 */
class CoachingBookingService
{
    public const MAX_EXTRA_COACHES = 3;

    /** Status booking yang masih memakai jadwal coach (sama dengan slot lapangan yang masih hidup). */
    public const ACTIVE_STATUSES = ['LOCKED', 'PENDING_PAYMENT', 'PENDING', 'PAID', 'CHECKED_IN'];

    public function __construct(protected CoachingPricingService $pricing) {}

    /**
     * Bersihkan & validasi isian coaching kasir.
     *
     * @param  array{head_coach_id?: mixed, extra_coach_ids?: mixed, students?: mixed, notes?: mixed}  $input
     * @return array{head_coach_id: string, extra_coach_ids: array<int, string>, students: array<int, string>, notes: ?string}
     */
    public function normalize(array $input): array
    {
        $head = trim((string) ($input['head_coach_id'] ?? ''));
        if ($head === '') {
            throw new HttpException(422, 'Pilih coach utama untuk booking coaching.');
        }

        $extra = array_values(array_unique(array_filter(array_map(fn ($id) => trim((string) $id), (array) ($input['extra_coach_ids'] ?? [])), fn ($id) => $id !== '' && $id !== $head)));
        if (count($extra) > self::MAX_EXTRA_COACHES) {
            throw new HttpException(422, 'Coach tambahan maksimal '.self::MAX_EXTRA_COACHES.' orang.');
        }

        $students = array_values(array_filter(array_map(fn ($s) => trim(mb_substr(strip_tags((string) $s), 0, 100)), (array) ($input['students'] ?? [])), fn ($s) => $s !== ''));
        if (count($students) > CoachingBooking::MAX_STUDENTS) {
            throw new HttpException(422, 'Satu sesi coaching maksimal '.CoachingBooking::MAX_STUDENTS.' murid.');
        }

        $notes = trim(mb_substr((string) ($input['notes'] ?? ''), 0, 1000));

        $ids = array_merge([$head], $extra);
        $active = Coach::whereIn('id', $ids)->where('is_active', true)->pluck('id')->all();
        if (count($active) !== count($ids)) {
            throw new HttpException(422, 'Coach yang dipilih tidak ditemukan atau sudah nonaktif. Muat ulang halaman lalu pilih lagi.');
        }

        return ['head_coach_id' => $head, 'extra_coach_ids' => $extra, 'students' => $students, 'notes' => $notes !== '' ? $notes : null];
    }

    /**
     * Coach tidak boleh punya dua sesi di jam yang sama. Baris coach dikunci dulu (urutan id) supaya dua kasir yang
     * membooking coach yang sama bersamaan tidak lolos berdua. Dipanggil DI DALAM transaksi.
     *
     * @param  array<int, string>  $coachIds
     * @param  iterable<array{0: CarbonInterface, 1: CarbonInterface}>  $ranges
     * @param  array<int, string>  $exceptBookingIds  slot yang sedang dipindah (reschedule) — tidak dihitung bentrok
     */
    public function assertCoachesFree(array $coachIds, iterable $ranges, array $exceptBookingIds = []): void
    {
        $coaches = Coach::whereIn('id', $coachIds)->orderBy('id')->lockForUpdate()->get()->keyBy('id');

        // Satu sesi tidak boleh di dua lapangan pada jam yang sama (coach tidak bisa di dua tempat).
        $list = collect($ranges)->values();
        foreach ($list as $i => [$aStart, $aEnd]) {
            foreach ($list->slice($i + 1) as [$bStart, $bEnd]) {
                if ($aStart->lt($bEnd) && $bStart->lt($aEnd)) {
                    throw new HttpException(422, 'Satu sesi coaching tidak bisa memakai dua lapangan di jam yang sama ('.$aStart->format('H:i').'). Buat booking coaching terpisah untuk coach lain.');
                }
            }
        }

        foreach ($list as [$start, $end]) {
            $clash = PadelBooking::query()
                ->whereNotNull('coaching_booking_id')
                ->when($exceptBookingIds !== [], fn ($q) => $q->whereNotIn('id', $exceptBookingIds))
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->where('start_time', '<', $end->format('Y-m-d H:i:s'))
                ->where('end_time', '>', $start->format('Y-m-d H:i:s'))
                ->whereIn('coaching_booking_id', DB::table('coaching_booking_coaches')->whereIn('coach_id', $coachIds)->select('coaching_booking_id'))
                ->with('coachingBooking.coaches:id,name')
                ->first();

            if ($clash) {
                $busy = $clash->coachingBooking?->coaches->pluck('id')->intersect($coachIds)->map(fn ($id) => $coaches[$id]->name ?? 'Coach')->implode(', ');
                throw new HttpException(422, 'Coach '.($busy ?: '').' sudah punya sesi coaching '.$clash->start_time->format('d M H:i').'–'.$clash->end_time->format('H:i').'. Pilih jam / coach lain.');
            }
        }
    }

    /**
     * Pasang tarif coaching ke slot yang sudah ditahan untuk checkout kasir (di DALAM transaksi checkout): harga tiap
     * slot = tarif setoran (+ coach tambahan), membership / voucher tidak dipakai, data coaching disimpan.
     *
     * @param  Collection<int, PadelBooking>  $bookings
     */
    public function attach(Collection $bookings, array $coaching, User $cashier): CoachingBooking
    {
        if (! $cashier->can('book_coaching')) {
            throw new HttpException(403, 'Akses ditolak: Anda tidak memiliki izin [book_coaching] untuk membuat booking coaching.');
        }

        $coachIds = array_merge([$coaching['head_coach_id']], $coaching['extra_coach_ids']);
        $this->assertCoachesFree($coachIds, $bookings->map(fn (PadelBooking $b) => [$b->start_time, $b->end_time]));

        $head = Coach::with('academy')->findOrFail($coaching['head_coach_id']);
        $extraCount = count($coaching['extra_coach_ids']);
        $pricing = [];

        $record = CoachingBooking::create([
            'head_coach_id' => $head->id,
            'academy_name' => $head->academy?->name,
            'students' => $coaching['students'],
            'notes' => $coaching['notes'],
            'extra_coach_fee_per_hour' => $this->pricing->extraFeePerHour(),
            'created_by_id' => $cashier->id,
        ]);
        $record->coaches()->attach($head->id, ['role' => 'HEAD']);
        foreach ($coaching['extra_coach_ids'] as $id) {
            $record->coaches()->attach($id, ['role' => 'EXTRA']);
        }

        foreach ($bookings as $booking) {
            $quote = $this->pricing->feeFor($booking->start_time, $booking->end_time, $extraCount);
            $equipment = (float) $booking->equipment_fee;
            $booking->update([
                'court_fee' => $quote['fee'],
                'total_amount' => $quote['fee'] + $equipment,
                'coaching_booking_id' => $record->id,
            ]);
            $pricing[] = ['booking_id' => $booking->id, 'court_id' => $booking->court_id] + $quote;
        }
        $record->update(['pricing' => $pricing]);

        return $record;
    }

    /** Hubungkan booking coaching ke ordernya (setelah order dibuat). */
    public function linkOrder(CoachingBooking $record, Order $order): void
    {
        $record->update(['order_id' => $order->id]);
    }
}
