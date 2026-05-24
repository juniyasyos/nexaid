<?php

namespace App\Filament\Panel\Resources\Users\Pages;

use App\Actions\ImportUsersFromJsonAction;
use App\Domain\Iam\Models\AccessProfile;
use App\Domain\Iam\Models\Application;
use App\Filament\Panel\Resources\Users\UserResource;
use App\Jobs\SyncApplicationUsers;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->withCommonRelations();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->makeCreateUserAction(),
            $this->makeImportUsersAction(),
            $this->makeSyncUsersAction(),
        ];
    }

    protected function makeCreateUserAction(): CreateAction
    {
        return CreateAction::make()
            ->label('Tambah Pengguna')
            ->icon('heroicon-m-plus')
            ->color('primary');
    }

    protected function makeImportUsersAction(): Action
    {
        return Action::make('importFromJson')
            ->label('Import Pengguna (JSON)')
            ->icon('heroicon-m-arrow-down-tray')
            ->color('success')
            ->schema($this->getImportUsersSchema())
            ->action(fn (array $data, ImportUsersFromJsonAction $importAction) => $this->handleImportUsers($data, $importAction))
            ->modalHeading('Import Pengguna dari JSON')
            ->modalDescription('Upload file JSON berisi data pengguna untuk di-import.')
            ->modalSubmitActionLabel('Import')
            ->modalWidth('2xl');
    }

    protected function getImportUsersSchema(): array
    {
        return [
            FileUpload::make('json_file')
                ->label('Upload File JSON')
                ->acceptedFileTypes(['application/json'])
                ->maxSize(5120)
                ->storeFiles(false)
                ->required()
                ->helperText('Format: JSON array dengan struktur sama seperti users.json. Max 5MB.'),

            Toggle::make('skip_errors')
                ->label('Lanjutkan meski ada error')
                ->default(true)
                ->helperText('Jika aktif, import akan terus berjalan meski ada baris yang gagal.'),
        ];
    }

    protected function handleImportUsers(array $data, ImportUsersFromJsonAction $importAction): void
    {
        try {
            Log::debug('=== Import JSON Action Started ===');

            $jsonContent = $this->readJsonUpload($data['json_file'] ?? null);
            $usersData = $this->decodeJsonUsers($jsonContent);

            $result = $importAction->execute($usersData);

            $this->sendImportResultNotification($result);

            Log::info('Import JSON - Completed', $this->getImportLogContext($result));
        } catch (Throwable $e) {
            Log::error('=== Import JSON Action Failed ===', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            $this->notifyDanger('Error saat import', $e->getMessage());
        }
    }

    protected function readJsonUpload(mixed $file): string
    {
        if (! $file) {
            throw new RuntimeException('File JSON tidak ditemukan.');
        }

        if (is_object($file)) {
            return $this->readUploadedFileObject($file);
        }

        if (is_string($file)) {
            return $this->readUploadedFilePath($file);
        }

        throw new RuntimeException('Format file upload tidak dikenali.');
    }

    protected function readUploadedFileObject(object $file): string
    {
        foreach (['getRealPath', '__toString'] as $method) {
            if (! method_exists($file, $method)) {
                continue;
            }

            $path = $method === '__toString'
                ? (string) $file
                : $file->{$method}();

            if ($path && file_exists($path)) {
                return file_get_contents($path);
            }
        }

        throw new RuntimeException('Gagal membaca file JSON dari temporary upload.');
    }

    protected function readUploadedFilePath(string $path): string
    {
        if (file_exists($path)) {
            return file_get_contents($path);
        }

        if (Storage::disk()->exists($path)) {
            return Storage::disk()->get($path);
        }

        throw new RuntimeException("File JSON tidak ditemukan pada path: {$path}");
    }

    protected function decodeJsonUsers (string $jsonContent): array
    {
        $usersData = json_decode($jsonContent, true);

        if (! is_array($usersData)) {
            throw new RuntimeException('Format JSON tidak valid: ' . json_last_error_msg());
        }

        return $usersData;
    }

    protected function sendImportResultNotification(array $result): void
    {
        $message = $this->buildImportSummaryMessage($result);
        $warningMessage = $this->buildImportWarningMessage($result);

        if (($result['failed'] ?? 0) > 0) {
            Notification::make()
                ->title('Import Pengguna Selesai dengan Catatan')
                ->body($message . $warningMessage . $this->buildImportErrorMessage($result))
                ->warning()
                ->send();

            return;
        }

        if ($warningMessage !== '') {
            Notification::make()
                ->title('Import Pengguna Selesai dengan Catatan')
                ->body($message . $warningMessage)
                ->warning()
                ->send();

            return;
        }

        Notification::make()
            ->title('Import Pengguna Selesai')
            ->body($message)
            ->success()
            ->send();
    }

    protected function buildImportSummaryMessage(array $result): string
    {
        return sprintf(
            'Total: %d | Dibuat: %d | Diperbarui: %d | Gagal: %d',
            $result['total'] ?? 0,
            $result['created'] ?? 0,
            $result['updated'] ?? 0,
            $result['failed'] ?? 0,
        );
    }

    protected function buildImportWarningMessage(array $result): string
    {
        $warnings = [];

        if (! empty($result['warnings']['access_profiles_not_found'])) {
            $warnings[] = 'Access profile tidak ditemukan: ' . implode(', ', $result['warnings']['access_profiles_not_found']);
        }

        if (! empty($result['warnings']['unit_kerjas_not_found'])) {
            $warnings[] = 'Unit kerja tidak ditemukan: ' . implode(', ', $result['warnings']['unit_kerjas_not_found']);
        }

        return empty($warnings)
            ? ''
            : "\n\nWarning:\n" . implode("\n", $warnings);
    }

    protected function buildImportErrorMessage(array $result): string
    {
        if (empty($result['errors'])) {
            return '';
        }

        $errors = collect($result['errors'])
            ->map(fn (array $error) => sprintf(
                'Baris %d (%s): %s',
                $error['row'] ?? 0,
                $error['nip'] ?? '-',
                $error['error'] ?? '-',
            ))
            ->join("\n");

        return "\n\nError:\n" . $errors;
    }

    protected function getImportLogContext(array $result): array
    {
        return [
            'total' => $result['total'] ?? 0,
            'created' => $result['created'] ?? 0,
            'updated' => $result['updated'] ?? 0,
            'failed' => $result['failed'] ?? 0,
        ];
    }

    protected function makeSyncUsersAction(): Action
    {
        return Action::make('syncFromApps')
            ->label('Sinkron pengguna')
            ->icon('heroicon-m-arrow-path')
            ->color('primary')
            ->authorize(fn () => false)
            ->schema($this->getSyncUsersSchema())
            ->action(fn (array $data) => $this->handleSyncUsers($data));
    }

    protected function getSyncUsersSchema(): array
    {
        return [
            CheckboxList::make('application_ids')
                ->label('Aplikasi')
                ->options(Application::query()->pluck('name', 'id')->toArray())
                ->columns(2)
                ->required(),

            CheckboxList::make('profile_ids')
                ->label('Role Bundles')
                ->options(AccessProfile::active()->pluck('name', 'id')->toArray())
                ->columns(2)
                ->required(),

            Select::make('sync_mode')
                ->label('Mode sinkron')
                ->options([
                    'auto' => 'Otomatis',
                    'manual' => 'Manual',
                ])
                ->default('auto')
                ->required(),
        ];
    }

    protected function handleSyncUsers(array $data): void
    {
        $applicationIds = $data['application_ids'] ?? [];
        $profileIds = $data['profile_ids'] ?? [];

        if (empty($applicationIds)) {
            $this->notifyWarning('Tidak ada aplikasi dipilih');
            return;
        }

        if (empty($profileIds)) {
            $this->notifyWarning('Tidak ada role bundle dipilih');
            return;
        }

        SyncApplicationUsers::dispatch($applicationIds, $profileIds);

        $this->notifySuccess('Job sinkron pengguna dijadwalkan');
    }

    protected function notifySuccess(string $title, ?string $body = null): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->success()
            ->send();
    }

    protected function notifyWarning(string $title, ?string $body = null): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->warning()
            ->send();
    }

    protected function notifyDanger(string $title, ?string $body = null): void
    {
        Notification::make()
            ->title($title)
            ->body($body)
            ->danger()
            ->send();
    }
}