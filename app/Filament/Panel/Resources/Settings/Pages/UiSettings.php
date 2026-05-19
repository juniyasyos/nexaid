<?php

namespace App\Filament\Panel\Resources\Settings\Pages;

use App\Filament\Panel\Resources\Settings\SettingResource;
use App\Services\SettingService;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UiSettings extends Page
{
    protected static string $resource = SettingResource::class;

    protected static ?string $title = 'UI Settings';

    protected string $view = 'filament.panel.resources.settings.pages.ui-settings';

    public ?array $data = [];

    public function mount(SettingService $settingService): void
    {
        $this->data = [
            'login_view' => $settingService->get('login_view', 'default'),
        ];

        $this->form->fill($this->data);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->columns(1)
            ->components([
                Section::make('UI Settings')
                    ->description('Manage UI login view selection. Only one variant can be active.')
                    ->icon('heroicon-o-palette')
                    ->schema([
                        Fieldset::make('Login Page Variant')
                            ->schema([
                                Radio::make('login_view')
                                    ->label('Login page variant')
                                    ->options([
                                        'default' => 'Default',
                                        'type1' => 'Type 1',
                                    ])
                                    ->inline(false)
                                    ->required(),
                            ])
                            ->columns(1),
                    ])
            ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();

        app(SettingService::class)->set('login_view', $state['login_view'] ?? 'default');

        Notification::make()
            ->title('UI settings saved')
            ->body('Login view selection updated.')
            ->success()
            ->send();
    }

    protected function getViewData(): array
    {
        return [
            'hasFields' => true,
        ];
    }
}
