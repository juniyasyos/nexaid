<?php

namespace App\Jobs;

use App\Actions\ImportUsersFromJsonAction;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportUsersFromJsonJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $filePath,
        public readonly int $userId,
    ) {}

    public function handle(ImportUsersFromJsonAction $action): void
    {
        try {
            if (! Storage::disk('s3')->exists($this->filePath)) {
                throw new \RuntimeException('File import pengguna tidak ditemukan di storage.');
            }

            $jsonContent = Storage::disk('s3')->get($this->filePath);
            $usersData = json_decode($jsonContent, true);

            if (! is_array($usersData)) {
                throw new \InvalidArgumentException('Format JSON tidak valid untuk import pengguna.');
            }

            // $debugPayload = collect($usersData)->first(
            //     fn($userData) => is_array($userData) && (($userData['name'] ?? null) === 'Agung Sunaryo, S.Kom')
            // );

            // if ($debugPayload !== null) {
            //     dd([
            //         'source' => 'ImportUsersFromJsonJob',
            //         'file_path' => $this->filePath,
            //         'triggered_by_user_id' => $this->userId,
            //         'incoming_payload' => $debugPayload,
            //     ]);
            // }

            $result = $action->execute($usersData);

            $message = sprintf(
                'Total: %d | Dibuat: %d | Diperbarui: %d | Gagal: %d',
                $result['total'],
                $result['created'],
                $result['updated'],
                $result['failed']
            );

            $warningLines = [];

            if (! empty($result['warnings']['access_profiles_not_found'])) {
                $warningLines[] = 'Access profile tidak ditemukan: ' . implode(', ', $result['warnings']['access_profiles_not_found']);
            }

            if (! empty($result['warnings']['unit_kerjas_not_found'])) {
                $warningLines[] = 'Unit kerja tidak ditemukan: ' . implode(', ', $result['warnings']['unit_kerjas_not_found']);
            }

            $warningMessage = empty($warningLines)
                ? ''
                : "\n\nWarning:\n" . implode("\n", $warningLines);

            if ($result['failed'] > 0) {
                $errorDetails = collect($result['errors'])
                    ->map(fn($err) => sprintf(
                        'Baris %d (%s): %s',
                        $err['row'],
                        $err['nip'],
                        $err['error']
                    ))
                    ->join("\n");

                $this->notify(
                    Notification::make()
                        ->title('Import Pengguna Selesai dengan Catatan')
                        ->body($message . $warningMessage . "\n\nError:\n" . $errorDetails)
                        ->warning()
                );

                return;
            }

            if ($warningMessage !== '') {
                $this->notify(
                    Notification::make()
                        ->title('Import Pengguna Selesai dengan Catatan')
                        ->body($message . $warningMessage)
                        ->warning()
                );

                return;
            }

            $this->notify(
                Notification::make()
                    ->title('Import Pengguna Selesai')
                    ->body($message)
                    ->success()
            );
        } catch (Throwable $e) {
            $this->notify(
                Notification::make()
                    ->title('Import Pengguna Gagal')
                    ->body($e->getMessage())
                    ->danger()
            );
        } finally {
            Storage::disk('s3')->delete($this->filePath);
        }
    }

    private function notify(Notification $notification): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            return;
        }

        $notification->sendToDatabase($user);
    }
}
