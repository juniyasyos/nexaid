<?php

namespace App\Filament\Panel\Resources\Applications\Tables;

use App\Domain\Iam\Models\Application;
use App\Domain\Applications\Services\ApplicationSyncOrchestrator;
use App\Domain\Shared\Services\DateRangeFilterBuilder;
use App\Domain\Iam\Services\ApplicationRoleSyncService;
use App\Jobs\SyncApplicationUsers;
use App\Filament\Panel\Resources\Applications\RelationManagers\RolesRelationManager;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Guava\FilamentModalRelationManagers\Actions\RelationManagerAction;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Modal\Actions\Action as ModalAction;

class ApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Manajemen Aplikasi IAM')
            ->description('Kelola integrasi aplikasi, sinkronisasi hak akses, konfigurasi autentikasi, serta status koneksi aplikasi pada sistem IAM.')
            ->defaultSort('updated_at', 'asc')
            ->poll('30s')
            ->defaultPaginationPageOption(25)
            ->striped()
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->searchPlaceholder('Cari nama aplikasi, app key, atau callback URL...')
            ->columns([
                TextColumn::make('name')
                    ->label('Application Name')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(fn($record) => $record->description),
                TextColumn::make('app_key')
                    ->label('App Key')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->badge()
                    ->toggleable()
                    ->color('primary'),
                TextColumn::make('roles_count')
                    ->label('Roles')
                    ->counts('roles')
                    ->badge()
                    ->toggleable()
                    ->color('info')
                    ->sortable(),
                IconColumn::make('enabled')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->toggleable()
                    ->tooltip(fn(bool $state): string => $state ? 'Application enabled' : 'Application disabled'),
                TextColumn::make('callback_url')
                    ->label('Callback URL')
                    ->limit(40)
                    ->copyable()
                    ->icon('heroicon-m-link')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Last Updated')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('enabled')
                    ->boolean(),
                Filter::make('updated_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn(Builder $query, array $data): Builder => DateRangeFilterBuilder::build($query, $data, 'updated_at'))
                    ->indicateUsing(fn(array $data): array => DateRangeFilterBuilder::getIndicators($data, 'Updated')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                Action::make('toggleEnabled')
                    ->label('Toggle Enabled')
                    ->icon('heroicon-m-adjustments-horizontal')
                    ->action(function (Application $record): void {
                        // TODO: Enforce authorization for toggling enabled state.
                        $record->forceFill([
                            'enabled' => ! $record->enabled,
                        ])->save();
                    })
                    ->requiresConfirmation(),
                RelationManagerAction::make()
                    ->label('Manage Roles')
                    ->icon('heroicon-o-shield-check')
                    ->color('info')
                    ->slideOver()
                    ->relationManager(RolesRelationManager::make()),
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    Action::make('syncRoles')
                        ->label('Sync Roles')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->action(function (Application $record): void {
                            $orchestrator = app(ApplicationSyncOrchestrator::class);
                            $result = $orchestrator->syncRoles($record);

                            Notification::make()
                                ->title($result['success'] ? 'Roles Synchronized' : 'Sync Failed')
                                ->body($orchestrator->formatSyncResult($result))
                                ->color($result['success'] ? 'success' : 'danger')
                                ->send();
                        }),
                    Action::make('syncUsers')
                        ->label('Sync Users This App')
                        ->icon('heroicon-o-user-group')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->action(function (Application $record): void {
                            $orchestrator = app(ApplicationSyncOrchestrator::class);
                            $orchestrator->syncUsers($record);

                            Notification::make()
                                ->title('User sync job queued')
                                ->success()
                                ->send();
                        }),

                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
