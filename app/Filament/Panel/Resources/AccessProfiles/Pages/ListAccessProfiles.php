<?php

namespace App\Filament\Panel\Resources\AccessProfiles\Pages;

use App\Filament\Panel\Resources\AccessProfiles\AccessProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAccessProfiles extends ListRecords
{
    protected static string $resource = AccessProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
            ->label('Tambah Profil Akses')
            ->icon('heroicon-m-plus')
            ->color('primary'),
        ];
    }
}
