<?php

namespace App\Filament\Panel\Resources\Applications;

use App\Filament\Panel\Resources\Applications\Pages\CreateApplication;
use App\Filament\Panel\Resources\Applications\Pages\EditApplication;
use App\Filament\Panel\Resources\Applications\Pages\ListApplications;
use App\Filament\Panel\Resources\Applications\Pages\ViewApplication;
use App\Filament\Panel\Resources\Applications\Schemas\ApplicationForm;
use App\Filament\Panel\Resources\Applications\Schemas\ApplicationInfolist;
use App\Filament\Panel\Resources\Applications\Tables\ApplicationsTable;
use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Services\ApplicationRoleSyncService;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ApplicationResource extends Resource
{
    protected static ?string $model = Application::class;

    protected static string | UnitEnum | null $navigationGroup = 'IAM Management';

    protected static ?int $navigationSort = 20;

    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-cube';

    protected static ?string $modelLabel = 'Application';

    protected static ?string $pluralModelLabel = 'Applications';

    protected static ?string $navigationLabel = 'Applications';

    public static function form(Schema $schema): Schema
    {
        return ApplicationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ApplicationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ApplicationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListApplications::route('/'),
            'create' => CreateApplication::route('/create'),
            'view' => ViewApplication::route('/{record}'),
            'edit' => EditApplication::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ])
            ->withCount(['roles', 'systemRoles'])
            ->with(['creator']);
    }
}
