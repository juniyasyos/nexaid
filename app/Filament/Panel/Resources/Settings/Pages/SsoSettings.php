<?php

namespace App\Filament\Panel\Resources\Settings\Pages;

use App\Filament\Panel\Resources\Settings\SettingResource;
use App\Services\SettingService;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SsoSettings extends Page
{
    protected static string $resource = SettingResource::class;

    protected static ?string $title = 'SSO Settings';

    protected string $view = 'filament.panel.resources.settings.pages.group-settings';

    public ?array $data = [];

    public function mount(SettingService $settingService): void
    {
        $this->data = [
            'sso_issuer' => $settingService->get('sso.issuer', env('SSO_ISSUER', env('APP_URL', 'iam-server'))),
            'sso_ttl' => $settingService->get('sso.ttl', 300),
            'sso_backchannel_signature_header' => $settingService->get('sso.backchannel.signature_header', 'IAM-Signature'),
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(1)
            ->components([
                Section::make('SSO Core')
                    ->description('Atur issuer, TTL token, dan header verifikasi SSO.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('sso_issuer')
                            ->label('Issuer')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        TextInput::make('sso_ttl')
                            ->label('TTL (seconds)')
                            ->numeric()
                            ->required(),
                        TextInput::make('sso_backchannel_signature_header')
                            ->label('Backchannel Signature Header')
                            ->maxLength(255),
                    ]),
            ]);
    }

    public function save(SettingService $settingService): void
    {
        $state = $this->form->getState();

        $settingService->set('sso.issuer', $state['sso_issuer'] ?? null);
        $settingService->set('sso.ttl', $state['sso_ttl'] ?? null);
        $settingService->set('sso.backchannel.signature_header', $state['sso_backchannel_signature_header'] ?? null);

        Notification::make()
            ->title('SSO settings saved')
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
            'pageHeading' => 'SSO Settings',
            'pageDescription' => 'Kelola issuer, TTL token, dan header verifikasi untuk SSO.',
            'submitLabel' => 'Save SSO Values',
            'hasFields' => true,
        ];
    }
}