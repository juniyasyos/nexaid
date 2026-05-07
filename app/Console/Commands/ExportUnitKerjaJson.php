<?php

namespace App\Console\Commands;

use App\Models\UnitKerja;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ExportUnitKerjaJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'unit-kerja:export-json {--path=exports/unit_kerja.json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export unit kerja to a JSON file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = UnitKerja::query()->orderBy('id');

        if (in_array(SoftDeletes::class, class_uses_recursive(UnitKerja::class), true)) {
            $query->withTrashed();
        }

        $unitKerjas = $query
            ->get()
            ->map(static fn(UnitKerja $unitKerja) => $unitKerja->getAttributes())
            ->toArray();

        $payload = json_encode($unitKerjas, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            $this->error('Failed to encode JSON: ' . json_last_error_msg());

            return self::FAILURE;
        }

        $path = $this->option('path') ?: 'exports/unit_kerja.json';

        Storage::disk('local')->put($path, $payload);

        $this->info('Unit Kerja exported: storage/app/' . $path);
        $this->info('Total unit kerja: ' . count($unitKerjas));

        return self::SUCCESS;
    }
}
