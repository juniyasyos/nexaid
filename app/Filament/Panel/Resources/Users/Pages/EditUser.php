<?php

namespace App\Filament\Panel\Resources\Users\Pages;

use App\Filament\Panel\Resources\Users\RelationManagers\AccessProfilesRelationManager;
use App\Filament\Panel\Resources\Users\UserResource;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Guava\FilamentModalRelationManagers\Actions\RelationManagerAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RelationManagerAction::make()
                ->icon('heroicon-o-shield-check')
                ->label('Manage Role Bundles')
                ->record($this->getRecord())
                ->slideOver()
                ->relationManager(AccessProfilesRelationManager::make()),
            ActionGroup::make([
                ViewAction::make()
                    ->label('View')
                    ->icon('heroicon-o-eye'),
                DeleteAction::make()
                    ->label('Delete')
                    ->icon('heroicon-o-trash'),
            ])
                ->label('Actions')
                ->icon('heroicon-o-ellipsis-vertical')
                ->button()
        ];
    }
}
