<?php

namespace App\Filament\Panel\Resources\Users\Tables;

use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Guava\FilamentModalRelationManagers\Actions\RelationManagerAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use App\Filament\Panel\Resources\Users\RelationManagers\AccessProfilesRelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Manajemen Pengguna')
            ->description('Kelola akun IAM, hak akses aplikasi, dan status keamanan pengguna.')
            ->defaultSort('updated_at', 'desc')
            ->poll('2s')
            ->defaultPaginationPageOption(25)
            ->striped()
            ->persistFiltersInSession()
            ->persistSearchInSession()
            ->headerActions(self::headerActions())
            ->searchPlaceholder('Cari nama, NIP, atau email pengguna...')
            ->columns([

                // Nama + nip + email
                TextColumn::make('name')
                    ->label('Pengguna')
                    ->weight('semibold')
                    ->description(fn(User $record) => $record->nip)
                    ->icon('heroicon-m-user-circle')
                    ->searchable(['name', 'nip', 'email'])
                    ->sortable()
                    ->toggleable(),

                // UNIT KERJA - OPTIMIZED: Use eager loaded relationship
                TextColumn::make('unit_kerja')
                    ->label('Unit Kerja')
                    ->getStateUsing(function (User $record): ?string {
                        // Uses pre-loaded unitKerjas relationship from eager loading
                        $unitKerjas = $record->relationLoaded('unitKerjas')
                            ? $record->unitKerjas->pluck('unit_name')->toArray()
                            : $record->unitKerjas()->pluck('unit_name')->toArray();

                        if (empty($unitKerjas)) {
                            return null;
                        }

                        return collect($unitKerjas)->implode(', ');
                    })
                    ->weight('semibold')
                    ->color('slate')
                    ->tooltip('Unit kerja yang menjadi tempat tugas pengguna.')
                    ->wrap()
                    ->placeholder('Belum ada unit kerja')
                    ->toggleable(),

                // DAFTAR APLIKASI YANG BISA DIAKSES - OPTIMIZED: Use cache and eager loading
                TextColumn::make('accessible_apps')
                    ->label('Role Bundles')
                    ->getStateUsing(function (User $record): ?string {
                        // Uses cached relationship method
                        $apps = $record->relationLoaded('accessProfiles')
                            ? $record->accessProfiles->where('is_active', true)->pluck('name')->toArray()
                            : $record->accessProfiles()->where('is_active', true)->pluck('name')->toArray();

                        if (empty($apps)) {
                            return null;
                        }

                        return collect($apps)
                            ->map(fn(string $appKey) => strtoupper($appKey))
                            ->implode(' • ');
                    })
                    ->badge()
                    ->color('info')
                    ->tooltip('Daftar aplikasi yang dapat diakses pengguna melalui IAM.')
                    ->wrap()
                    ->placeholder('Tidak ada akses aplikasi')
                    ->toggleable(),

                // RINGKASAN IAM (jumlah aplikasi & profil) - OPTIMIZED
                TextColumn::make('iam_summary')
                    ->label('Ringkasan IAM')
                    ->getStateUsing(function (User $record): ?string {
                        $profilesCount = $record->relationLoaded('accessProfiles')
                            ? $record->accessProfiles->count()
                            : $record->accessProfiles()->count();

                        if ($profilesCount === 0) {
                            return null;
                        }

                        // Use accessibleApps() which is cached
                        $apps = $record->accessibleApps();

                        return sprintf('%d aplikasi • %d profil akses', count($apps), $profilesCount);
                    })
                    ->badge()
                    ->color('primary')
                    ->tooltip('Ringkasan jumlah aplikasi terhubung dan profil akses global pengguna.')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->label('Status Pengguna')
                    ->badge()
                    ->color(fn(User $record) => match ($record->status) {
                        'active' => 'success',
                        'inactive' => 'warning',
                        'suspended' => 'danger',
                        default => 'secondary',
                    })
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('phone_number')
                    ->label('Telepon')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),

                // LOGIN AKTIF
                TextColumn::make('session_active')
                    ->label('Login Aktif')
                    ->badge()
                    ->color(function (User $record) {
                        if ($record->last_login_at === null && $record->last_logout_at === null) {
                            return 'secondary';
                        }

                        $start = $record->getActiveSessionLastActivity();
                        $end = $record->getActiveSessionExpiresAt();
                        $now = Carbon::now(config('app.timezone'));

                        return ($start && $end && $now->between($start, $end))
                            ? 'success'
                            : 'warning';
                    })
                    ->getStateUsing(function (User $record) {
                        if ($record->last_login_at === null && $record->last_logout_at === null) {
                            return 'Tidak login';
                        }

                        $start = $record->getActiveSessionLastActivity();
                        $end = $record->getActiveSessionExpiresAt();
                        $now = Carbon::now(config('app.timezone'));

                        return ($start && $end && $now->between($start, $end))
                            ? 'Online'
                            : 'Offline';
                    })
                    ->description(function (User $record) {
                        if ($record->last_login_at === null && $record->last_logout_at === null) {
                            return 'Pengguna belum pernah login';
                        }

                        if (! $record->hasActiveSession()) {
                            return 'Tidak ada sesi login aktif';
                        }

                        $start = $record->getActiveSessionLastActivity();
                        $end = $record->getActiveSessionExpiresAt();
                        $now = Carbon::now(config('app.timezone'));

                        if (! $start || ! $end) {
                            return 'Tidak ada sesi login aktif';
                        }

                        $start = $start->copy()->setTimezone(config('app.timezone'));
                        $end = $end->copy()->setTimezone(config('app.timezone'));

                        if (! $now->between($start, $end)) {
                            return sprintf('Sesi sudah berakhir pada %s', $end->format('H:i'));
                        }

                        $remainingMinutes = $now->diffInMinutes($end, false);
                        $remainingText = $remainingMinutes > 0
                            ? ($remainingMinutes >= 60
                                ? intval($remainingMinutes / 60) . ' jam' . ($remainingMinutes % 60 ? ' ' . ($remainingMinutes % 60) . ' menit' : '')
                                : $remainingMinutes . ' menit')
                            : 'kurang dari 1 menit';

                        return sprintf(
                            'Login sejak %s • berakhir %s • tersisa %s',
                            $start->format('H:i'),
                            $end->format('H:i'),
                            $remainingText,
                        );
                    })
                    ->tooltip(function (User $record) {
                        if ($record->last_login_at === null && $record->last_logout_at === null) {
                            return 'Pengguna belum pernah login';
                        }

                        if (! $record->hasActiveSession()) {
                            return 'Tidak ada sesi login aktif';
                        }

                        $start = $record->getActiveSessionLastActivity();
                        $end = $record->getActiveSessionExpiresAt();

                        if (! $start || ! $end) {
                            return 'Tidak ada sesi login aktif';
                        }

                        $start = $start->copy()->setTimezone(config('app.timezone'));
                        $end = $end->copy()->setTimezone(config('app.timezone'));

                        return sprintf('%s - %s (WIB)', $start->format('H:i'), $end->format('H:i'));
                    })
                    ->toggleable(),

                TextColumn::make('last_login_at')
                    ->label('Login Terakhir')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('last_logout_at')
                    ->label('Logout Terakhir')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                // MFA / TWO FACTOR
                IconColumn::make('mfa_enabled')
                    ->label('MFA')
                    ->boolean()
                    ->getStateUsing(fn(User $record) => ! empty($record->two_factor_secret ?? null))
                    ->trueIcon('heroicon-m-lock-closed')
                    ->falseIcon('heroicon-m-lock-open')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn(User $record) => ! empty($record->two_factor_secret ?? null) ? 'MFA aktif' : 'MFA tidak aktif')
                    ->toggleable(isToggledHiddenByDefault: true),

                // UPDATED AT
                TextColumn::make('updated_at')
                    ->label('Diperbarui')
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->color('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                    ->query(function (Builder $query, array $data) {

                        // STATUS
                        $query->when(
                            $data['status'] ?? null,
                            fn($q, $value) => $q->where('status', $value)
                        );

                        // MFA
                        $query->when(
                            $data['mfa'] ?? null,
                            fn($q, $value) => $value === 'enabled'
                                ? $q->whereNotNull('two_factor_secret')
                                : $q->whereNull('two_factor_secret')
                        );

                        // ROLE
                        $query->when(
                            $data['access_profiles'] ?? null,
                            fn($q, $value) => $q->whereHas(
                                'accessProfiles',
                                fn($sub) => $sub->whereIn('access_profiles.id', $value)
                            )
                        );

                        // UNIT KERJA
                        $query->when(
                            $data['unit_kerjas'] ?? null,
                            fn($q, $value) => $q->whereHas(
                                'unitKerjas',
                                fn($sub) => $sub->whereIn('unit_kerja.id', $value)
                            )
                        );

                        // LOGIN DATE
                        $query
                            ->when(
                                $data['login_from'] ?? null,
                                fn($q, $date) => $q->whereDate('last_login_at', '>=', $date)
                            )
                            ->when(
                                $data['login_until'] ?? null,
                                fn($q, $date) => $q->whereDate('last_login_at', '<=', $date)
                            );

                        // CREATED DATE
                        $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn($q, $date) => $q->whereDate('created_at', '>=', $date)
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn($q, $date) => $q->whereDate('created_at', '<=', $date)
                            );

                        // QUICK FILTERS
                        $query->when(
                            $data['quick_filter'] ?? null,
                            function ($q, $value) {

                                match ($value) {

                                    'secure_users' => $q
                                        ->where('status', 'active')
                                        ->whereNotNull('two_factor_secret')
                                        ->whereHas('accessProfiles'),

                                    'unused_accounts' => $q
                                        ->where('status', 'active')
                                        ->whereNull('last_login_at')
                                        ->where('created_at', '<', now()->subDays(30)),

                                    default => null,
                                };
                            }
                        );

                        return $query;
                    })
                    ->columnSpanFull(),

            ], layout: FiltersLayout::Modal)
            ->filtersFormWidth('7xl')
            ->filtersFormColumns(2)
            ->recordActions([
                // ImpersonateTableAction::make()
                //     ->label('Impersonate')
                //     ->icon('heroicon-m-arrow-right-on-rectangle')
                //     ->visible(fn(User $record) => Auth::id() !== $record->id),
                RelationManagerAction::make()
                    ->label('Manage Role Bundles')
                    ->icon('heroicon-o-user-group')
                    ->color('info')
                    ->slideOver()
                    ->modalWidth('7xl')
                    ->relationManager(AccessProfilesRelationManager::make()),
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Detail')
                        ->icon('heroicon-m-eye'),

                    EditAction::make()
                        ->label('Edit'),

                    Action::make('setStatus')
                        ->label('Ubah Status')
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
                        ->modalHeading('Ubah Status Pengguna')
                        ->modalDescription('Pilih status yang diinginkan untuk pengguna ini.'),

                    Action::make('terminateSession')
                        ->label('Hapus Sesi')
                        ->icon('heroicon-m-arrow-left-end-on-rectangle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->visible(fn(User $record) => $record->hasActiveSession())
                        ->action(function (User $record) {
                            $deleted = $record->terminateSessions();

                            Notification::make()
                                ->title($deleted ? 'Sesi login pengguna dihapus' : 'Tidak ditemukan sesi aktif')
                                ->success()
                                ->send();
                        }),
                ]),
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

                    return Storage::disk('local')->download(
                        $relativePath,
                        'users.json',
                        ['Content-Type' => 'application/json']
                    );
                })
                // ->visible(fn() => Gate::allows('export', User::class)),
        ];
    }
}
