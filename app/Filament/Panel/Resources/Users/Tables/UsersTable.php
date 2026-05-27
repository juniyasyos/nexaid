<?php

namespace App\Filament\Panel\Resources\Users\Tables;

use App\Domain\Users\Services\UserSessionStateResolver;
use App\Domain\Users\Services\UserAccessSummaryFormatter;
use App\Domain\Users\Services\UserTableFilterBuilder;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Guava\FilamentModalRelationManagers\Actions\RelationManagerAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use App\Filament\Panel\Resources\Users\RelationManagers\AccessProfilesRelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Support\Facades\Artisan;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Manajemen Pengguna')
            ->description('Kelola akun IAM, hak akses aplikasi, dan status keamanan pengguna.')
            ->defaultSort('updated_at', 'desc')
            ->poll('30s')
            ->defaultPaginationPageOption(25)
            ->striped()
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->headerActions(self::headerActions())
            ->searchPlaceholder('Cari nama, NIP, atau email pengguna...')
            ->columns([
                Split::make([
                    ImageColumn::make('avatar_url')
                        ->label('')
                        ->circular()
                        ->grow(false)
                        ->getStateUsing(
                            fn(User $record) => $record->avatar_url
                            ?: 'https://ui-avatars.com/api/?name=' . urlencode($record->name)
                        ),

                    Stack::make([
                        TextColumn::make('name')
                            ->label('Pengguna')
                            ->weight('semibold')
                            ->searchable(['name'])
                            ->sortable(),

                        TextColumn::make('nip')
                            ->label('NIP')
                            ->icon('heroicon-m-finger-print')
                            ->color('gray')
                            ->size('sm')
                            ->searchable()
                            ->copyable()
                            ->copyMessage('NIP berhasil disalin!')
                            ->copyMessageDuration(1500),
                    ])
                        ->space(1)
                        ->grow(),

                    Stack::make([
                        TextColumn::make('access_profiles')
                            ->label('Role Bundle')
                            ->color('primary')
                            ->size('sm')
                            ->formatStateUsing(fn(?string $state) => strtoupper($state ?? '-'))
                            ->getStateUsing(function (User $record): ?string {
                                $profiles = $record->relationLoaded('accessProfiles')
                                    ? $record->accessProfiles->pluck('name')->toArray()
                                    : $record->accessProfiles()->pluck('name')->toArray();

                                return empty($profiles)
                                    ? null
                                    : collect($profiles)->implode(', ');
                            }),

                        TextColumn::make('unit_kerja')
                            ->label('Unit Kerja')
                            ->icon('heroicon-m-building-office-2')
                            ->color('gray')
                            ->size('md')
                            ->sortable()
                            ->formatStateUsing(fn(?string $state) => strtoupper($state ?? '-'))
                            ->getStateUsing(function (User $record): ?string {
                                $unitKerjas = $record->relationLoaded('unitKerjas')
                                    ? $record->unitKerjas->pluck('unit_name')->toArray()
                                    : $record->unitKerjas()->pluck('unit_name')->toArray();

                                return empty($unitKerjas)
                                    ? null
                                    : collect($unitKerjas)->implode(', ');
                            })
                            ->tooltip('Unit kerja pengguna.')
                            ->placeholder('Belum ada unit kerja')
                            ->wrap(),
                    ])
                        ->space(1),

                    Stack::make([
                        TextColumn::make('iam_summary')
                            ->label('Ringkasan IAM')
                            ->size('sm')
                            ->color('primary')
                            ->getStateUsing(
                                fn(User $record) => app(UserAccessSummaryFormatter::class)->format($record)
                            )
                            ->tooltip('Ringkasan jumlah aplikasi & profil akses.'),

                        TextColumn::make('status')
                            ->label('Status')
                            ->badge()
                            ->sortable()
                            ->formatStateUsing(fn(?string $state) => strtoupper($state ?? '-'))
                            ->color(fn(User $record) => match ($record->status) {
                                'active' => 'success',
                                'inactive' => 'warning',
                                'suspended' => 'danger',
                                default => 'gray',
                            }),
                    ])
                        ->space(1),

                    Stack::make([
                        TextColumn::make('session_active')
                            ->label('Login Aktif')
                            ->badge()
                            ->size('sm')
                            ->sortable()
                            ->color(fn(User $record) => app(UserSessionStateResolver::class)->getStatusColor($record))
                            ->getStateUsing(fn(User $record) => app(UserSessionStateResolver::class)->getStatus($record))
                            ->description(fn(User $record) => app(UserSessionStateResolver::class)->getDescription($record))
                            ->tooltip(fn(User $record) => app(UserSessionStateResolver::class)->getTooltip($record)),
                    ])
                        ->space(1),

                    // Stack::make([
                    //     TextColumn::make('last_login_at')
                    //         ->label('Login Terakhir')
                    //         ->icon('heroicon-m-clock')
                    //         ->color('gray')
                    //         ->size('sm')
                    //         ->dateTime('d M Y H:i')
                    //         ->sortable(),

                    //     TextColumn::make('last_logout_at')
                    //         ->label('Logout Terakhir')
                    //         ->icon('heroicon-m-arrow-left-on-rectangle')
                    //         ->color('gray')
                    //         ->size('sm')
                    //         ->dateTime('d M Y H:i')
                    //         ->sortable(),
                    // ])
                    //     ->space(1),

                ])
                    ->from('md'),
            ])
            ->filters([

                Filter::make('advanced_filters')
                    ->schema([

                        Section::make('Status & Keamanan')
                            ->description('Filter terkait status akun dan keamanan pengguna')
                            ->schema([

                                Grid::make(3)
                                    ->schema([

                                        Select::make('status')
                                            ->label('Status Pengguna')
                                            ->native(false)
                                            ->options([
                                                'active' => 'Aktif',
                                                'inactive' => 'Nonaktif',
                                                'suspended' => 'Ditangguhkan',
                                            ]),

                                        Select::make('session_status')
                                            ->label('Status Sesi')
                                            ->native(false)
                                            ->options([
                                                'online' => 'Online',
                                                'offline' => 'Offline',
                                                'never_logged_in' => 'Belum Pernah Login',
                                            ]),

                                        Select::make('mfa')
                                            ->label('MFA')
                                            ->native(false)
                                            ->options([
                                                'enabled' => 'MFA Aktif',
                                                'disabled' => 'MFA Tidak Aktif',
                                            ]),
                                    ]),
                            ])
                            ->columns(1)
                            ->collapsible(),

                        Section::make('Relasi & Hak Akses')
                            ->description('Filter role bundle dan unit kerja')
                            ->schema([

                                Grid::make(2)
                                    ->schema([

                                        Select::make('access_profiles')
                                            ->label('Role / Access Profile')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->relationship('accessProfiles', 'name'),

                                        Select::make('unit_kerjas')
                                            ->label('Unit Kerja')
                                            ->multiple()
                                            ->searchable()
                                            ->preload()
                                            ->relationship('unitKerjas', 'unit_name'),
                                    ]),
                            ])
                            ->collapsible(),

                        Section::make('Rentang Waktu')
                            ->description('Filter berdasarkan tanggal aktivitas pengguna')
                            ->schema([

                                Grid::make(2)
                                    ->schema([

                                        DatePicker::make('login_from')
                                            ->label('Login Dari'),

                                        DatePicker::make('login_until')
                                            ->label('Login Sampai'),

                                        DatePicker::make('created_from')
                                            ->label('Dibuat Dari'),

                                        DatePicker::make('created_until')
                                            ->label('Dibuat Sampai'),
                                    ]),
                            ])
                            ->collapsible(),

                        Section::make('Quick Filters')
                            ->description('Filter cepat untuk identifikasi akun tertentu')
                            ->schema([

                                Grid::make(2)
                                    ->schema([

                                        Select::make('quick_filter')
                                            ->label('Preset Filter')
                                            ->native(false)
                                            ->options([
                                                'secure_users' => 'Pengguna Aman',
                                                'unused_accounts' => 'Akun Tidak Digunakan',
                                            ]),
                                    ]),
                            ])
                            ->collapsible(),

                    ])
                    ->query(fn(Builder $query, array $data): Builder => app(UserTableFilterBuilder::class)->apply($query, $data))
                    ->columnSpanFull(),

            ], layout: FiltersLayout::Modal)
            ->filtersFormWidth('7xl')
            ->filtersFormColumns(2)
            ->recordActions([
                // ImpersonateTableAction::make()
                //     ->label('Impersonate')
                //     ->icon('heroicon-m-arrow-right-on-rectangle')
                //     ->visible(fn(User $record) => Auth::id() !== $record->id),
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Lihat Pengguna')
                        ->icon('heroicon-m-eye'),

                    EditAction::make()
                        ->label('Edit Informasi')
                        ->icon('heroicon-m-pencil-square'),

                    RelationManagerAction::make()
                        ->label('Kelola Role Bundle')
                        ->icon('heroicon-o-user-group')
                        ->color('info')
                        ->slideOver()
                        ->modalWidth('7xl')
                        ->relationManager(AccessProfilesRelationManager::make()),

                    Action::make('setStatus')
                        ->label('Atur Status Akun')
                        ->icon('heroicon-m-adjustments-horizontal')
                        ->schema([
                            Select::make('status')
                                ->label('Status Pengguna')
                                ->options([
                                    'active' => 'Aktif',
                                    'inactive' => 'Nonaktif',
                                    'suspended' => 'Ditangguhkan',
                                ])
                                ->required()
                                ->default(fn(User $record) => $record->status),
                        ])
                        ->action(function (User $record, array $data): void {
                            $record->update(['status' => $data['status']]);
                        })
                        ->modalHeading('Atur Status Akun')
                        ->modalDescription('Perbarui status akses untuk pengguna ini.'),

                    Action::make('terminateSession')
                        ->label('Paksa Logout')
                        ->icon('heroicon-m-arrow-left-end-on-rectangle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn(User $record) => $record->hasActiveSession())
                        ->action(function (User $record) {
                            $deleted = $record->terminateSessions();

                            Notification::make()
                                ->title(
                                    $deleted
                                    ? 'Sesi pengguna berhasil dihentikan'
                                    : 'Tidak ada sesi aktif'
                                )
                                ->success()
                                ->send();
                        }),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Aktifkan')
                        ->icon('heroicon-m-bolt')
                        ->color('success')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(fn(Collection $records) => $records->each->update(['status' => 'active'])),

                    BulkAction::make('deactivate')
                        ->label('Nonaktifkan')
                        ->icon('heroicon-m-no-symbol')
                        ->color('secondary')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(fn(Collection $records) => $records->each->update(['status' => 'inactive'])),

                    DeleteBulkAction::make()
                        ->label('Hapus')
                        ->icon('heroicon-m-trash'),
                ]),
            ])
            ->emptyStateIcon('heroicon-m-user-group')
            ->emptyStateHeading('Belum ada user')
            ->emptyStateDescription('Tambahkan user baru atau ubah filter pencarian untuk melihat data.');
    }

    public static function headerActions(): array
    {
        return [
            Action::make('exportUsersJson')
                ->label('Unduh JSON')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $relativePath = 'exports/users.json';

                    Artisan::call('users:export-json', ['--path' => $relativePath]);

                    return response()->download(
                        storage_path('app/' . $relativePath),
                        'users.json',
                        ['Content-Type' => 'application/json']
                    );
                })
            // ->visible(fn() => Gate::allows('export', User::class)),
        ];
    }
}
