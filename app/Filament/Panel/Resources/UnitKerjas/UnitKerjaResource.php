<?php

namespace App\Filament\Panel\Resources\UnitKerjas;

use App\Filament\Panel\Resources\UnitKerjas\Pages\CreateUnitKerja;
use App\Filament\Panel\Resources\UnitKerjas\Pages\EditUnitKerja;
use App\Filament\Panel\Resources\UnitKerjas\Pages\ListUnitKerjas;
use App\Filament\Panel\Resources\UnitKerjas\Schemas\UnitKerjaForm;
use App\Filament\Panel\Resources\UnitKerjas\Tables\UnitKerjasTable;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class UnitKerjaResource extends Resource
{
    protected static ?string $model = null;

    protected static ?string $slug = 'unit-kerjas';

    public static function getModel(): string
    {
        return config('manage-unit-kerja.model.unit_kerja', parent::getModel());
    }

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function getPermissionPrefixes(): array
    {
        return [
            'view',
            'view_any',
            'create',
            'update',
            'restore',
            'restore_any',
            'delete',
            'delete_any',
            'force_delete',
            'force_delete_any',
            'attach_user_to_unit_kerja',
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['unit_name'];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl(name: 'edit', parameters: ['record' => $record]);
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->unit_name;
    }

    public static function getLabel(): ?string
    {
        return __('filament-forms::unit-kerja.navigation.title');
    }

    public static function getPluralLabel(): ?string
    {
        return __('filament-forms::unit-kerja.navigation.plural');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('filament-forms::unit-kerja.navigation.group');
    }

    public static function isCrudAllowed(): bool
    {
        return (bool) config('iam.sync_unit_kerja', true);
    }

    public static function form(Schema $schema): Schema
    {
        return UnitKerjaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll('2s')
            ->columns(UnitKerjasTable::columns())
            ->filters(UnitKerjasTable::filters())
            ->actions(UnitKerjasTable::actions())
            ->bulkActions(UnitKerjasTable::bulkActions());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUnitKerjas::route('/'),
            'create' => CreateUnitKerja::route('/create'),
            'edit' => EditUnitKerja::route('/{record:slug}/edit'),
        ];
    }
}
