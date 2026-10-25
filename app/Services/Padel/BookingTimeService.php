<?php

namespace App\Services\Padel;

use App\Models\Padel\BookingTimeSetting;
use App\Models\Padel\PadelBooking;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

/**
 * SATU sumber waktu booking online:
 *   - waktu tahan slot (LOCKED, sebelum checkout) → countdown halaman keranjang/checkout & pelepasan slot;
 *   - batas waktu bayar (sejak klik bayar) → `expiry` sesi Midtrans DAN pelepasan slot PENDING_PAYMENT.
 * Batasnya disimpan per booking (`padel_bookings.expires_at`), jadi perubahan pengaturan hanya berlaku untuk
 * booking baru, dan bayar ulang / ganti metode TIDAK memperpanjang batas bayar.
 */
class BookingTimeService
{
    public const CACHE_KEY = 'booking_time_settings';

    public const CACHE_TTL_SECONDS = 300;

    public const DEFAULT_HOLD_MINUTES = 10;

    public const DEFAULT_PAYMENT_WINDOW_MINUTES = 15;

    public const HOLD_MIN = 5;

    public const HOLD_MAX = 30;

    public const PAYMENT_MIN = 5;

    public const PAYMENT_MAX = 60;

    /** Batas reschedule (jam sebelum main, semua booking): bawaan 2 jam (Modul 21), paling lama 30 hari. */
    public const DEFAULT_RESCHEDULE_CUTOFF_HOURS = 2;

    public const RESCHEDULE_CUTOFF_MIN = 1;

    public const RESCHEDULE_CUTOFF_MAX = 720;

    /**
     * Jeda setelah batas bayar sebelum slot dilepas: notifikasi Midtrans bisa telat (settlement s/d 20 detik,
     * expire s/d 90 detik menurut dokumentasi Midtrans).
     */
    public const GRACE_MINUTES = 3;

    /**
     * Kalau saat batas bayar Midtrans masih bilang "pending" atau tidak bisa dihubungi, slot ditahan paling lama
     * selama ini setelah batas bayar — supaya slot milik customer yang mungkin sudah bayar tidak dilepas ke orang lain.
     */
    public const GATEWAY_UNCERTAIN_MAX_MINUTES = 15;

    /** Sisa waktu minimal supaya bayar ulang masih masuk akal (sesi Midtrans dibuat dalam satuan menit). */
    public const MIN_RETRY_MINUTES = 2;

    /** @var array{slot_hold_minutes: int, payment_window_minutes: int, reschedule_cutoff_hours: int}|null */
    private ?array $settings = null;

    public function holdMinutes(): int
    {
        return $this->settings()['slot_hold_minutes'];
    }

    public function holdSeconds(): int
    {
        return $this->holdMinutes() * 60;
    }

    public function paymentWindowMinutes(): int
    {
        return $this->settings()['payment_window_minutes'];
    }

    /** Reschedule paling lambat sekian jam sebelum jam main — berlaku untuk SEMUA booking. */
    public function rescheduleCutoffHours(): int
    {
        return $this->settings()['reschedule_cutoff_hours'];
    }

    /**
     * Simpan batas reschedule global (jam). Perubahan tercatat otomatis di Log Aktivitas (AuditRegistry).
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException 422 kalau di luar 1 jam – 30 hari
     */
    public function setRescheduleCutoffHours(int $hours): void
    {
        if ($hours < self::RESCHEDULE_CUTOFF_MIN || $hours > self::RESCHEDULE_CUTOFF_MAX) {
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(422, 'Batas reschedule 1 jam sampai 30 hari sebelum main.');
        }

        \Illuminate\Support\Facades\DB::transaction(function () use ($hours) {
            $setting = BookingTimeSetting::query()->lockForUpdate()->first() ?? new BookingTimeSetting();
            $setting->fill(['reschedule_cutoff_hours' => $hours])->save();
        });
        $this->flush();
    }

    /** 72 → "3 hari", 2 → "2 jam", 30 → "30 jam". */
    public static function formatCutoff(int $hours): string
    {
        return $hours >= 24 && $hours % 24 === 0 ? ($hours / 24).' hari' : $hours.' jam';
    }

    public function holdExpiresAt(?CarbonInterface $from = null): CarbonInterface
    {
        return ($from ?? now())->copy()->addMinutes($this->holdMinutes());
    }

    public function paymentExpiresAt(?CarbonInterface $from = null): CarbonInterface
    {
        return ($from ?? now())->copy()->addMinutes($this->paymentWindowMinutes());
    }

    /**
     * Batas bayar booking ini. Booking lama (tanpa `expires_at`) mengikuti aturan lama: dihitung dari created_at.
     */
    public function paymentDeadlineFor(PadelBooking $booking): CarbonInterface
    {
        return $booking->expires_at ?? $booking->created_at->copy()->addMinutes($this->paymentWindowMinutes());
    }

    /**
     * Durasi sesi Midtrans untuk bayar ulang: sisa waktu sampai batas bayar (dibulatkan ke bawah supaya sesi
     * Midtrans selalu berakhir SEBELUM slot dilepas). Null = waktunya sudah habis.
     */
    public function remainingPaymentMinutes(CarbonInterface $deadline): ?int
    {
        $minutes = (int) floor(now()->diffInSeconds($deadline, false) / 60);

        return $minutes >= self::MIN_RETRY_MINUTES ? $minutes : null;
    }

    public function flush(): void
    {
        $this->settings = null;
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array{slot_hold_minutes: int, payment_window_minutes: int, reschedule_cutoff_hours: int} */
    private function settings(): array
    {
        return $this->settings ??= $this->load();
    }

    private function load(): array
    {
        try {
            $row = Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, fn () => BookingTimeSetting::query()->first()?->only(['slot_hold_minutes', 'payment_window_minutes', 'reschedule_cutoff_hours']) ?? []);
        } catch (\Illuminate\Database\QueryException $e) {
            // Tabel belum ada (deploy lupa `php artisan migrate`): pakai default supaya booking tetap jalan.
            report($e);
            $row = [];
        }

        return [
            'slot_hold_minutes' => $this->clamp($row['slot_hold_minutes'] ?? null, self::DEFAULT_HOLD_MINUTES, self::HOLD_MIN, self::HOLD_MAX),
            'payment_window_minutes' => $this->clamp($row['payment_window_minutes'] ?? null, self::DEFAULT_PAYMENT_WINDOW_MINUTES, self::PAYMENT_MIN, self::PAYMENT_MAX),
            'reschedule_cutoff_hours' => $this->clamp($row['reschedule_cutoff_hours'] ?? null, self::DEFAULT_RESCHEDULE_CUTOFF_HOURS, self::RESCHEDULE_CUTOFF_MIN, self::RESCHEDULE_CUTOFF_MAX),
        ];
    }

    private function clamp(mixed $value, int $default, int $min, int $max): int
    {
        return $value === null ? $default : max($min, min($max, (int) $value));
    }
}
