<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;
use App\Services\SettingService;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $count = app(SettingService::class)->syncFromDefinitions();

        Setting::upsert([
            [
                'key' => 'login_view',
                'category' => 'ui',
                'value' => 'type1',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['key'], ['value', 'category', 'updated_at']);

        $this->command?->info('Settings seeded: ' . $count);
    }
}
