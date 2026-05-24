<?php

namespace App\Filament\Panel\Widgets;

use App\Domain\Iam\Models\AccessProfile;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfileRoleConfigurationMap extends BaseWidget
{
    protected static ?int $sort = 3;

    protected static ?string $heading = 'Role Bundles';
    protected static ?string $description = 'Konfigurasi paket role dan distribusi pengguna';

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        try {
            $profiles = DB::table('access_profiles')
                ->leftJoin('access_profile_role_iam_map', 'access_profiles.id', '=', 'access_profile_role_iam_map.access_profile_id')
                ->leftJoin('user_access_profiles', 'access_profiles.id', '=', 'user_access_profiles.access_profile_id')
                ->leftJoin('iam_roles', 'access_profile_role_iam_map.role_id', '=', 'iam_roles.id')
                ->select(
                    'access_profiles.id',
                    'access_profiles.name',
                    'access_profiles.is_system',
                    'access_profiles.is_active',
                    'iam_roles.name as role_name',
                    DB::raw('COUNT(DISTINCT user_access_profiles.user_id) as user_count')
                )
                ->groupBy('access_profiles.id', 'access_profiles.name', 'access_profiles.is_system', 'access_profiles.is_active', 'iam_roles.id', 'iam_roles.name')
                ->orderBy('access_profiles.name')
                ->get();

            // Aggregate: group by profile since we may have joins
            $aggregated = $profiles->groupBy('id')->map(function ($group) {
                $first = $group->first();
                $roles = $group->pluck('role_name')->filter()->toArray();
                $users = $group->first()->user_count ?? 0;

                return (object)[
                    'id' => $first->id,
                    'name' => $first->name,
                    'is_system' => $first->is_system,
                    'is_active' => $first->is_active,
                    'role_count' => count($roles),
                    'roles' => implode(', ', $roles),
                    'user_count' => $users,
                ];
            })->values();

            // Create query builder for table display
            $query = AccessProfile::query()
                ->select('id', 'name', 'is_system', 'is_active')
                ->orderBy('is_system', 'desc');

            return $table
                ->query($query)
                ->columns([
                    TextColumn::make('name')
                        ->label('Profile Name')
                        ->sortable(),

                    TextColumn::make('id')
                        ->label('Bundled Roles')
                        ->formatStateUsing(function ($state) use ($aggregated) {
                            $profile = $aggregated->firstWhere('id', $state);
                            return $profile?->roles ?: 'None';
                        })
                        ->limit(50),

                    TextColumn::make('id')
                        ->label('# Role(s)')
                        ->formatStateUsing(function ($state) use ($aggregated) {
                            $profile = $aggregated->firstWhere('id', $state);
                            return $profile?->role_count ?? 0;
                        })
                        ->alignment('center'),

                    TextColumn::make('id')
                        ->label('# User(s)')
                        ->formatStateUsing(function ($state) use ($aggregated) {
                            $profile = $aggregated->firstWhere('id', $state);
                            return $profile?->user_count ?? 0;
                        })
                        ->alignment('center'),

                    TextColumn::make('is_active')
                        ->label('Status')
                        ->badge()
                        ->getStateUsing(function ($record) {
                            return $record->is_active ? 'Active' : 'Inactive';
                        })
                        ->colors([
                            'success' => 'Active',
                            'danger' => 'Inactive',
                        ]),

                    TextColumn::make('is_system')
                        ->label('Type')
                        ->badge()
                        ->getStateUsing(function ($record) {
                            return $record->is_system ? 'System' : 'Custom';
                        })
                        ->colors([
                            'warning' => 'System',
                            'info' => 'Custom',
                        ]),
                ])
                ->defaultSort('name')
                ->description(static::$description)
                ->emptyStateHeading('No access profiles found')
                ->emptyStateDescription('Access profiles will appear here.')
                ->emptyStateIcon('heroicon-o-shield-exclamation');
        } catch (\Throwable $e) {
            Log::error('ProfileRoleConfigurationMap widget failed: ' . $e->getMessage(), ['exception' => $e]);

            // Return empty table on error
            return $table
                ->query(AccessProfile::query()->whereRaw('1=0'))
                ->columns([])
                ->emptyStateHeading('Unable to load profile configuration')
                ->emptyStateDescription('An error occurred while fetching profile data.')
                ->emptyStateIcon('heroicon-o-exclamation-triangle');
        }
    }
}
