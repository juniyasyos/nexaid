<?php

namespace App\Filament\Panel\Resources\Applications\Tables;

use App\Domain\Iam\Models\Application;
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
            ->poll('10s')
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
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn(Builder $query, $date): Builder => $query->whereDate('updated_at', '>=', $date))
                            ->when($data['until'] ?? null, fn(Builder $query, $date): Builder => $query->whereDate('updated_at', '<=', $date));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if (! empty($data['from'])) {
                            $indicators[] = 'Updated from ' . $data['from'];
                        }

                        if (! empty($data['until'])) {
                            $indicators[] = 'Updated until ' . $data['until'];
                        }

                        return $indicators;
                    }),
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
                            $service = new ApplicationRoleSyncService();
                            $result = $service->syncRoles($record);

                            if (!$result['success']) {
                                Notification::make()
                                    ->title('Sync Failed')
                                    ->body($result['error'])
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $message = $result['message'] . "\n\n";
                            $comparison = $result['comparison'];
                            $inSync = count($comparison['in_sync']);
                            $missing = count($comparison['missing_in_client']);
                            $extra = count($comparison['extra_in_client']);

                            $message .= "Current Status:\n";
                            $message .= "✓ In Sync: {$inSync} role(s)\n";
                            if ($missing > 0) {
                                $message .= "⚠ Missing in Client: {$missing} role(s)\n";
                            }
                            if ($extra > 0) {
                                $message .= "ℹ Extra in Client: {$extra} role(s)";
                            }

                            Notification::make()
                                ->title('Roles Synchronized')
                                ->body($message)
                                ->success()
                                ->send();
                        }),
                    Action::make('syncUsers')
                        ->label('Sync Users This App')
                        ->icon('heroicon-o-user-group')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->action(function (Application $record): void {
                            // gather all access profiles that reference roles from this
                            // specific application and send their ids to the job. this
                            // keeps compatibility with the previous behaviour while
                            // still using the new profile-based mechanism.
                            $profileIds = \App\Domain\Iam\Models\AccessProfile::query()
                                ->whereHas('roles', function ($q) use ($record) {
                                    $q->where('application_id', $record->id);
                                })
                                ->pluck('id')
                                ->toArray();

                            SyncApplicationUsers::dispatch($record, $profileIds);
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
