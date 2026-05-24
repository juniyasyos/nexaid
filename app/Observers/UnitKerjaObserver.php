<?php

namespace App\Observers;

use App\Jobs\PushUnitKerjaToClient;
use App\Models\UnitKerja;
use Illuminate\Support\Facades\Log;

class UnitKerjaObserver
{
    public function saved(UnitKerja $unitKerja): void
    {
        if (! $unitKerja->wasRecentlyCreated && ! $unitKerja->wasChanged(['unit_name', 'description', 'slug'])) {
            return;
        }

        $this->dispatchUnitSync($unitKerja, 'saved');
    }

    public function deleted(UnitKerja $unitKerja): void
    {
        $this->dispatchUnitSync($unitKerja, 'deleted');
    }

    public function restored(UnitKerja $unitKerja): void
    {
        $this->dispatchUnitSync($unitKerja, 'restored');
    }

    public function forceDeleted(UnitKerja $unitKerja): void
    {
        // Force delete all user_unit_kerja pivot rows for this unit kerja
        \Illuminate\Support\Facades\DB::table('user_unit_kerja')
            ->where('unit_kerja_id', $unitKerja->getKey())
            ->delete();

        Log::info('iam.unit_kerja_observer_force_deleted', [
            'unit_kerja_id' => $unitKerja->getKey(),
            'slug' => $unitKerja->slug,
            'unit_name' => $unitKerja->unit_name,
            'relations_deleted' => true,
        ]);

        $this->dispatchUnitSync($unitKerja, 'force_deleted');
    }

    protected function dispatchUnitSync(UnitKerja $unitKerja, string $event): void
    {
        Log::info('iam.unit_kerja_observer_trigger', [
            'unit_kerja_id' => $unitKerja->getKey(),
            'slug' => $unitKerja->slug,
            'unit_name' => $unitKerja->unit_name,
            'event' => $event,
        ]);

        PushUnitKerjaToClient::dispatch([], $unitKerja->getKey());
    }
}
