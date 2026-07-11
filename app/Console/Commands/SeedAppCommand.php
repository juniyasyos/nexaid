<?php

namespace App\Console\Commands;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\ApplicationRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class SeedAppCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:seed {--app= : The app_key of the application to seed} {--status : Show status of applications in database vs seeder}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed a specific application and its roles based on the app_key';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $appKey = $this->option('app');
        $status = $this->option('status');

        if ($status) {
            $this->showApplicationsStatus();
            return Command::SUCCESS;
        }

        if (!$appKey) {
            $this->error('Missing required option.');
            $this->showUsageHelper();
            return Command::FAILURE;
        }

        $this->info("🚀 Starting seeding for application: {$appKey}");

        // 1. Seed Application
        $this->seedApplication($appKey);

        // 2. Seed Roles
        $this->seedRoles($appKey);

        $this->info("✅ Seeding for '{$appKey}' completed successfully!");

        return Command::SUCCESS;
    }

    private function seedApplication(string $appKey): void
    {
        $jsonPath = database_path('applications.json');
        
        if (!File::exists($jsonPath)) {
            $this->warn("⚠️  Applications JSON file not found at '{$jsonPath}'.");
            return;
        }

        $applications = json_decode(File::get($jsonPath), true);
        $appData = collect($applications)->firstWhere('app_key', $appKey);

        if (!$appData) {
            $this->warn("⚠️  Application '{$appKey}' not found in '{$jsonPath}'.");
            return;
        }

        $admin = User::where('nip', '0000.00000')->first();
        $appData['created_by'] = $admin?->id;

        Application::updateOrCreate(
            ['app_key' => $appData['app_key']],
            $appData
        );

        $this->info("  ✅ Created/Updated application: {$appData['name']}");
    }

    private function seedRoles(string $appKey): void
    {
        $rolesPath = database_path("data/application-role/{$appKey}.json");

        if (!File::exists($rolesPath)) {
            $this->warn("⚠️  Roles file not found for '{$appKey}' at '{$rolesPath}'.");
            return;
        }

        $roles = json_decode(File::get($rolesPath), true);

        if (!is_array($roles)) {
            $this->warn("⚠️  Invalid JSON in '{$rolesPath}'.");
            return;
        }

        $application = Application::where('app_key', $appKey)->first();

        if (!$application) {
            $this->warn("⚠️  Application '{$appKey}' not found in database, skipping roles.");
            return;
        }

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

        $this->info("  ✅ Created/Updated " . count($roles) . " roles for '{$appKey}'");
    }

    private function showUsageHelper(): void
    {
        $this->newLine();
        $this->info('💡 Usage Helper for app:seed');
        $this->line('--------------------------------------------------');
        $this->line('This command is used to seed applications and their respective roles.');
        $this->newLine();
        $this->info('Available Commands:');
        $this->line('  <fg=yellow>php artisan app:seed --app=<app_key></>    Seed a specific application by its key.');
        $this->line('                                         Example: <fg=cyan>php artisan app:seed --app=siimut</>');
        $this->newLine();
        $this->line('  <fg=yellow>php artisan app:seed --status</>           Show a comparison table of applications');
        $this->line('                                         in the database versus the seeder JSON.');
        $this->newLine();
    }

    private function showApplicationsStatus(): void
    {
        $jsonPath = database_path('applications.json');
        
        $seederApps = [];
        if (File::exists($jsonPath)) {
            $seederData = json_decode(File::get($jsonPath), true);
            if (is_array($seederData)) {
                $seederApps = collect($seederData)->keyBy('app_key')->toArray();
            } else {
                $this->warn("⚠️  Invalid JSON in '{$jsonPath}'.");
            }
        } else {
            $this->warn("⚠️  Applications JSON file not found at '{$jsonPath}'.");
        }

        $dbApps = Application::with('roles')->get()->keyBy('app_key');

        $allKeys = collect(array_keys($seederApps))->merge($dbApps->keys())->unique()->sort();

        $rows = [];
        foreach ($allKeys as $key) {
            $inSeeder = isset($seederApps[$key]);
            $inDb = $dbApps->has($key);

            $name = $inDb ? $dbApps[$key]->name : ($inSeeder ? $seederApps[$key]['name'] : 'N/A');
            
            if ($inDb && $inSeeder) {
                $status = '<fg=green>In DB & Seeder</>';
            } elseif ($inDb) {
                $status = '<fg=yellow>Only in DB</>';
            } else {
                $status = '<fg=cyan>Only in Seeder</>';
            }

            $differences = [];
            if ($inDb && $inSeeder) {
                $seederApp = $seederApps[$key];
                $dbApp = $dbApps[$key];
                
                $fieldsToCompare = array_keys($seederApp);
                $fieldsToExclude = ['secret', 'created_by']; // Exclude fields that shouldn't or can't be strictly compared
                
                foreach ($fieldsToCompare as $field) {
                    if (in_array($field, $fieldsToExclude)) {
                        continue;
                    }
                    
                    if (array_key_exists($field, $seederApp)) {
                        $dbValue = $dbApp->$field;
                        $seederValue = $seederApp[$field];
                        
                        if (is_array($dbValue) || is_array($seederValue)) {
                            $dbValueArr = is_array($dbValue) ? $dbValue : [];
                            $seederValueArr = is_array($seederValue) ? $seederValue : [];
                            sort($dbValueArr);
                            sort($seederValueArr);
                            if (json_encode($dbValueArr) !== json_encode($seederValueArr)) {
                                $differences[] = "{$field}: array differs";
                            }
                        } else {
                            if (is_bool($dbValue) || is_bool($seederValue)) {
                                $dbValue = (bool) $dbValue;
                                $seederValue = (bool) $seederValue;
                            }

                            if ($dbValue !== $seederValue) {
                                $dbValStr = is_bool($dbValue) ? ($dbValue ? 'true' : 'false') : (string)$dbValue;
                                $seedValStr = is_bool($seederValue) ? ($seederValue ? 'true' : 'false') : (string)$seederValue;
                                $differences[] = "{$field}: DB({$dbValStr}) != Seeder({$seedValStr})";
                            }
                        }
                    }
                }

                // Check Roles Differences
                $rolesPath = database_path("data/application-role/{$key}.json");
                $seederRoles = [];
                if (File::exists($rolesPath)) {
                    $seederRolesData = json_decode(File::get($rolesPath), true);
                    if (is_array($seederRolesData)) {
                        $seederRoles = collect($seederRolesData)->keyBy('slug')->toArray();
                    }
                }
                
                $dbRoles = $dbApp->roles->keyBy('slug');
                $allRoleSlugs = collect(array_keys($seederRoles))->merge($dbRoles->keys())->unique();
                
                foreach ($allRoleSlugs as $slug) {
                    if (!isset($seederRoles[$slug])) {
                        $differences[] = "Role '{$slug}' only in DB";
                    } elseif (!$dbRoles->has($slug)) {
                        $differences[] = "Role '{$slug}' only in Seeder";
                    } else {
                        $sRole = $seederRoles[$slug];
                        $dRole = $dbRoles[$slug];
                        foreach (['name', 'description', 'is_system'] as $rField) {
                            if (array_key_exists($rField, $sRole)) {
                                $dVal = $dRole->$rField;
                                $sVal = $sRole[$rField];
                                if (is_bool($dVal) || is_bool($sVal)) {
                                    $dVal = (bool)$dVal;
                                    $sVal = (bool)$sVal;
                                }
                                if ($dVal !== $sVal) {
                                    $differences[] = "Role '{$slug}' {$rField} differs";
                                }
                            }
                        }
                    }
                }
            }

            $diffStr = '-';
            if ($inDb && $inSeeder) {
                $diffStr = empty($differences) ? '<fg=green>Matches</>' : implode("\n", $differences);
            }
            $rows[] = [
                $key,
                $name,
                $status,
                $diffStr
            ];
        }

        $this->table(['App Key', 'Name', 'Status', 'Differences (DB vs Seeder)'], $rows);
    }
}
