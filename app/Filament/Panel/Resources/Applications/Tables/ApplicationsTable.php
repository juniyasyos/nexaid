<?php

namespace App\Filament\Panel\Resources\Applications\Tables;

use App\Domain\Applications\Services\ApplicationSyncOrchestrator;
use App\Domain\Iam\Models\Application;
use App\Domain\Shared\Services\DateRangeFilterBuilder;
use App\Filament\Panel\Resources\Applications\RelationManagers\RolesRelationManager;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Guava\FilamentModalRelationManagers\Actions\RelationManagerAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Aplikasi Terintegrasi')
            ->description('Kelola aplikasi yang terhubung ke IAM, termasuk status akses, role, callback URL, dan proses sinkronisasi.')
            ->defaultSort('updated_at', 'desc')
            ->poll('30s')
            ->striped()
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->persistSortInSession()
            ->searchPlaceholder('Cari aplikasi, app key, atau callback URL...')
            ->columns([
                TextColumn::make('name')
                    ->label('Aplikasi')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn(Application $record): ?string => $record->description
                        ? Str::limit($record->description, 80)
                        : 'Belum ada deskripsi')
                    ->wrap(),

                TextColumn::make('app_key')
                    ->label('App Key')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono')
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->copyMessage('App key berhasil disalin')
                    ->toggleable(),

                TextColumn::make('roles_count')
                    ->label('Role')
                    ->counts('roles')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->toggleable(),

                IconColumn::make('enabled')
                    ->label('Akses')
                    ->boolean()
                    ->trueIcon('heroicon-m-check-circle')
                    ->falseIcon('heroicon-m-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->tooltip(fn(bool $state): string => $state
                        ? 'Aplikasi aktif dan dapat digunakan'
                        : 'Aplikasi nonaktif dan akses dibatasi')
                    ->sortable(),

                TextColumn::make('callback_url')
                    ->label('Callback URL')
                    ->icon('heroicon-m-link')
                    ->limit(45)
                    ->copyable()
                    ->copyMessage('Callback URL berhasil disalin')
                    ->placeholder('Belum dikonfigurasi')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Terakhir Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->color('gray')
                    ->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('enabled')
                    ->label('Status Akses')
                    ->placeholder('Semua aplikasi')
                    ->trueLabel('Aktif')
                    ->falseLabel('Nonaktif')
                    ->queries(
                        true: fn(Builder $query) => $query->where('enabled', true),
                        false: fn(Builder $query) => $query->where('enabled', false),
                        blank: fn(Builder $query) => $query,
                    ),

                SelectFilter::make('roles_count')
                    ->label('Ketersediaan Role')
                    ->options([
                        'with_roles' => 'Sudah memiliki role',
                        'without_roles' => 'Belum memiliki role',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'with_roles' => $query->has('roles'),
                            'without_roles' => $query->doesntHave('roles'),
                            default => $query,
                        };
                    }),

                Filter::make('updated_at')
                    ->label('Tanggal Diperbarui')
                    ->schema([
                        DatePicker::make('from')
                            ->label('Dari tanggal'),

                        DatePicker::make('until')
                            ->label('Sampai tanggal'),
                    ])
                    ->query(
                        fn(Builder $query, array $data): Builder =>
                        DateRangeFilterBuilder::build($query, $data, 'updated_at')
                    )
                    ->indicateUsing(
                        fn(array $data): array =>
                        DateRangeFilterBuilder::getIndicators($data, 'Diperbarui')
                    ),

                TrashedFilter::make()
                    ->label('Data Terhapus'),
            ])
            ->recordActions([
                Action::make('goToApp')
                    ->label('Akses Aplikasi')
                    ->disabled(fn($record) => !$record->enabled)
                    ->action(function (Application $record) {
                        $redirectUri = is_array($record->redirect_uris)
                            ? ($record->redirect_uris[0] ?? null)
                            : $record->redirect_uris;

                        if (! is_string($redirectUri) || $redirectUri === '') {
                            Notification::make()
                                ->title('Redirect URL belum dikonfigurasi')
                                ->body('Aplikasi ini belum memiliki redirect URI yang valid.')
                                ->danger()
                                ->send();

                            return null;
                        }

                        return redirect()->away($redirectUri);
                    })
                    ->icon('heroicon-m-arrow-top-right-on-square'),
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Lihat Detail')
                        ->icon('heroicon-m-eye'),

                    EditAction::make()
                        ->label('Edit Aplikasi')
                        ->icon('heroicon-m-pencil-square'),

                    RelationManagerAction::make()
                        ->label('Kelola Role')
                        ->icon('heroicon-o-shield-check')
                        ->color('info')
                        ->slideOver()
                        ->modalWidth('7xl')
                        ->relationManager(RolesRelationManager::make()),
                ])
                    ->button()
                    ->label('Kelola')
                    ->icon('heroicon-m-cog-6-tooth')
                    ->color('gray'),

                ActionGroup::make([
                    Action::make('toggleEnabled')
                        ->label(fn(Application $record): string => $record->enabled ? 'Nonaktifkan App' : 'Aktifkan App')
                        ->icon(fn(Application $record): string => $record->enabled
                            ? 'heroicon-m-lock-closed'
                            : 'heroicon-m-lock-open')
                        ->color(fn(Application $record): string => $record->enabled ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->modalHeading(fn(Application $record): string => $record->enabled
                            ? 'Nonaktifkan Aplikasi?'
                            : 'Aktifkan Aplikasi?')
                        ->modalDescription(fn(Application $record): string => $record->enabled
                            ? 'Akses aplikasi ini akan dibatasi setelah dinonaktifkan.'
                            : 'Aplikasi ini akan dapat digunakan kembali setelah diaktifkan.')
                        ->modalSubmitActionLabel(fn(Application $record): string => $record->enabled
                            ? 'Nonaktifkan'
                            : 'Aktifkan')
                        ->action(function (Application $record): void {
                            $record->forceFill([
                                'enabled' => !$record->enabled,
                            ])->save();

                            Notification::make()
                                ->title($record->enabled ? 'Aplikasi berhasil diaktifkan' : 'Aplikasi berhasil dinonaktifkan')
                                ->success()
                                ->send();
                        }),

                    Action::make('syncRoles')
                        ->label('Sinkron Role')
                        ->icon('heroicon-o-arrow-path')
                        ->color('info')
                        ->action(function (Application $record): void {
                            $orchestrator = app(ApplicationSyncOrchestrator::class);
                            $result = $orchestrator->syncRoles($record);

                            Notification::make()
                                ->title($result['success'] ? 'Role berhasil disinkronkan' : 'Sinkronisasi role gagal')
                                ->body($orchestrator->formatSyncResult($result))
                                ->color($result['success'] ? 'success' : 'danger')
                                ->send();
                        }),

                    Action::make('syncUsers')
                        ->label('Sinkron Pengguna')
                        ->icon('heroicon-o-user-group')
                        ->color('primary')
                        ->requiresConfirmation()
                        ->modalHeading('Sinkron Pengguna Aplikasi')
                        ->modalDescription('Sistem akan menjadwalkan sinkronisasi pengguna untuk aplikasi ini.')
                        ->modalSubmitActionLabel('Mulai Sinkron')
                        ->action(function (Application $record): void {
                            $orchestrator = app(ApplicationSyncOrchestrator::class);
                            $orchestrator->syncUsers($record);

                            Notification::make()
                                ->title('Sinkronisasi pengguna dijadwalkan')
                                ->success()
                                ->send();
                        }),
                ])
                    ->label('Sinkronisasi')
                    ->icon('heroicon-m-arrow-path')
                    ->button()
                    ->color('primary'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Hapus'),

                    RestoreBulkAction::make()
                        ->label('Pulihkan'),

                    ForceDeleteBulkAction::make()
                        ->label('Hapus Permanen'),
                ])
                    ->label('Aksi Massal'),
            ]);
    }
}