<?php

namespace App\Services\Membership;

use App\Models\Membership\MembershipPlanBenefit;
use App\Models\Membership\UserMembershipBalance;
use App\Services\Padel\PeakHourService;
use Carbon\Carbon;

/**
 * Aturan akses jam padel sebuah paket / kartu membership (Modul 28, meeting 28 Okt 2026): hari berlaku, jam akses,
 * "hanya di luar jam peak" atau "hanya di jam peak" (jam peak & tanggal merah dari Master Data — PeakHourService, sumber
 * yang sama dengan harga). SATU-SATUNYA pengecek — dipakai checkout online, kasir walk-in (layar & bayar), voucher jam
 * corporate, reschedule, dan Jadwal Tetap.
 *
 * Kartu (UserMembershipBalance) menyimpan salinan aturan paket saat dibeli. Perubahan paket sampai ke kartu yang sudah
 * aktif hanya lewat tombol "Terapkan aturan ke membership aktif" (izin apply_membership_plan_rules).
 */
class PadelAccessRule
{
    public const DAYS = [1 => 'Sen', 2 => 'Sel', 3 => 'Rab', 4 => 'Kam', 5 => 'Jum', 6 => 'Sab', 7 => 'Min'];

    /** Nama hari untuk halaman customer (berbahasa Inggris). */
    public const DAYS_EN = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

    /** @return list<int> hari ISO (1 = Senin … 7 = Minggu); kosong = semua hari */
    public static function days(UserMembershipBalance|MembershipPlanBenefit $source): array
    {
        $days = array_values(array_unique(array_filter(
            array_map('intval', (array) ($source->access_days ?? [])),
            fn (int $d) => $d >= 1 && $d <= 7,
        )));
        sort($days);

        // Ketujuh hari dicentang = sama dengan tanpa batas hari.
        return count($days) === 7 ? [] : $days;
    }

    public static function hasRules(UserMembershipBalance|MembershipPlanBenefit $source): bool
    {
        return self::days($source) !== []
            || ($source->time_window_start && $source->time_window_end)
            || (bool) $source->off_peak_only
            || (bool) $source->peak_only;
    }

    /**
     * Alasan sesi main [start, end) keluar dari aturan paket, null = boleh. Seluruh durasi wajib lolos (all-or-nothing),
     * sama dengan pengecekan jam akses sebelumnya.
     */
    public static function violation(UserMembershipBalance|MembershipPlanBenefit $source, Carbon $start, Carbon $end): ?string
    {
        $days = self::days($source);
        if ($days !== [] && ! in_array($start->dayOfWeekIso, $days, true)) {
            return 'hari '.self::DAYS[$start->dayOfWeekIso].' di luar hari berlaku paket ('.self::daysLabel($days).')';
        }

        if ($source->time_window_start && $source->time_window_end
            && self::outsideWindow((string) $source->time_window_start, (string) $source->time_window_end, $start, $end)) {
            return 'di luar jam akses paket ('.self::windowLabel($source).')';
        }

        if ($source->off_peak_only || $source->peak_only) {
            $peak = app(PeakHourService::class);
            for ($cursor = $start->copy(); $cursor->lt($end); $cursor->addHour()) {
                $isPeak = $peak->isPeak($cursor);
                if ($source->off_peak_only && $isPeak) {
                    return 'jam '.$cursor->format('H:i').' termasuk jam peak, paket hanya berlaku di luar jam peak';
                }
                if ($source->peak_only && ! $isPeak) {
                    return 'jam '.$cursor->format('H:i').' bukan jam peak, paket hanya berlaku di jam peak';
                }
            }
        }

        return null;
    }

    /**
     * Jam yang bisa dipakai per hari menurut aturan & jam peak Master Data saat ini (tanpa tanggal merah), dalam
     * rentang jam buka lapangan. Dipakai ringkasan "Paket dapat digunakan" di form paket & cek "nol jam" saat simpan.
     *
     * @return array<int, list<int>> hari ISO => jam mulai yang boleh (mis. [6, 7, …, 16])
     */
    public static function usableHours(MembershipPlanBenefit|UserMembershipBalance $source, int $openHour = 6, int $closeHour = 24): array
    {
        $rules = app(PeakHourService::class)->rules();
        $days = self::days($source);
        $hours = [];

        foreach (array_keys(self::DAYS) as $iso) {
            $hours[$iso] = [];
            if ($days !== [] && ! in_array($iso, $days, true)) {
                continue;
            }
            foreach (range($openHour, $closeHour - 1) as $hour) {
                $isPeak = collect($rules[$iso % 7] ?? [])->contains(fn (array $r) => $hour >= $r[0] && $hour < $r[1]);
                $slotStart = Carbon::today()->setTime($hour, 0);
                $outsideWindow = $source->time_window_start && $source->time_window_end
                    && self::outsideWindow((string) $source->time_window_start, (string) $source->time_window_end, $slotStart, $slotStart->copy()->addHour());
                if (! $outsideWindow && ! ($source->off_peak_only && $isPeak) && ! ($source->peak_only && ! $isPeak)) {
                    $hours[$iso][] = $hour;
                }
            }
        }

        return $hours;
    }

    /**
     * Ringkasan jam berlaku untuk admin, hari berurutan dengan jam sama digabung, mis.
     * [['Sen–Jum', '06:00–17:00'], ['Sab, Min', 'Tidak berlaku']].
     *
     * @param  array<int, list<int>>  $usable  hasil usableHours()
     * @return list<array{0: string, 1: string}>
     */
    public static function summary(array $usable): array
    {
        $describe = function (array $hours): string {
            if ($hours === []) {
                return 'Tidak berlaku';
            }
            $ranges = [];
            foreach ($hours as $hour) {
                $last = array_key_last($ranges);
                if ($last !== null && $ranges[$last][1] === $hour) {
                    $ranges[$last][1] = $hour + 1;
                } else {
                    $ranges[] = [$hour, $hour + 1];
                }
            }

            return implode(', ', array_map(fn (array $r) => sprintf('%02d:00–%02d:00', $r[0], $r[1]), $ranges));
        };

        $groups = [];
        foreach ($usable as $iso => $hours) {
            $text = $describe($hours);
            $last = array_key_last($groups);
            if ($last !== null && $groups[$last]['text'] === $text) {
                $groups[$last]['days'][] = $iso;
            } else {
                $groups[] = ['days' => [$iso], 'text' => $text];
            }
        }

        return array_map(fn (array $g) => [self::daysLabel($g['days']), $g['text']], $groups);
    }

    /**
     * Apakah sesi main keluar dari jam akses paket. Dihitung dalam menit-dalam-hari: jam selesai 00:00 hari berikutnya
     * = 24:00 (1440), jendela yang berakhir 00:00 = sampai tengah malam. Dulu dibandingkan sebagai teks "H:i:s", jadi
     * slot 23:00–24:00 ("00:00:00") lolos dari paket 06:00–16:00 dan paket malam (s.d. 00:00) selalu menolak.
     */
    public static function outsideWindow(string $windowStart, string $windowEnd, Carbon $start, Carbon $end): bool
    {
        $toMinutes = fn (string $hms) => ((int) substr($hms, 0, 2)) * 60 + (int) substr($hms, 3, 2);
        $startMin = $start->hour * 60 + $start->minute;
        $endMin = $end->isSameDay($start) ? $end->hour * 60 + $end->minute : 1440 + $end->hour * 60 + $end->minute;
        $windowEndMin = $toMinutes($windowEnd) === 0 ? 1440 : $toMinutes($windowEnd);

        return $startMin < $toMinutes($windowStart) || $endMin > $windowEndMin;
    }

    /** Ringkasan aturan untuk layar, mis. "Sen–Jum · 06:00–16:00 · di luar jam peak"; null = tanpa aturan. */
    public static function label(UserMembershipBalance|MembershipPlanBenefit $source): ?string
    {
        $parts = array_filter([
            ($days = self::days($source)) !== [] ? self::daysLabel($days) : null,
            $source->time_window_start && $source->time_window_end ? self::windowLabel($source) : null,
            $source->off_peak_only ? 'di luar jam peak' : null,
            $source->peak_only ? 'di jam peak' : null,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }

    /** Versi halaman customer, mis. "on Mon–Fri, outside peak hours" (dipakai: "Valid only on Mon–Fri, …"). */
    public static function labelEn(UserMembershipBalance|MembershipPlanBenefit $source): ?string
    {
        $parts = array_filter([
            ($days = self::days($source)) !== [] ? 'on '.self::daysLabel($days, self::DAYS_EN) : null,
            $source->time_window_start && $source->time_window_end ? self::windowLabel($source) : null,
            $source->off_peak_only ? 'outside peak hours' : null,
            $source->peak_only ? 'during peak hours' : null,
        ]);

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * Kalimat untuk staf saat jam main di luar ketentuan — bahasa sehari-hari, tanpa istilah corporate / sponsor.
     * $voucher = jam voucher karyawan perusahaan; selain itu kuota paket member.
     */
    public static function outsideNotice(?string $label, bool $voucher): string
    {
        $sentence = $voucher
            ? 'Jam ini di luar ketentuan voucher. Voucher tidak bisa dipakai, bayar harga normal.'
            : 'Jam ini di luar ketentuan paket member. Kuota tidak bisa dipakai, bayar harga normal.';

        return $label ? "{$sentence} Berlaku: {$label}." : $sentence;
    }

    /**
     * @param  list<int>  $days  "Sen–Jum", "Sen, Rab, Jum"
     * @param  array<int, string>  $names  nama hari (DAYS / DAYS_EN)
     */
    public static function daysLabel(array $days, array $names = self::DAYS): string
    {
        $groups = [];
        foreach ($days as $day) {
            $last = array_key_last($groups);
            if ($last !== null && end($groups[$last]) === $day - 1) {
                $groups[$last][] = $day;
            } else {
                $groups[] = [$day];
            }
        }

        return implode(', ', array_map(fn (array $g) => count($g) >= 3
            ? $names[$g[0]].'–'.$names[end($g)]
            : implode(', ', array_map(fn (int $d) => $names[$d], $g)), $groups));
    }

    private static function windowLabel(UserMembershipBalance|MembershipPlanBenefit $source): string
    {
        return substr((string) $source->time_window_start, 0, 5).'–'.substr((string) $source->time_window_end, 0, 5);
    }
}
