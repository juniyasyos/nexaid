<?php

namespace App\Console\Commands;

use App\Domain\Iam\Models\AccessProfile;
use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\ApplicationRole;
use App\Domain\Iam\Services\ApplicationUserSyncService;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TestUserSync extends Command
{
    protected $signature = 'test:user-sync {--create-test-user : Create test user first}';

    protected $description = 'Test user sync and check profile assignments';

    public function handle()
    {
        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('🧪 Testing User Sync');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('');

        $app = Application::where('app_key', 'siimut')->first();
        if (!$app) {
            $this->error('❌ Application siimut not found');
            return 1;
        }

        $this->info("📱 Testing with: {$app->name}");
        $this->line('');

        // Step 1: Create test data
        if ($this->option('create-test-user')) {
            $this->info('Step 1️⃣  Creating test user...');
            $user = User::firstOrCreate(
                ['nip' => '9999.99999'],
                [
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'password' => bcrypt('password'),
                    'active' => true,
                ]
            );
            $this->info("   ✅ User created: {$user->name} (NIP: {$user->nip}, ID: {$user->id})");
            $this->line('');
        }

        // Step 2: Check current state
        $this->info('Step 2️⃣  Current state before sync...');
        $userCount = User::count();
        $this->info("   Total users: {$userCount}");

        // OPTIMIZATION: Use chunk to avoid loading entire table + use withCount to avoid N+1
        User::withCount([
            'accessProfiles' => function ($q) use ($app) {
                $q->whereHas('roles', function ($q2) use ($app) {
                    $q2->where('application_id', $app->id);
                });
            }
        ])
        ->chunk(100, function ($users) {
            foreach ($users as $user) {
                $this->info("   • {$user->name} (NIP: {$user->nip}): {$user->access_profiles_count} profiles");
            }
        });
        $this->line('');

        // Step 3: Sync
        $this->info('Step 3️⃣  Running sync...');

        // Check config first
        $syncMode = config('iam.user_sync_mode', 'pull');
        $this->info("   Sync mode: $syncMode");

        if ($syncMode === 'pull') {
            $this->warn('   ⚠️  PULL mode: Trying to fetch users from client application');
            $this->warn("   Callback URL: {$app->callback_url}");
            $this->warn("   Backchannel URL: {$app->backchannel_url}");
        }

        $service = new ApplicationUserSyncService();

        // Get all profiles for this app to pass as allowed
        $profileIds = AccessProfile::query()
            ->whereHas('roles', function ($q) use ($app) {
                $q->where('application_id', $app->id);
            })
            ->pluck('id')
            ->toArray();

        if (empty($profileIds)) {
            $this->warn('   ⚠️ No profiles found for app');
        } else {
            $this->info("   Profile IDs to use: " . implode(', ', $profileIds));
        }

        $result = $service->syncUsers($app);

        if (!$result['success']) {
            $this->error("   ❌ Sync failed: {$result['error']}");
            $this->line('');
            $this->info('Step 4️⃣  Why sync failed?');
            $this->info("   • Config iam.user_sync_mode = $syncMode");
            if ($syncMode === 'pull') {
                $this->warn("   • Trying to reach: {$app->callback_url}");
                $this->error("   • But no client application is running there!");
                $this->line('');
                $this->info('Step 5️⃣  How to fix?');
                $this->line('');
                $this->info('Option A: Start SIIMUT client app on callback URL');
                $this->line('   Example: http://127.0.0.1:8088/sso/callback');
                $this->line('');
                $this->info('Option B: Switch to PUSH mode (IAM sends users to client)');
                $this->line('   Set: IAM_USER_SYNC_MODE=push in .env');
                $this->line('   Then use seeder to assign users to profiles directly');
                $this->line('   Then click "Sync Users" will push users to client');
                $this->line('');
            }
            return 1;
        }

        $this->info("   ✅ Sync completed");
        $this->info("   • Created: {$result['created']}");
        $this->info("   • Updated: {$result['updated']}");
        if ($result['created'] == 0 && $result['updated'] == 0) {
            $this->warn("   ⚠️  No users synced. Check if client is sending users.");
        }
        $this->line('');

        // Step 4: Check after sync
        $this->info('Step 4️⃣  State after sync...');
        
        // OPTIMIZATION: Use chunk to avoid loading entire table + use with() to avoid N+1
        User::with(['accessProfiles'])
            ->chunk(100, function ($users) use ($app) {
                foreach ($users as $user) {
                    $profiles = $user->accessProfiles
                        ->filter(function ($profile) use ($app) {
                            return $profile->roles->where('application_id', $app->id)->isNotEmpty();
                        });

                    if ($profiles->isEmpty()) {
                        $this->warn("   • {$user->name} (NIP: {$user->nip}): ❌ NO PROFILES");
                    } else {
                        $profileNames = $profiles->pluck('name')->implode(', ');
                        $this->info("   • {$user->name} (NIP: {$user->nip}): ✅ {$profiles->count()} profiles");
                        $this->info("     → {$profileNames}");
                    }
                }
            });
        $this->line('');

        // Step 5: Raw pivot table check
        $this->info('Step 5️⃣  Raw pivot table (user_access_profiles)...');
        $pivotData = DB::table('user_access_profiles')
            ->get(['user_id', 'access_profile_id', 'assigned_by', 'created_at']);

        if ($pivotData->isEmpty()) {
            $this->warn('   ❌ No records in user_access_profiles pivot table!');
        } else {
            $this->info("   Total pivot records: {$pivotData->count()}");
            foreach ($pivotData as $row) {
                $user = User::find($row->user_id);
                $profile = AccessProfile::find($row->access_profile_id);
                $this->info("   • User: {$user->name} (ID: {$row->user_id}) → Profile: {$profile->name} (ID: {$row->access_profile_id})");
            }
        }
        $this->line('');

        // Step 6: Check logs
        $this->info('Step 6️⃣  Recent sync logs (user_sync_* entries)...');
        $logFile = storage_path('logs/laravel.log');

        if (file_exists($logFile)) {
            $lines = array_reverse(file($logFile));
            $syncLogs = [];

            foreach ($lines as $line) {
                if (strpos($line, 'user_sync') !== false || strpos($line, 'client_users') !== false) {
                    $syncLogs[] = trim($line);
                    if (count($syncLogs) >= 5) break;
                }
            }

            if (empty($syncLogs)) {
                $this->warn('   ⚠️ No sync logs found');
            } else {
                foreach (array_reverse($syncLogs) as $log) {
                    $this->line("   " . substr($log, 0, 150) . "...");
                }
            }
        }
        $this->line('');

        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('✅ Test completed');
        $this->line('');
        $this->info('📝 If no profiles assigned:');
        $this->line('   1. Check logs: tail -f storage/logs/laravel.log | grep user_sync');
        $this->line('   2. Look for "user_role_sync_failed" or "user_sync_slug_validation"');
        $this->line('   3. Check if client sending correct role slugs');
        $this->line('');

        return 0;
    }
}
