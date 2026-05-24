<?php

namespace App\Filament\Panel\Resources\UnitKerjas\Tables;

use App\Filament\Panel\Resources\UnitKerjas\RelationManagers\UsersRelationUnitKerjaManager;
use App\Filament\Panel\Resources\UnitKerjas\UnitKerjaResource;
use App\Models\UnitKerja;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Guava\FilamentModalRelationManagers\Actions\RelationManagerAction;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UnitKerjasTable
{
    public static function columns(): array
    {
        return [
            TextColumn::make('unit_name')
                ->label(__('filament-forms::unit-kerja.fields.unit_name'))
                ->description(fn(UnitKerja $record) => Str::limit($record->description, 100))
                ->wrap()
                ->grow()
                ->weight(FontWeight::Bold)
                ->searchable()
        ];
    }

    public static function filters(): array
    {
        return [
            TrashedFilter::make()
                ->default('with'),
        ];
    }

    public static function actions(): array
    {
        return [
            RelationManagerAction::make('users')
                ->slideOver()
                ->label('Pegawai')
                ->icon('heroicon-o-user-group')
                ->color('success')
                ->relationManager(UsersRelationUnitKerjaManager::make()),

            ActionGroup::make([
                EditAction::make('edit')
                    ->label('Edit')
                    ->tooltip('Edit')
                    ->visible(fn($record) => UnitKerjaResource::isCrudAllowed() && method_exists($record, 'trashed') && !$record->trashed())
                    ->icon('heroicon-o-pencil-square'),

                RestoreAction::make('restore')
                    ->visible(
                        function ($record): bool {
                            static $canRestore = null;

                            $canRestore ??= UnitKerjaResource::isCrudAllowed() && Gate::allows('restore', UnitKerja::class);

                            return $canRestore
                                && method_exists($record, 'trashed')
                                && $record->trashed();
                        }
                    ),

                ForceDeleteAction::make('forceDelete')
                    ->requiresConfirmation()
                    ->visible(
                        function ($record): bool {
                            static $canForceDelete = null;

                            $canForceDelete ??= UnitKerjaResource::isCrudAllowed() && Gate::allows('forceDelete', UnitKerja::class);

                            return $canForceDelete
                                && method_exists($record, 'trashed')
                                && $record->trashed();
                        }
                    ),
            ])
                ->button()
                ->icon('heroicon-o-ellipsis-vertical')
                ->color('primary'),
        ];
    }

    public static function bulkActions(): array
    {
        return [
            BulkActionGroup::make([
                DeleteBulkAction::make()
                    ->label('Hapus'),

                RestoreBulkAction::make()
                    ->label('Pulihkan'),

                ForceDeleteBulkAction::make()
                    ->label('Hapus Permanen')
                    ->requiresConfirmation()
                    ->modalHeading('Hapus Permanen Data Terpilih')
                    ->modalDescription('Apakah Anda yakin ingin menghapus data ini secara permanen? Tindakan ini tidak dapat dibatalkan.')
                    ->modalSubmitActionLabel('Ya, Hapus Permanen')
                    ->visible(fn() => Gate::allows('forceDelete', UnitKerja::class)),
            ])->visible(fn() => UnitKerjaResource::isCrudAllowed() && Gate::any(['update_imut::category', 'create_imut::category'])),
        ];
    }

    public static function headerActions(): array
    {
        return [
            // ExportAction::make()->exporter(UnitKerjaExporter::class),

            Action::make('exportUnitKerjaJson')
                ->label('Unduh JSON')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $relativePath = 'exports/unit_kerja.json';

                    Artisan::call('unit-kerja:export-json', ['--path' => $relativePath]);

                    return Storage::disk('local')->download(
                        $relativePath,
                        'unit_kerja.json',
                        ['Content-Type' => 'application/json']
                    );
                })
        ];
    }
}
