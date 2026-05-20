<?php

namespace App\Filament\Panel\Resources\Settings\Pages;

use App\Filament\Panel\Resources\Settings\SettingResource;
use App\Services\SettingService;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Throwable;

class UiSettings extends Page
{
    private const LOGIN_VIEW_IMAGES = [
        'default' => 'login-page/default.jpeg',
        'type1' => 'login-page/login-type-1.jpeg',
        'type2' => 'login-page/login-type-2.jpeg',
    ];

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
                    ->icon('heroicon-o-computer-desktop')
                    ->schema([
                    ((function () {
                        $imgClass = '\\Alkoumi\\FilamentImageRadioButton\\Forms\\Components\\ImageRadioGroup';

                        if (class_exists($imgClass)) {
                            $imageDisk = $this->resolveLoginViewImageDisk();

                            return $imgClass::make('login_view')
                                ->label('')
                                ->disk($imageDisk)
                                ->options(self::LOGIN_VIEW_IMAGES)
                                ->gridColumns(2)
                                ->required();
                        }

                        return ToggleButtons::make('login_view')
                            ->label('')
                            ->options([
                                'default' => 'Default',
                                'type1' => 'Type 1',
                                'type2' => 'Type 2',
                            ])
                            ->inline()
                            ->required();
                    })()),
                    ])
            ]);
    }

    private function resolveLoginViewImageDisk(): string
    {
        if ($this->allLoginViewImagesExistOnDisk('s3')) {
            return 's3';
        }

        return 's3';
    }

    private function allLoginViewImagesExistOnDisk(string $disk): bool
    {
        try {
            $filesystem = Storage::disk($disk);

            foreach (self::LOGIN_VIEW_IMAGES as $path) {
                if (! $filesystem->exists($path)) {
                    return false;
                }
            }

            return true;
        } catch (Throwable) {
            return false;
        }
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
