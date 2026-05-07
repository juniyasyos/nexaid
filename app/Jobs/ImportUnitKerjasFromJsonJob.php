<?php

namespace App\Jobs;

use App\Actions\ImportUnitKerjasFromJsonAction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ImportUnitKerjasFromJsonJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $importId,
        public readonly string $filePath,
        public readonly int $userId,
        public readonly bool $skipErrors = true,
    ) {}

    public function handle(ImportUnitKerjasFromJsonAction $action): void
    {
        $progressKey = $this->progressKey();

        try {
            Cache::put($progressKey, array_merge($this->defaultProgress(), [
                'status' => 'running',
                'message' => 'Membaca file JSON...',
            ]), now()->addHours(2));

            if (! Storage::disk('s3')->exists($this->filePath)) {
                throw new \RuntimeException('File import tidak ditemukan di storage.');
            }

            $jsonContent = Storage::disk('s3')->get($this->filePath);
            $unitsData = json_decode($jsonContent, true);

            if (! is_array($unitsData)) {
                throw new \InvalidArgumentException('Format JSON tidak valid untuk import unit kerja.');
            }

            $result = $action->execute($unitsData, $this->skipErrors, function (array $progress) use ($progressKey): void {
                Cache::put($progressKey, array_merge($this->defaultProgress(), [
                    'status' => $progress['status'] ?? 'running',
                    'message' => sprintf(
                        'Sinkronisasi %d/%d data unit kerja',
                        $progress['index'] ?? 0,
                        $progress['total'] ?? 0
                    ),
                    'total' => $progress['total'] ?? 0,
                    'processed' => $progress['index'] ?? 0,
                    'created' => $progress['created'] ?? 0,
                    'updated' => $progress['updated'] ?? 0,
                    'failed' => $progress['failed'] ?? 0,
                    'last_unit_name' => $progress['unit_name'] ?? null,
                    'last_error' => $progress['error'] ?? null,
                ]), now()->addHours(2));
            });

            Cache::put($progressKey, array_merge($this->defaultProgress(), [
                'status' => 'completed',
                'message' => 'Import unit kerja selesai.',
                'total' => $result['total'],
                'processed' => $result['total'],
                'created' => $result['created'],
                'updated' => $result['updated'],
                'failed' => $result['failed'],
                'errors' => $result['errors'],
            ]), now()->addHours(2));
        } catch (Throwable $e) {
            Cache::put($progressKey, array_merge($this->defaultProgress(), [
                'status' => 'failed',
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
            ]), now()->addHours(2));
        } finally {
            Storage::disk('s3')->delete($this->filePath);
        }
    }

    protected function progressKey(): string
    {
        return 'import_unit_kerja:' . $this->importId;
    }

    protected function defaultProgress(): array
    {
        return [
            'import_id' => $this->importId,
            'status' => 'queued',
            'message' => 'Menunggu diproses...',
            'total' => 0,
            'processed' => 0,
            'created' => 0,
            'updated' => 0,
            'failed' => 0,
            'last_unit_name' => null,
            'last_error' => null,
            'error' => null,
        ];
    }
}
