<?php

namespace App\Filament\Panel\Resources\Settings;

use App\Filament\Panel\Resources\Settings\Pages\CompanySettings;
use App\Filament\Panel\Resources\Settings\Pages\AuthSettings;
use App\Filament\Panel\Resources\Settings\Pages\FortifySettings;
use App\Filament\Panel\Resources\Settings\Pages\IamSettings;
use App\Filament\Panel\Resources\Settings\Pages\SettingsHome;
use App\Filament\Panel\Resources\Settings\Pages\SsoSettings;
use App\Filament\Panel\Resources\Settings\Pages\EditSetting;
use App\Filament\Panel\Resources\Settings\Pages\UiSettings;
use App\Filament\Panel\Resources\Settings\Pages\ListSettings;
use App\Filament\Panel\Resources\Settings\Schemas\SettingForm;
use App\Filament\Panel\Resources\Settings\Tables\SettingsTable;
use App\Models\Setting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    protected static ?int $navigationSort = 90;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $modelLabel = 'Setting';

    protected static ?string $pluralModelLabel = 'Settings';

    protected static ?string $recordTitleAttribute = 'key';

    public static function form(Schema $schema): Schema
    {
        return SettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SettingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => SettingsHome::route('/'),
            'company' => CompanySettings::route('/company'),
            'sso' => SsoSettings::route('/sso'),
            'ui' => UiSettings::route('/ui'),
            'iam' => IamSettings::route('/iam'),
            'auth' => AuthSettings::route('/auth'),
            'fortify' => FortifySettings::route('/fortify'),
            'edit' => EditSetting::route('/{record}/edit'),
        ];
    }
}
