<?php

namespace App\Filament\Panel\Resources\Users\RelationManagers;

use App\Domain\Iam\Models\AccessProfile;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AccessProfilesRelationManager extends RelationManager
{
    protected static string $relationship = 'accessProfiles';

    protected static ?string $title = 'Access Profiles';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Profile Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn($record) => $record->description),
                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->badge()
                    ->hidden()
                    ->color('gray')
                    ->fontFamily('mono')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->size('sm'),
                TextColumn::make('roles_count')
                    ->label('Roles Included')
                    ->counts('roles')
                    ->badge()
                    ->color('info')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                IconColumn::make('is_system')
                    ->label('System')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-pencil')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->tooltip(fn(bool $state): string => $state ? 'System profile (protected)' : 'Custom profile'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                TextColumn::make('pivot.created_at')
                    ->label('Assigned At')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Assign Profile')
                    ->modalHeading('Assign Access Profile to User')
                    ->modalDescription('Select an access profile to grant this user multiple application roles at once.')
                    ->preloadRecordSelect()
                    ->schema(fn(AttachAction $action): array => [
                        Select::make('recordId')
                            ->label('Access Profile')
                            ->options(AccessProfile::query()
                                ->where('is_active', true)
                                ->pluck('name', 'id'))
                            ->searchable(['name', 'slug'])
                            ->required()
                            ->native(false)
                            ->helperText('Only active profiles are shown.'),
                    ])
                    ->before(function (array $data) {
                        $ownerRecord = $this->getOwnerRecord();
                        $profileId = $data['recordId'] ?? null;

                        if ($ownerRecord && $profileId && $ownerRecord->accessProfiles()->where('access_profiles.id', $profileId)->exists()) {
                            \Filament\Notifications\Notification::make()
                                ->danger()
                                ->title('Duplicate profile assignment')
                                ->body('This access profile is already assigned to the user.')
                                ->send();

                            return false;
                        }

                        return $data;
                    })
                    ->after(function () {
                        $userId = Auth::id();
                        $user = $this->getOwnerRecord();

                        if ($userId && $user && method_exists($this, 'getRecord')) {
                            DB::table('user_access_profiles')
                                ->where('user_id', $user->id)
                                ->whereNull('assigned_by')
                                ->update(['assigned_by' => $userId]);
                        }

                        // Trigger sync to notify clients of profile assignment
                        if ($user instanceof \App\Models\User) {
                            $user->triggerSync('access_profile_assigned_via_filament');
                        }
                    }),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label('Remove')
                    ->requiresConfirmation()
                    ->modalHeading('Remove profile from user')
                    ->modalDescription('Are you sure you want to remove this access profile? The user will lose all associated application roles.')
                    ->after(function ($record) {
                        if (! ($record instanceof \App\Domain\Iam\Models\AccessProfile)) {
                            return;
                        }

                        $user = $this->getOwnerRecord();
                        if (! ($user instanceof \App\Models\User)) {
                            return;
                        }

                        $applications = $record->roles
                            ->pluck('application')
                            ->filter()
                            ->unique('id')
                            ->values()
                            ->all();

                        // Trigger sync to notify clients of profile removal
                        $user->triggerSync('access_profile_detached_via_filament');
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label('Remove Selected')
                        ->requiresConfirmation()
                        ->after(function ($records) {
                            $user = $this->getOwnerRecord();
                            if (! $user instanceof \App\Models\User) {
                                return;
                            }

                            $applications = collect($records)
                                ->filter(fn($record) => $record instanceof \App\Domain\Iam\Models\AccessProfile)
                                ->flatMap(fn($profile) => $profile->roles->pluck('application'))
                                ->filter()
                                ->unique('id')
                                ->values()
                                ->all();

                            // Trigger sync to notify clients of profile removals
                            $user->triggerSync('access_profiles_bulk_detached_via_filament');
                        }),
                ]),
            ])
            ->emptyStateHeading('No access profiles assigned')
            ->emptyStateDescription('Assign access profiles to grant this user multiple application roles efficiently.')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
