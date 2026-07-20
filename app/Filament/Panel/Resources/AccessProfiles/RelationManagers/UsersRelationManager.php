<?php

namespace App\Filament\Panel\Resources\AccessProfiles\RelationManagers;

use App\Models\User;
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
use Illuminate\Support\Facades\Log;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Assigned Users';

    protected static ?string $recordTitleAttribute = 'name';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('User Name')
                    ->searchable()
                    ->sortable()
                    ->description(fn($record) => $record->nip)
                    ->weight('bold'),
                TextColumn::make('pivot.assigned_by')
                    ->label('Assigned By')
                    ->state(function ($record) {
                        if ($record->pivot->assigned_by) {
                            return 'ID: ' . $record->pivot->assigned_by;
                        }
                        return 'System';
                    })
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('pivot.created_at')
                    ->label('Assigned At')
                    ->dateTime('M d, Y H:i')
                    ->sortable()
                    ->toggledHiddenByDefault(false),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Assign User')
                    ->modalHeading('Assign User to Profile')
                    ->modalDescription('Select users to assign this access profile.')
                    ->preloadRecordSelect()
                    ->schema(fn(AttachAction $action): array => [
                        Select::make('recordId')
                            ->label('User')
                            ->options(User::query()->where('status', 'active')->pluck('name', 'id'))
                            ->searchable(['name', 'email'])
                            ->required()
                            ->native(false)
                            ->helperText('Only active users are shown.'),
                    ])
                    ->after(function ($record, array $data) {
                        // Set assigned_by to current authenticated user
                        $userId = Auth::id();
                        if ($userId) {
                            DB::table('user_access_profiles')
                                ->where('user_id', $record->id)
                                ->where('access_profile_id', $this->getOwnerRecord()->id)
                                ->update(['assigned_by' => $userId]);
                        }
                    }),
            ])
            ->recordActions([
                DetachAction::make()
                    ->label('Remove')
                    ->requiresConfirmation()
                    ->modalHeading('Remove user from profile')
                    ->modalDescription('Are you sure you want to remove this user from the access profile?')
                    ->after(function ($record) {
                        if (!($record instanceof User)) {
                            return;
                        }

                        $profile = $this->getOwnerRecord();
                        $applications = $profile->roles
                            ->pluck('application')
                            ->filter()
                            ->unique('id')
                            ->values()
                            ->all();

                        Log::info('iam.access_profile.detach.user', [
                            'profile_id' => $profile->id,
                            'profile_slug' => $profile->slug,
                            'user_id' => $record->id,
                            'application_ids' => collect($applications)->pluck('id')->values()->all(),
                            'application_keys' => collect($applications)->pluck('app_key')->values()->all(),
                        ]);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()
                        ->label('Remove Selected')
                        ->requiresConfirmation()
                        ->after(function ($records) {
                            $profile = $this->getOwnerRecord();
                            $applications = $profile->roles
                                ->pluck('application')
                                ->filter()
                                ->unique('id')
                                ->values()
                                ->all();

                            foreach ($records as $record) {
                                if (!($record instanceof User)) {
                                    continue;
                                }

                                Log::info('iam.access_profile.detach.bulk.user', [
                                    'profile_id' => $profile->id,
                                    'profile_slug' => $profile->slug,
                                    'user_id' => $record->id,
                                    'application_ids' => collect($applications)->pluck('id')->values()->all(),
                                    'application_keys' => collect($applications)->pluck('app_key')->values()->all(),
                                ]);
                            }
                        }),
                ]),
            ])
            ->emptyStateHeading('No users assigned')
            ->emptyStateDescription('Assign users to this profile to grant them the associated application roles.')
            ->emptyStateIcon('heroicon-o-user-group');
    }
}
