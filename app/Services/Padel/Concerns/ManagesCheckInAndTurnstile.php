<?php

namespace App\Services\Padel\Concerns;

use App\Models\Padel\PadelBooking;
use App\Models\Padel\PadelBookingEquipment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

trait ManagesCheckInAndTurnstile
{
    /**
     * Verifikasi Check-In di Frontdesk Kasir / Gate.
     * Dilengkapi toleransi double-scan ramah kasir (HTTP 200) dan alert serah-terima alat (Equipments Handover).
     *
     * @throws HttpException
     */
    public function checkIn(string $code, User $staffUser): array
    {
        $booking = PadelBooking::with(['court', 'user', 'equipments.equipment'])
            ->where(function ($q) use ($code) {
                $q->where('qr_code_hash', $code)
                    ->orWhere('booking_code', $code);
            })
            ->first();

        if (! $booking) {
            throw new HttpException(404, 'Tiket tidak ditemukan. Pastikan QR Code atau Kode Booking benar.');
        }

        $now = now();

        // Validasi jika sesi sudah lewat dan pemain belum pernah check-in -> Otomatis EXPIRED
        if ($now->gt($booking->end_time) && $booking->checked_in_at === null) {
            $booking->update(['status' => 'EXPIRED']);
            Cache::forget('kelola_pemesanan_tab_counts');
            throw new HttpException(400, "Tiket kedaluwarsa (Expired). Sesi bermain pada pukul {$booking->start_time->format('H:i')} - {$booking->end_time->format('H:i')} WIB telah selesai.");
        }

        if (in_array($booking->status, ['CANCELLED', 'EXPIRED', 'REFUNDED', 'REFUND_PENDING'])) {
            throw new HttpException(400, "Tiket tidak aktif atau telah dibatalkan. Status saat ini: {$booking->status}.");
        }

        if ($booking->status === 'PENDING' || $booking->status === 'PENDING_PAYMENT') {
            throw new HttpException(400, "Tiket belum lunas. Silakan selesaikan pembayaran terlebih dahulu.");
        }

        // Ambil daftar peralatan sewa (flat per order atau per booking)
        $equipmentList = [];
        if ($booking->order_id) {
            $orderEquipments = PadelBookingEquipment::with('equipment')
                ->where('order_id', $booking->order_id)
                ->get();
            foreach ($orderEquipments as $oe) {
                $equipmentList[] = [
                    'name' => $oe->equipment ? $oe->equipment->name : 'Peralatan Padel',
                    'quantity' => $oe->quantity,
                ];
            }
        } elseif ($booking->equipments && $booking->equipments->isNotEmpty()) {
            foreach ($booking->equipments as $be) {
                $equipmentList[] = [
                    'name' => $be->equipment ? $be->equipment->name : 'Peralatan Padel',
                    'quantity' => $be->quantity,
                ];
            }
        }

        $earliestCheckIn = $booking->start_time->copy()->subMinutes(45);

        // Validasi waktu awal check-in (-45 menit)
        if ($now->lt($earliestCheckIn)) {
            $earlyMinutes = (int) $now->diffInMinutes($booking->start_time);
            throw new HttpException(400, "Akses check-in baru dibuka 45 menit sebelum sesi dimulai. Jadwal main masih {$earlyMinutes} menit lagi (pukul {$booking->start_time->format('H:i')} WIB).");
        }

        // 🛡️ TOLERANSI DOUBLE-SCAN KASIR: Kembalikan 200 OK dengan alert kuning/informasi
        if ($booking->status === 'CHECKED_IN' || $booking->checked_in_at !== null) {
            $formattedTime = $booking->checked_in_at ? $booking->checked_in_at->format('H:i') : 'sebelumnya';
            return [
                'already_checked_in' => true,
                'message' => "Member atas nama {$booking->user->name} sudah melakukan check-in sebelumnya pada pukul {$formattedTime} WIB.",
                'booking_code' => $booking->booking_code,
                'player_name' => $booking->user->name,
                'court_name' => $booking->court->name,
                'schedule' => $booking->booking_date->format('d M Y') . ', ' . $booking->start_time->format('H:i') . ' - ' . $booking->end_time->format('H:i') . ' WIB',
                'checked_in_at' => $booking->checked_in_at ? $booking->checked_in_at->toISOString() : $now->toISOString(),
                'equipments' => $equipmentList,
                'gate_marshall' => $staffUser->name,
            ];
        }

        // Scan Pertama: Ubah status menjadi CHECKED_IN
        $booking->update([
            'status' => 'CHECKED_IN',
            'checked_in_at' => $now,
        ]);

        Cache::forget('kelola_pemesanan_tab_counts');

        return [
            'already_checked_in' => false,
            'message' => "Check-in berhasil! Akses lapangan dibuka. Silakan serahkan peralatan sewa kepada pemain.",
            'booking_code' => $booking->booking_code,
            'player_name' => $booking->user->name,
            'court_name' => $booking->court->name,
            'schedule' => $booking->booking_date->format('d M Y') . ', ' . $booking->start_time->format('H:i') . ' - ' . $booking->end_time->format('H:i') . ' WIB',
            'checked_in_at' => $now->toISOString(),
            'equipments' => $equipmentList,
            'gate_marshall' => $staffUser->name,
        ];
    }

    /**
     * Tandai sesi bermain selesai (Complete) saat pemain keluar lapangan / mengembalikan raket.
     */
    public function completeBooking(string $bookingId, User $staffUser): PadelBooking
    {
        $booking = PadelBooking::findOrFail($bookingId);
        $booking->update(['status' => 'COMPLETED']);
        Cache::forget('kelola_pemesanan_tab_counts');
        return $booking;
    }

    /**
     * Otomatis menyinkronkan status booking yang telah lewat jadwal bermain:
     * 1. Status PAID & end_time < now() & checked_in_at IS NULL -> EXPIRED (No-show / Tiket Hangus)
     * 2. Status CHECKED_IN & end_time < now() -> COMPLETED (Selesai Bermain)
     * 3. Rilis expired locks (> 10 menit)
     */
    public function syncExpiredAndCompletedBookings(): array
    {
        $now = now();

        // 1. Booking yang lunas atau reschedule berbayar tapi tidak datang sampai sesi berakhir -> EXPIRED (No-Show)
        $expiredCount = PadelBooking::whereIn('status', ['PAID', 'LOCKED'])
            ->where('end_time', '<', $now)
            ->whereNull('checked_in_at')
            ->where(function ($q) {
                $q->where('status', 'PAID')
                    ->orWhere('reschedule_count', '>', 0)
                    ->orWhereHas('order.payments', function ($pq) {
                        $pq->where('status', 'SUCCESS');
                    });
            })
            ->update(['status' => 'EXPIRED']);

        // 2. Pemain yang sudah check-in dan sesinya telah lewat -> COMPLETED
        $completedCount = PadelBooking::where('status', 'CHECKED_IN')
            ->where('end_time', '<', $now)
            ->update(['status' => 'COMPLETED']);

        // 3. Rilis slot LOCKED yang kedaluwarsa
        $releasedLocks = $this->releaseExpiredLocks();

        if ($expiredCount > 0 || $completedCount > 0 || $releasedLocks > 0) {
            Cache::forget('kelola_pemesanan_tab_counts');
        }

        return [
            'expired' => $expiredCount,
            'completed' => $completedCount,
            'released_locks' => $releasedLocks,
        ];
    }
}
