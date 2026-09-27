<?php

namespace App\Services\Sponsor;

use App\Models\Sponsor\SponsorAccessSchedule;
use App\Models\Sponsor\SponsorOrganization;
use App\Models\User;
use DomainException;

/**
 * Jadwal akses lapangan per sponsor — dikontrol VENUE (staf lewat Filament), bukan PIC.
 * Dipakai untuk gantian jatah jam antar beberapa sponsor yang berbagi kapasitas lapangan
 * yang sama (mis. 3 sponsor, 3 lapangan, tidak semua boleh main di jam rame bersamaan).
 */
class SponsorScheduleService
{
    public function createRule(
        SponsorOrganization $organization,
        string $validFrom,
        string $validUntil,
        ?array $daysOfWeek,
        string $timeStart,
        string $timeEnd,
        ?int $maxConcurrentCourts = null,
        ?User $createdBy = null
    ): SponsorAccessSchedule {
        if ($validFrom > $validUntil) {
            throw new DomainException('Tanggal mulai harus lebih awal atau sama dengan tanggal selesai.');
        }

        if ($timeStart >= $timeEnd) {
            throw new DomainException('Jam mulai harus lebih awal dari jam selesai.');
        }

        if ($daysOfWeek !== null) {
            foreach ($daysOfWeek as $day) {
                if (! is_int($day) || $day < 1 || $day > 7) {
                    throw new DomainException('Hari tidak valid. Gunakan angka 1 (Senin) sampai 7 (Minggu).');
                }
            }
        }

        if ($maxConcurrentCourts !== null && $maxConcurrentCourts < 1) {
            throw new DomainException('Maks lapangan bersamaan harus minimal 1 (atau dikosongkan untuk tanpa batas).');
        }

        return SponsorAccessSchedule::create([
            'sponsor_organization_id' => $organization->id,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'days_of_week' => $daysOfWeek,
            'time_start' => $timeStart,
            'time_end' => $timeEnd,
            'max_concurrent_courts' => $maxConcurrentCourts,
            'created_by_user_id' => $createdBy?->id,
        ]);
    }

    public function deleteRule(SponsorAccessSchedule $rule): void
    {
        $rule->delete();
    }
}
