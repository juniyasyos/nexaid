<?php

namespace App\Filament\Panel\Resources\Applications\Pages;

use App\Filament\Panel\Resources\Applications\ApplicationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewApplication extends ViewRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Edit Application')
                ->icon('heroicon-o-pencil'),
        ];
    }
}
