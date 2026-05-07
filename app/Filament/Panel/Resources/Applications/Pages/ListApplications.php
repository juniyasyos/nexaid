<?php

namespace App\Filament\Panel\Resources\Applications\Pages;

use App\Filament\Panel\Resources\Applications\ApplicationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListApplications extends ListRecords
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Aplikasi')
                ->icon('heroicon-m-plus')
                ->color('primary'),
        ];
    }
}
