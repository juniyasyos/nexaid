<?php

namespace App\Filament\Panel\Resources\Settings\Pages;

use App\Filament\Panel\Resources\Settings\SettingResource;
use App\Services\SettingService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class IamSettings extends Page
{
    protected static string $resource = SettingResource::class;

    protected static ?string $title = 'IAM Settings';

    protected string $view = 'filament.panel.resources.settings.pages.group-settings';

    public ?array $data = [];

    public function mount(SettingService $settingService): void
    {
        $this->data = [
            'iam_home_app_enabled' => $settingService->get('iam.home_app', [])['enabled'] ?? true,
            'iam_home_app_key' => $settingService->get('iam.home_app', [])['app_key'] ?? 'iam-home',
            'iam_home_app_name' => $settingService->get('iam.home_app', [])['name'] ?? 'IAM Home',
            'iam_home_app_description' => $settingService->get('iam.home_app', [])['description'] ?? 'Portal utama IAM',
            'iam_home_url' => $settingService->get('iam.home_app', [])['url'] ?? 'http://127.0.0.1:8010/',
            'iam_home_app_logo_url' => $settingService->get('iam.home_app', [])['logo_url'] ?? null,
            'iam_issuer' => $settingService->get('iam.issuer', env('IAM_ISSUER', env('APP_URL', 'https://iam.local'))),
            'iam_token_ttl' => $settingService->get('iam.token_ttl', 3600),
            'iam_unit_kerja_delete_soft' => $settingService->get('iam.unit_kerja_delete_soft', false),
            'iam_user_delete_soft' => $settingService->get('iam.user_delete_soft', false),
            'iam_push_deleted_records' => $settingService->get('iam.push_deleted_records', true),
            'iam_backchannel_verify' => $settingService->get('iam.backchannel_verify', true),
            'iam_backchannel_method' => $settingService->get('iam.backchannel_method', 'jwt'),
            'iam_algorithm' => $settingService->get('iam.algorithm', 'HS256'),
            'iam_refresh_token_ttl' => $settingService->get('iam.refresh_token_ttl', 86400 * 30),
            'iam_auth_code_ttl' => $settingService->get('iam.auth_code_ttl', 300),
            'iam_audience' => $settingService->get('iam.audience', null),
            'iam_protect_system_roles' => $settingService->get('iam.protect_system_roles', true),
            'iam_role_sync_mode' => $settingService->get('iam.role_sync_mode', 'push'),
            'iam_user_sync_mode' => $settingService->get('iam.user_sync_mode', 'push'),
            'iam_role_sync_from_client_allow_create' => $settingService->get('iam.role_sync_from_client_allow_create', false),
            'iam_role_sync_from_iam_allow_create' => $settingService->get('iam.role_sync_from_iam_allow_create', false),
            'iam_user_sync_from_iam_allow_create' => $settingService->get('iam.user_sync_from_iam_allow_create', true),
            'iam_user_sync_from_iam_delete_missing' => $settingService->get('iam.user_sync_from_iam_delete_missing', false),
            'iam_user_sync_force_pull' => $settingService->get('iam.user_sync_force_pull', false),
            'iam_user_sync_password_field' => $settingService->get('iam.user_sync_password_field', false),
            'iam_user_fields' => $settingService->get('iam.user_fields', 'id,name,nip,email,status'),
            'iam_sync_unit_kerja' => $settingService->get('iam.sync_unit_kerja', true),
            'iam_default_user_roles' => json_encode($settingService->get('iam.default_user_roles', []), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]',
            'iam_imports_delete_source_after_import' => $settingService->get('iam.imports', [])['delete_source_after_import'] ?? false,
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(1)
            ->components([
                Section::make('Home App')
                    ->description('Konfigurasi app default yang disisipkan ke respons aplikasi pengguna.')
                    ->columns(2)
                    ->schema([
                        Toggle::make('iam_home_app_enabled')
                            ->label('Enabled')
                            ->columnSpanFull(),
                        TextInput::make('iam_home_app_key')
                            ->label('App Key')
                            ->maxLength(255),
                        TextInput::make('iam_home_app_name')
                            ->label('Name')
                            ->maxLength(255),
                        TextInput::make('iam_home_url')
                            ->label('URL')
                            ->url()
                            ->columnSpanFull(),
                        TextInput::make('iam_home_app_logo_url')
                            ->label('Logo URL')
                            ->url()
                            ->columnSpanFull(),
                        Textarea::make('iam_home_app_description')
                            ->label('Description')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Token & Security')
                    ->description('Atur issuer, secret, algorithm, TTL, dan kontrol keamanan token.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('iam_issuer')
                            ->label('Issuer')
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('iam_token_ttl')
                            ->label('Token TTL (seconds)')
                            ->numeric(),
                        TextInput::make('iam_refresh_token_ttl')
                            ->label('Refresh TTL (seconds)')
                            ->numeric(),
                        TextInput::make('iam_auth_code_ttl')
                            ->label('Auth Code TTL (seconds)')
                            ->numeric(),
                        TextInput::make('iam_algorithm')
                            ->label('Algorithm')
                            ->maxLength(255),
                        TextInput::make('iam_audience')
                            ->label('Audience')
                            ->maxLength(255),
                        Toggle::make('iam_backchannel_verify')
                            ->label('Backchannel Verify'),
                        Select::make('iam_backchannel_method')
                            ->label('Backchannel Method')
                            ->options([
                                'jwt' => 'JWT',
                                'hmac' => 'HMAC',
                            ]),
                        Toggle::make('iam_protect_system_roles')
                            ->label('Protect System Roles'),
                    ]),
                Section::make('Sync Policy')
                    ->description('Kontrol perilaku sinkronisasi user, role, dan penghapusan data.')
                    ->columns(3)
                    ->schema([
                        Toggle::make('iam_unit_kerja_delete_soft')
                            ->label('Unit Kerja Soft Delete'),
                        Toggle::make('iam_user_delete_soft')
                            ->label('User Soft Delete'),
                        Toggle::make('iam_push_deleted_records')
                            ->label('Push Deleted Records'),
                        Select::make('iam_role_sync_mode')
                            ->label('Role Sync Mode')
                            ->options([
                                'pull' => 'Pull',
                                'push' => 'Push',
                            ]),
                        Select::make('iam_user_sync_mode')
                            ->label('User Sync Mode')
                            ->options([
                                'pull' => 'Pull',
                                'push' => 'Push',
                            ]),
                        Toggle::make('iam_role_sync_from_client_allow_create')
                            ->label('Role Create From Client'),
                        Toggle::make('iam_role_sync_from_iam_allow_create')
                            ->label('Role Create From IAM'),
                        Toggle::make('iam_user_sync_from_iam_allow_create')
                            ->label('User Create From IAM'),
                        Toggle::make('iam_user_sync_from_iam_delete_missing')
                            ->label('User Delete Missing From IAM'),
                        Toggle::make('iam_user_sync_force_pull')
                            ->label('Force Pull User Sync'),
                        Toggle::make('iam_user_sync_password_field')
                            ->label('Sync Password Field'),
                        Toggle::make('iam_sync_unit_kerja')
                            ->label('Enable Unit Kerja CRUD'),
                        TextInput::make('iam_user_fields')
                            ->label('User Fields')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
                Section::make('Import & Payload')
                    ->description('Pengaturan default user roles dan perilaku import file JSON.')
                    ->columns(1)
                    ->schema([
                        Textarea::make('iam_default_user_roles')
                            ->label('Default User Roles (JSON)')
                            ->rows(6)
                            ->columnSpanFull(),
                        Toggle::make('iam_imports_delete_source_after_import')
                            ->label('Delete Source After Import'),
                    ]),
            ]);
    }

    public function save(SettingService $settingService): void
    {
        $state = $this->form->getState();

        $settingService->set('iam.home_app', [
            'enabled' => (bool) ($state['iam_home_app_enabled'] ?? false),
            'app_key' => $state['iam_home_app_key'] ?? null,
            'name' => $state['iam_home_app_name'] ?? null,
            'description' => $state['iam_home_app_description'] ?? null,
            'url' => $state['iam_home_url'] ?? null,
            'logo_url' => $state['iam_home_app_logo_url'] ?? null,
        ]);
        $settingService->set('iam.issuer', $state['iam_issuer'] ?? null);
        $settingService->set('iam.token_ttl', $state['iam_token_ttl'] ?? null);
        $settingService->set('iam.unit_kerja_delete_soft', $state['iam_unit_kerja_delete_soft'] ?? null);
        $settingService->set('iam.user_delete_soft', $state['iam_user_delete_soft'] ?? null);
        $settingService->set('iam.push_deleted_records', $state['iam_push_deleted_records'] ?? null);
        $settingService->set('iam.backchannel_verify', $state['iam_backchannel_verify'] ?? null);
        $settingService->set('iam.backchannel_method', $state['iam_backchannel_method'] ?? null);
        $settingService->set('iam.algorithm', $state['iam_algorithm'] ?? null);
        $settingService->set('iam.refresh_token_ttl', $state['iam_refresh_token_ttl'] ?? null);
        $settingService->set('iam.auth_code_ttl', $state['iam_auth_code_ttl'] ?? null);
        $settingService->set('iam.audience', $state['iam_audience'] ?? null);
        $settingService->set('iam.protect_system_roles', $state['iam_protect_system_roles'] ?? null);
        $settingService->set('iam.role_sync_mode', $state['iam_role_sync_mode'] ?? null);
        $settingService->set('iam.user_sync_mode', $state['iam_user_sync_mode'] ?? null);
        $settingService->set('iam.role_sync_from_client_allow_create', $state['iam_role_sync_from_client_allow_create'] ?? null);
        $settingService->set('iam.role_sync_from_iam_allow_create', $state['iam_role_sync_from_iam_allow_create'] ?? null);
        $settingService->set('iam.user_sync_from_iam_allow_create', $state['iam_user_sync_from_iam_allow_create'] ?? null);
        $settingService->set('iam.user_sync_from_iam_delete_missing', $state['iam_user_sync_from_iam_delete_missing'] ?? null);
        $settingService->set('iam.user_sync_force_pull', $state['iam_user_sync_force_pull'] ?? null);
        $settingService->set('iam.user_sync_password_field', $state['iam_user_sync_password_field'] ?? null);
        $settingService->set('iam.user_fields', $state['iam_user_fields'] ?? null);
        $settingService->set('iam.sync_unit_kerja', $state['iam_sync_unit_kerja'] ?? null);

        $defaultUserRoles = json_decode((string) ($state['iam_default_user_roles'] ?? '[]'), true);
        $settingService->set('iam.default_user_roles', is_array($defaultUserRoles) ? $defaultUserRoles : []);

        $settingService->set('iam.imports', [
            'delete_source_after_import' => (bool) ($state['iam_imports_delete_source_after_import'] ?? false),
        ]);

        Notification::make()
            ->title('IAM settings saved')
            ->body('Only setting values were updated. Keys remain locked.')
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'eyebrow' => 'Settings',
            'pageHeading' => 'IAM Settings',
            'pageDescription' => 'Kelola home app, token, signing, sync policy, dan pengaturan import IAM.',
            'submitLabel' => 'Save IAM Values',
            'hasFields' => true,
        ];
    }
}