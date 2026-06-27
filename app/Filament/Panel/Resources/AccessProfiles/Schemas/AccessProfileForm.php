<?php

namespace App\Filament\Panel\Resources\AccessProfiles\Schemas;

use App\Domain\Iam\Models\ApplicationRole;
use App\Rules\UniqueRolePerApplication;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Filament\Forms\Components\CheckboxList;

class AccessProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(1)
                    ->columnSpanFull()
                    ->schema([
                        Section::make('Bundle Identity')
                            ->description('Create a named bundle that combines related roles from different applications. This bundle can be assigned to users as a single unit.')
                            ->schema([
                                TextInput::make('key_hash')
                                    ->label('Identifier')
                                    ->maxLength(64)
                                    ->disabled()
                                    ->hidden()
                                    ->copyable()
                                    ->dehydrated(false)
                                    ->helperText('Generated automatically once and cannot be changed.')
                                    ->prefixIcon('heroicon-m-finger-print'),

                                Grid::make(2)->schema([
                                    TextInput::make('name')
                                        ->label('Bundle Name')
                                        ->required()
                                        ->prefixIcon('heroicon-m-users')
                                        ->maxLength(255)
                                        ->columnSpanFull()
                                        ->placeholder('Example: Quality Team, Hospital Management, IT Support')
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (string $operation, $state, Set $set, Get $get): void {
                                            // Auto generate slug hanya saat create dan slug masih kosong.
                                            if ($operation !== 'create') {
                                                return;
                                            }

                                            if (filled($get('slug'))) {
                                                return;
                                            }

                                            $set('slug', Str::slug((string) $state, '_'));
                                        }),

                                    TextInput::make('slug')
                                        ->label('Bundle Slug')
                                        ->required()
                                        ->hidden()
                                        ->maxLength(64)
                                        ->rules(['regex:/^[a-z0-9\-_]+$/'])
                                        ->placeholder('quality_team, manajemen_rs, it_support')
                                        ->helperText('Used internally by the IAM system. Lowercase letters, numbers, dashes and underscores only.')
                                        ->dehydrateStateUsing(fn(string $state): string => Str::lower($state))
                                        ->prefixIcon('heroicon-m-finger-print'),
                                ]),

                                Grid::make([
                                    'default' => 1,
                                    'md' => 2,
                                ])
                                    ->schema([
                                        ToggleButtons::make('is_system')
                                            ->label('System Bundle')
                                            ->boolean()
                                            ->inline()
                                            ->grouped()
                                            ->default(false)
                                            ->icons([
                                                true => 'heroicon-m-shield-check',
                                                false => 'heroicon-m-user',
                                            ])
                                            ->colors([
                                                true => 'warning',
                                                false => 'gray',
                                            ])
                                            ->helperText('System bundles are intended for critical system access and should only be managed by privileged administrators.'),

                                        ToggleButtons::make('is_active')
                                            ->label('Profile Status')
                                            ->boolean()
                                            ->inline()
                                            ->grouped()
                                            ->default(true)
                                            ->icons([
                                                true => 'heroicon-m-check-circle',
                                                false => 'heroicon-m-pause-circle',
                                            ])
                                            ->colors([
                                                true => 'success',
                                                false => 'gray',
                                            ])
                                            ->helperText('Inactive profiles cannot be assigned to new users, but existing assignments remain unaffected.'),
                                    ]),
                            ]),

                        Section::make('Included Roles')
                            ->description('Pilih role aplikasi yang akan dimasukkan ke bundle ini.')
                            ->schema(function () {
                                $apps = \App\Domain\Iam\Models\Application::with('roles')->get();
                                $fields = [];
                                foreach ($apps as $app) {
                                    if ($app->roles->isEmpty()) {
                                        continue;
                                    }
                                    
                                    $fields[] = ToggleButtons::make("app_roles.{$app->id}")
                                        ->label($app->name)
                                        ->options($app->roles->pluck('name', 'id')->toArray())
                                        ->inline()
                                        ->helperText('Pilih maksimal satu role untuk aplikasi ini.');
                                }
                                return $fields;
                            })
                            ->columns(1),

                        Section::make('Documentation')
                            ->description('Brief documentation about the purpose, scope, and who uses this bundle.')
                            ->schema([
                                Textarea::make('description')
                                    ->label('Description')
                                    ->rows(4)
                                    ->maxLength(1000)
                                    ->placeholder('Example: Bundle for hospital quality team, with access to SIIMUT (admin) and Incident Reporter (viewer).')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
