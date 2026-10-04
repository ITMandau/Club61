<?php

namespace App\Filament\Resources\Sponsor\Pages;

use App\Filament\Resources\Sponsor\SponsorOrganizationResource;
use App\Models\Sponsor\SponsorOrganization;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSponsorOrganization extends CreateRecord
{
    protected static string $resource = SponsorOrganizationResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    /**
     * Sponsor yang dihapus cuma di-soft-delete, tapi user_membership_id-nya tetap memegang
     * unique index. Kalau membership yang sama ditautkan lagi, pulihkan baris lama (roster &
     * riwayat voucher ikut kembali) alih-alih insert baru yang pasti bentrok.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $trashed = SponsorOrganization::onlyTrashed()
            ->where('user_membership_id', $data['user_membership_id'] ?? null)
            ->first();

        if (! $trashed) {
            return parent::handleRecordCreation($data);
        }

        $trashed->restore();
        $trashed->update($data);

        return $trashed;
    }
}
