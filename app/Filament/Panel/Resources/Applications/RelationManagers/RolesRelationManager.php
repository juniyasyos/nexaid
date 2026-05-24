<?php

namespace App\Filament\Panel\Resources\Applications\RelationManagers;

use App\Domain\Iam\Services\ApplicationRoleSyncService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RolesRelationManager extends RelationManager
{
    protected static string $relationship = 'roles';

    protected static ?string $title = 'Application Roles';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Role Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray')
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->size('sm'),
                TextColumn::make('description')
                    ->label('Description')
                    ->limit(50)
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_system')
                    ->label('System')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-pencil')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn(bool $state): string => $state ? 'Protected system role' : 'Custom role'),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_system')
                    ->label('Role Type')
                    ->placeholder('All roles')
                    ->trueLabel('System roles only')
                    ->falseLabel('Custom roles only'),
            ])
            ->headerActions([
                Action::make('syncRoles')
                    ->label('Push Roles to Client')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->action(function (): void {
                        $service = new ApplicationRoleSyncService();
                        $result = $service->syncRoles($this->getOwnerRecord());

                        if (empty($result['success'])) {
                            Notification::make()
                                ->title('Sync Failed')
                                ->body($result['error'] ?? 'Unknown error')
                                ->danger()
                                ->send();
                            return;
                        }

                        $message = $result['message'] ?? 'Roles pushed to client successfully.';

                        Notification::make()
                            ->title('Roles Synchronized')
                            ->body($message)
                            ->success()
                            ->send();
                    }),
                CreateAction::make()
                    ->icon('heroicon-m-plus')
                    ->label('Create Role')
                    ->modalHeading('Create New Application Role')
                    ->schema([
                        TextInput::make('name')
                            ->label('Role Name')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Toggle::make('is_system')
                            ->label('System Role')
                            ->helperText('System roles are protected and cannot be deleted.')
                            ->default(false),
                    ])
                    ->mutateDataUsing(function (array $data): array {
                        $data['application_id'] = $this->getOwnerRecord()->id;
                        return $data;
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->schema([
                        TextInput::make('slug')
                            ->label('Role Slug')
                            ->required()
                            ->alphaDash()
                            ->disabled(fn($record) => $record->is_system)
                            ->helperText(fn($record) => $record->is_system ? 'System role slug cannot be changed.' : 'Lowercase letters, numbers, dashes and underscores only.'),
                        TextInput::make('name')
                            ->label('Role Name')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),
                        Toggle::make('is_system')
                            ->label('System Role')
                            ->disabled()
                            ->helperText('System role status cannot be changed.'),
                    ]),
                DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Role')
                    ->modalDescription(fn($record) => "Are you sure you want to delete the role '{$record->name}'? This will remove all user assignments for this role.")
                    ->disabled(fn($record) => $record->is_system)
                    ->tooltip(fn($record) => $record->is_system ? 'System roles cannot be deleted' : null),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->requiresConfirmation()
                        ->modalHeading('Delete Selected Roles')
                        ->modalDescription('Are you sure you want to delete the selected roles? System roles will be skipped.')
                        ->before(function ($records) {
                            // Only delete non-system roles
                            return $records->filter(fn($record) => !$record->is_system);
                        }),
                ]),
            ])
            ->defaultSort('name')
            ->emptyStateHeading('No roles defined')
            ->emptyStateDescription('Create roles to define permissions for this application.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }
}
