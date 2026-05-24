<?php

namespace App\Console\Commands;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Services\ApplicationUserSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SyncSiimutClientUsers extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'siimut:sync-client
                            {--app=* : Application IDs to sync (can be repeated)}
                            {--profile=* : Access profile IDs to restrict sync (optional)}
                            {--dry-run : Show what would happen without writing changes}';

    /**
     * The console command description.
     */
    protected $description = 'Push IAM users to Siimut client applications and assign access profiles matching application role slugs. Use --dry-run to preview connectivity and assignment plans without writes.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $appIds = array_filter((array) $this->option('app'));
        $profileIds = array_filter((array) $this->option('profile'));
        $dryRun = $this->option('dry-run');

        $query = Application::query();
        if (! empty($appIds)) {
            $query->whereIn('id', $appIds);
            $this->info('Sync only selected applications: ' . implode(', ', $appIds));
        } else {
            $this->info('Sync all applications.');
        }

        if (! empty($profileIds)) {
            $this->info('Restricting to access profile IDs: ' . implode(', ', $profileIds));
            $query->whereHas('roles.accessProfiles', function ($q) use ($profileIds) {
                $q->whereIn('access_profiles.id', $profileIds);
            });
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Application> $applications */
        $applications = $query->get();

        if ($applications->isEmpty()) {
            $this->warn('No applications found matching criteria.');
            return self::SUCCESS;
        }

        foreach ($applications as $app) {
            /** @var Application $app */
            $this->line('==========');
            $this->info("Syncing app: {$app->id} ({$app->app_key})");

            $service = new ApplicationUserSyncService($profileIds, 'auto', []);

            if ($dryRun) {
                $this->line('Dry run mode: no create/update will be performed.');
                $result = $service->previewUsers($app);

                if (! $result['success']) {
                    $this->error("Failed to connect to app {$app->app_key}: {$result['error']}");
                    continue;
                }

                $this->info("Connected to app {$app->app_key} (id {$app->id})");
                $this->line('Client users and planned profile bundle assignment:');

                foreach ($result['preview'] as $previewUser) {
                    $this->line('---');
                    $this->info("User: " . ($previewUser['name'] ?? '[unknown]') . " (nip=" . ($previewUser['nip'] ?? '-') . ", email=" . ($previewUser['email'] ?? '-') . ")");
                    $this->line('  Siimut role slugs: ' . implode(', ', $previewUser['client_role_slugs']));
                    $this->line('  Existing SSO bundles: ' . implode(', ', array_map(fn($p) => $p['slug'], $previewUser['current_profile_assignments'])));

                    $plan = $previewUser['planned_profile_assignment'];
                    $this->line('  Candidate bundles from existing profiles: ' . implode(', ', array_map(fn($p) => $p['slug'], $plan['candidate_profiles'])));
                    $this->line('  Missing role slugs (would generate auto bundles): ' . implode(', ', $plan['missing_role_slugs']));
                    $this->line('  Missing auto bundle names: ' . implode(', ', array_map(fn($p) => $p['slug'], $plan['auto_profiles'])));
                }

                continue;
            }

            try {
                $result = $service->syncUsers($app);
                $this->info("OK: {$result['message']} (created={$result['created']} updated={$result['updated']})");
            } catch (\Exception $e) {
                $this->error('Failed sync for app ' . $app->app_key . ': ' . $e->getMessage());
                Log::error('siimut_sync_client_failed', [
                    'application_id' => $app->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
