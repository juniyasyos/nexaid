<?php

namespace App\Filament\Panel\Resources\UnitKerjas\Pages;

use App\Actions\ImportUnitKerjasFromJsonAction;
use App\Filament\Panel\Resources\UnitKerjas\UnitKerjaResource;
use App\Jobs\PushUnitKerjaToClient;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListUnitKerjas extends ListRecords
{
    protected static string $resource = UnitKerjaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Tambah Data')
                ->icon('heroicon-m-plus'),

            Action::make('importFromJson')
                ->label('Import Unit Kerja (JSON)')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('success')
                ->schema([
                    \Filament\Forms\Components\FileUpload::make('json_file')
                        ->label('Upload File JSON')
                        ->acceptedFileTypes(['application/json'])
                        ->maxSize(5120)
                        ->disk('s3')
                        ->directory('imports')
                        ->visibility('private')
                        ->required()
                        ->helperText('Format: JSON array berisi data unit kerja. Max 5MB.'),

                    \Filament\Forms\Components\Toggle::make('skip_errors')
                        ->label('Lanjutkan meski ada error')
                        ->default(true)
                        ->helperText('Jika aktif, import akan tetap berjalan meski ada data yang gagal.'),
                ])
                ->action(function (array $data): void {
                    try {
                        $fileName = $data['json_file'];

                        $disk = Storage::disk('s3');

                        if (! $disk->exists($fileName)) {
                            Notification::make()
                                ->title('File tidak ditemukan di MinIO')
                                ->danger()
                                ->send();

                            return;
                        }

                        // Move to a predictable timestamped filename to avoid hashed names
                        $timestampedName = sprintf('imports/import_unitkerja_%s.json', now()->format('Ymd_His'));
                        $disk->copy($fileName, $timestampedName);
                        $disk->delete($fileName);

                        $jsonContent = $disk->get($timestampedName);
                        $unitsData = json_decode($jsonContent, true);

                        if (! is_array($unitsData)) {
                            Notification::make()
                                ->title('Format JSON tidak valid')
                                ->body('File harus berisi array JSON unit kerja.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $skipErrors = (bool) ($data['skip_errors'] ?? true);
                        $result = app(ImportUnitKerjasFromJsonAction::class)->execute($unitsData, $skipErrors);

                        $disk->delete($timestampedName);

                        Notification::make()
                            ->title('Import unit kerja selesai')
                            ->body(sprintf(
                                'Total: %d, dibuat: %d, diperbarui: %d, gagal: %d',
                                $result['total'] ?? 0,
                                $result['created'] ?? 0,
                                $result['updated'] ?? 0,
                                $result['failed'] ?? 0,
                            ))
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Error saat import')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                })
                ->modalHeading('Import Unit Kerja dari JSON')
                ->modalDescription('Upload file JSON berisi daftar unit kerja. Data akan di-upsert untuk mengurangi duplikasi.')
                ->modalSubmitActionLabel('Import')
                ->modalWidth('2xl'),

            Action::make('syncAllUnitKerja')
                ->label('Sinkronisasi Semua Unit Kerja ke Client')
                ->icon('heroicon-m-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->action(function (): void {
                    PushUnitKerjaToClient::dispatch([], null);

                    Notification::make()
                        ->title('Sinkronisasi unit kerja dijadwalkan')
                        ->success()
                        ->send();
                }),
        ];
    }
}
