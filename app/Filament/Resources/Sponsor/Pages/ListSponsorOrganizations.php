<?php

namespace App\Filament\Resources\Sponsor\Pages;

use App\Filament\Resources\Sponsor\SponsorOrganizationResource;
use App\Models\Membership\MembershipPlan;
use App\Models\User;
use App\Services\Sponsor\SponsorOrganizationService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSponsorOrganizations extends ListRecords
{
    protected static string $resource = SponsorOrganizationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('grantCorporateMembership')
                ->label('Berikan Membership Corporate')
                ->icon('heroicon-o-gift')
                ->color('warning')
                ->visible(fn (): bool => (bool) auth()->user()?->can('grant_corporate_membership'))
                ->modalHeading('Berikan Paket Membership Corporate (Tanpa Pembelian)')
                ->modalDescription('Paket langsung aktif & kuotanya terisi, dicatat dengan diskon 100% beserta alasan dan nama pemberi — tidak menambah omzet.')
                ->schema([
                    TextInput::make('company_name')
                        ->label('Nama Perusahaan / Sponsor')
                        ->required()
                        ->maxLength(150),
                    Select::make('pic_user_id')
                        ->label('PIC (Person In Charge)')
                        ->searchable()
                        ->getSearchResultsUsing(fn (string $search) => User::query()
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%")
                            ->limit(20)
                            ->pluck('name', 'id'))
                        ->getOptionLabelUsing(fn ($value) => User::find($value)?->name)
                        ->required(),
                    Select::make('plan_id')
                        ->label('Paket Corporate')
                        ->options(fn () => MembershipPlan::query()
                            ->where('ownership_type', 'ORGANIZATIONAL')
                            ->where('is_active', true)
                            ->pluck('name', 'id'))
                        ->required(),
                    Textarea::make('reason')
                        ->label('Alasan Pemberian')
                        ->placeholder('Contoh: Kontrak sponsor dibayar via transfer korporat (invoice INV-2026-009).')
                        ->required()
                        ->maxLength(500),
                ])
                ->action(function (array $data, SponsorOrganizationService $service): void {
                    abort_unless(auth()->user()?->can('grant_corporate_membership'), 403);

                    try {
                        $organization = $service->grantCorporateMembership(
                            pic: User::findOrFail($data['pic_user_id']),
                            plan: MembershipPlan::findOrFail($data['plan_id']),
                            companyName: $data['company_name'],
                            grantedBy: auth()->user(),
                            reason: $data['reason'],
                        );
                    } catch (\DomainException $e) {
                        Notification::make()->title('Gagal Memberikan Membership')->body($e->getMessage())->danger()->send();

                        return;
                    }

                    Notification::make()
                        ->title('Membership Corporate Aktif')
                        ->body("{$organization->name} siap dipakai. PIC bisa langsung membuka Dashboard Sponsor Team.")
                        ->success()
                        ->send();
                }),

            CreateAction::make()
                ->label('Tambah Sponsor Manual'),
        ];
    }
}
