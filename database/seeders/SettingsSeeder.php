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
                'key' => 'ui.login_view.default',
                'category' => 'ui',
                'type' => 'boolean',
                'input_type' => 'toggle',
                'value' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'ui.login_view.type1',
                'category' => 'ui',
                'type' => 'boolean',
                'input_type' => 'toggle',
                'value' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ], ['key'], ['value', 'category', 'updated_at']);

        $this->command?->info('Settings seeded: ' . $count);
    }
}
