<?php

namespace App\Actions;

use App\Models\UnitKerja;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ImportUnitKerjasFromJsonAction
{
    /**
     * Import unit kerja data from a JSON payload.
     */
    public function execute(array $data, bool $skipErrors = true, ?callable $onProgress = null): array
    {
        $total = count($data);
        $created = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];

        foreach ($data as $index => $unitData) {
            try {
                DB::beginTransaction();

                if (! is_array($unitData)) {
                    throw new \InvalidArgumentException('Invalid unit kerja payload.');
                }

                $result = $this->upsertUnitKerja($unitData);

                if ($result['created']) {
                    $created++;
                } else {
                    $updated++;
                }

                if ($onProgress) {
                    $onProgress([
                        'index' => $index + 1,
                        'total' => $total,
                        'created' => $created,
                        'updated' => $updated,
                        'failed' => $failed,
                        'status' => 'running',
                        'unit_name' => $unitData['unit_name'] ?? $unitData['slug'] ?? 'N/A',
                    ]);
                }

                Notification::make()
                    ->title($result['created']
                        ? 'Unit kerja berhasil dibuat'
                        : 'Unit kerja berhasil diperbarui')
                    ->body($unitData['unit_name'] ?? $unitData['slug'] ?? 'N/A')
                    ->success()
                    ->send();

                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                $failed++;
                $unitLabel = is_array($unitData)
                    ? ($unitData['unit_name'] ?? $unitData['slug'] ?? 'N/A')
                    : 'N/A';

                $errors[] = [
                    'row' => $index + 1,
                    'unit_name' => $unitLabel,
                    'error' => $e->getMessage(),
                ];

                if ($onProgress) {
                    $unitLabel = is_array($unitData)
                        ? ($unitData['unit_name'] ?? $unitData['slug'] ?? 'N/A')
                        : 'N/A';

                    $onProgress([
                        'index' => $index + 1,
                        'total' => $total,
                        'created' => $created,
                        'updated' => $updated,
                        'failed' => $failed,
                        'status' => 'running',
                        'unit_name' => $unitLabel,
                        'error' => $e->getMessage(),
                    ]);
                }

                if (! $skipErrors) {
                    break;
                }
            }
        }

        return [
            'total' => $total,
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    protected function upsertUnitKerja(array $unitData): array
    {
        $unitName = trim((string) ($unitData['unit_name'] ?? ''));
        $slug = trim((string) ($unitData['slug'] ?? ''));
        $description = $unitData['description'] ?? null;
        $id = isset($unitData['id']) ? (int) $unitData['id'] : null;

        if ($unitName === '' && $slug === '' && $id === null) {
            throw new \InvalidArgumentException('Unit kerja harus memiliki unit_name, slug, atau id.');
        }

        $lookup = $slug !== ''
            ? ['slug' => $slug]
            : ($unitName !== '' ? ['unit_name' => $unitName] : ['id' => $id]);

        $unitKerja = UnitKerja::withTrashed()->firstOrNew($lookup);
        $created = ! $unitKerja->exists;

        $unitKerja->unit_name = $unitName !== ''
            ? $unitName
            : ($slug !== '' ? Str::title(str_replace(['-', '_'], ' ', $slug)) : 'Unit Kerja');
        $unitKerja->description = $description;

        if (method_exists($unitKerja, 'trashed') && $unitKerja->trashed()) {
            $unitKerja->restore();
        }

        $unitKerja->save();

        return [
            'unit' => $unitKerja,
            'created' => $created,
        ];
    }
}
