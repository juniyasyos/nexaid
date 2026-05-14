<?php

namespace App\Filament\Panel\Resources\Applications\Pages;

use App\Filament\Panel\Resources\Applications\ApplicationResource;
use App\Filament\Panel\Resources\Applications\RelationManagers\RolesRelationManager;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Guava\FilamentModalRelationManagers\Actions\RelationManagerAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditApplication extends EditRecord
{
    protected static string $resource = ApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RelationManagerAction::make()
                ->label('Manage Roles')
                ->record($this->getRecord())
                ->slideOver()
                ->relationManager(RolesRelationManager::make()),

            ActionGroup::make([
                ViewAction::make()
                    ->label('View')
                    ->icon('heroicon-o-eye'),
                DeleteAction::make()
                    ->icon('heroicon-o-trash')
                    ->label('Delete'),
                ForceDeleteAction::make()
                    ->icon('heroicon-o-x-circle')
                    ->label('Force Delete'),
                RestoreAction::make()
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->label('Restore'),
            ])->label('Actions')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button(),
        ];
    }
}
