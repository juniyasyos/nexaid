<?php

namespace App\Filament\Panel\Resources\AccessProfiles\Tables;

use App\Filament\Panel\Resources\AccessProfiles\RelationManagers\RolesRelationManager;
use App\Filament\Panel\Resources\AccessProfiles\RelationManagers\UsersRelationManager;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Guava\FilamentModalRelationManagers\Actions\RelationManagerAction;

class AccessProfilesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Profile Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn($record) => str($record->description)->limit(100)),
                TextColumn::make('key_hash')
                    ->label('Identifier')
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->fontFamily('mono')
                    ->size('sm')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('gray'),
                TextColumn::make('roles_count')
                    ->label('Assigned Roles')
                    ->counts('roles')
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('users_count')
                    ->label('Users')
                    ->counts('users')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                IconColumn::make('is_system')
                    ->label('System')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-pencil')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip(fn(bool $state): string => $state ? 'System profile (protected)' : 'Custom profile'),
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->onColor('success')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->offColor('danger'),
                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime()
                    ->sortable()
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All profiles')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),
                TernaryFilter::make('is_system')
                    ->label('Profile Type')
                    ->placeholder('All types')
                    ->trueLabel('System profiles')
                    ->falseLabel('Custom profiles'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                RelationManagerAction::make()
                    ->label('Manage Users')
                    ->icon('heroicon-o-users')
                    ->color('success')
                    ->slideOver()
                    ->relationManager(UsersRelationManager::make()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('No access profiles yet')
            ->emptyStateDescription('Create access profiles to group application roles for easier user management.')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
