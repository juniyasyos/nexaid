<?php

namespace App\Filament\Panel\Widgets;

use App\Domain\Iam\Models\Application;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class ApplicationAccessSummary extends BaseWidget
{
    protected static ?string $heading = 'Application Access';
    protected static ?string $description = 'Monitor aplikasi yang terhubung dengan Auth Server';

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        $query = Application::query()
            ->withCount('roles')
            ->with(['roles.users', 'roles.accessProfiles.users']);

        return $table
            ->query($query)
            ->columns([
                TextColumn::make('name')
                    ->label('Application Name')
                    ->sortable()
                    ->wrap(),

                TextColumn::make('app_key')
                    ->label('App Key')
                    ->sortable()
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('users_count')
                    ->label('Users')
                    ->getStateUsing(fn(Application $record): int => $this->getUsersCount($record))
                    ->alignment('center'),

                TextColumn::make('roles_count')
                    ->label('Roles')
                    ->counts('roles')
                    ->sortable()
                    ->alignment('center'),

                TextColumn::make('enabled')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn(Application $record) => $record->enabled ? 'Enabled' : 'Disabled')
                    ->colors([
                        'success' => 'Enabled',
                        'danger' => 'Disabled',
                    ]),
            ])
            ->description(static::$description)
            ->defaultSort('name')
            ->paginated([10]);
    }

    private function getUsersCount(Application $application): int
    {
        $directUsers = $application->roles
            ->flatMap(fn($role) => $role->users->pluck('id'));

        $profileUsers = $application->roles
            ->flatMap(fn($role) => $role->accessProfiles)
            ->filter(fn($profile) => $profile->is_active)
            ->flatMap(fn($profile) => $profile->users->pluck('id'));

        return $directUsers
            ->merge($profileUsers)
            ->unique()
            ->count();
    }
}
