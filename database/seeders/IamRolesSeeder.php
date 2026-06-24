<?php

namespace Database\Seeders;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\ApplicationRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class IamRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎭 Seeding IAM Roles per Application...');

        $dataPath = database_path('data/application-role');

        if (!File::exists($dataPath)) {
            $this->command->warn("⚠️  Directory '{$dataPath}' not found, skipping roles.");
            return;
        }

        $files = File::files($dataPath);

        foreach ($files as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }

            $appKey = $file->getFilenameWithoutExtension();
            $roles = json_decode(File::get($file->getPathname()), true);

            if (!is_array($roles)) {
                $this->command->warn("⚠️  Invalid JSON in '{$file->getFilename()}', skipping.");
                continue;
            }

            $application = Application::where('app_key', $appKey)->first();

            if (!$application) {
                $this->command->warn("⚠️  Application '{$appKey}' not found, skipping roles.");

                continue;
            }

            $this->command->info("  Creating roles for: {$application->name}");

            foreach ($roles as $roleData) {
                ApplicationRole::firstOrCreate(
                    [
                        'application_id' => $application->id,
                        'slug' => $roleData['slug'],
                    ],
                    [
                        'name' => $roleData['name'],
                        'description' => $roleData['description'],
                        'is_system' => $roleData['is_system'] ?? false,
                    ]
                );
            }

            $this->command->info("    ✅ Created " . count($roles) . ' roles');
        }

        $this->command->newLine();
        $this->command->info('✅ IAM Roles seeding completed!');
    }
}
