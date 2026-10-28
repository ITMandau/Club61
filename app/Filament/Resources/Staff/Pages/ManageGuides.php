<?php

namespace App\Filament\Resources\Staff\Pages;

use App\Filament\Resources\Staff\GuideResource;
use App\Services\Staff\GuideService;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageGuides extends ManageRecords
{
    protected static string $resource = GuideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah Panduan')->modalWidth('2xl')
                ->modalDescription('Panduan baru masih NONAKTIF: cek dulu lewat Pratinjau, lalu aktifkan link-nya.')
                ->using(fn (array $data, CreateAction $action) => GuideResource::attempt($action, fn () => app(GuideService::class)
                    ->create($data, (string) $data['file'], $data['source_name'] ?? null, auth()->user())))
                ->successNotificationTitle('Panduan diunggah — cek lewat Pratinjau, lalu aktifkan link-nya'),
        ];
    }
}
