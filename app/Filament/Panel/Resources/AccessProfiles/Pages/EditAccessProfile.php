<?php

namespace App\Filament\Panel\Resources\AccessProfiles\Pages;

use App\Filament\Panel\Resources\AccessProfiles\AccessProfileResource;
use App\Filament\Panel\Resources\AccessProfiles\RelationManagers\RolesRelationManager;
use App\Filament\Panel\Resources\AccessProfiles\RelationManagers\UsersRelationManager;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Guava\FilamentModalRelationManagers\Actions\RelationManagerAction;
use Filament\Resources\Pages\EditRecord;

class EditAccessProfile extends EditRecord
{
    protected static string $resource = AccessProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Delete Role Bundle')
                ->icon('heroicon-m-trash')
                ->color('danger'),
            ActionGroup::make([
                RelationManagerAction::make()
                    ->label('Manage Roles')
                    ->record($this->getRecord())
                    ->slideOver()
                    ->icon('heroicon-m-shield-check')
                    ->relationManager(RolesRelationManager::make()),
                RelationManagerAction::make()
                    ->label('Manage Users')
                    ->slideOver()
                    ->icon('heroicon-m-users')
                    ->record($this->getRecord())
                    ->relationManager(UsersRelationManager::make()),
            ])
                ->button()
                ->label('Manage Relations')
                ->color('primary')
                ->icon('heroicon-m-cog'),
        ];
    }

    /**
     * Temp holder for role ids from the form.
     */
    protected array $tempRoleIds = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Populate the app_roles form state from the database
        $data['app_roles'] = $this->record->roles->pluck('id', 'application_id')->toArray();

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $roleIds = $data['app_roles'] ?? [];

        $this->tempRoleIds = array_values(array_unique(array_filter($roleIds)));

        unset($data['roles'], $data['role_ids'], $data['app_roles']);

        return $data;
    }

    protected function afterSave(): void
    {
        if (! empty($this->tempRoleIds)) {
            $this->record->roles()->sync($this->tempRoleIds);
        }

        // Clear cache for all users who have this access profile
        // so their roles_by_app is refreshed immediately.
        foreach ($this->record->users as $user) {
            $user->clearRelationshipCaches();
        }
    }
}
