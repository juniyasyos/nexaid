<?php

namespace App\Filament\Panel\Resources\AccessProfiles\RelationManagers;

use App\Domain\Iam\Models\ApplicationRole;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RolesRelationManager extends RelationManager
{
    protected static string $relationship = 'roles';

    protected static ?string $title = 'Included Roles';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn($query) => $query->with('application'))
            ->columns([
                TextColumn::make('application.name')
                    ->label('Application')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('info'),
                TextColumn::make('name')
                    ->label('Role')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable()
                    ->color('gray')
                    ->fontFamily('mono')
                    ->size('sm'),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->toggleable()
                    ->wrap(),
                TextColumn::make('is_system')
                    ->label('System Role')
                    ->badge()
                    ->formatStateUsing(fn(bool $state): string => $state ? 'System' : 'Custom')
                    ->color(fn(bool $state): string => $state ? 'warning' : 'success'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Attach Role')
                    ->modalHeading('Attach Application Role to Profile')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(function (Builder $query) {
                        // Group roles by application for better UX
                        return $query->with('application')
                            ->orderBy('application_id')
                            ->orderBy('name');
                    })
                    ->recordSelectSearchColumns(['name', 'slug'])
                    ->schema(fn(AttachAction $action): array => [
                        Select::make('recordId')
                            ->label('Application Role')
                            ->options(function () {
                                return ApplicationRole::with('application')
                                    ->get()
                                    ->groupBy(fn($role) => $role->application->name ?? 'Unknown')
                                    ->map(function ($roles, $appName) {
                                        return $roles->mapWithKeys(function ($role) {
                                            return [$role->id => $role->name . ' (' . $role->slug . ')'];
                                        });
                                    })
                                    ->toArray();
                            })
                            ->searchable()
                            ->required()
                            ->native(false),
                    ])
                    ->after(function ($livewire) {
                        $accessProfile = $livewire->getOwnerRecord();
                        foreach ($accessProfile->users as $user) {
                            $user->clearRelationshipCaches();
                        }
                    }),
            ])
            ->recordActions([
                DetachAction::make()
                    ->requiresConfirmation()
                    ->after(function ($livewire) {
                        $accessProfile = $livewire->getOwnerRecord();
                        foreach ($accessProfile->users as $user) {
                            $user->clearRelationshipCaches();
                        }
                    }),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->requiresConfirmation()
                        ->after(function ($livewire) {
                            $accessProfile = $livewire->getOwnerRecord();
                            foreach ($accessProfile->users as $user) {
                                $user->clearRelationshipCaches();
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('No roles assigned yet')
            ->emptyStateDescription('Attach application roles to this access profile to define permissions.')
            ->emptyStateIcon('heroicon-o-shield-exclamation');
    }
}
