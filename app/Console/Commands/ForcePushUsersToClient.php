<?php

namespace App\Console\Commands;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Services\ApplicationUserSyncService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ForcePushUsersToClient extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'iam:force-push-users
                            {--app=* : Application IDs to sync (can be repeated)}
                            {--user_id= : Optional specific user ID to target (must exist in IAM)}';

    /**
     * The console command description.
     */
    protected $description = 'Force push users data from IAM to client applications.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $appIds = array_filter((array) $this->option('app'));
        $userId = $this->option('user_id');

        $query = Application::query();
        if (! empty($appIds)) {
            $query->whereIn('id', $appIds);
            $this->info('Limiting to applications: ' . implode(', ', $appIds));
        } else {
            $this->info('Menyinkron semua aplikasi IAM.');
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, Application> $applications */
        $applications = $query->get();

        if ($applications->isEmpty()) {
            $this->warn('Tidak ada aplikasi ditemukan.');
            return self::SUCCESS;
        }

        foreach ($applications as $application) {
            /** @var Application $application */
            $this->line('===========================================');
            $this->info("[{$application->id}] {$application->app_key}: force push ke client");

            $service = new ApplicationUserSyncService([], 'auto', []);

            try {
                $result = $service->forcePushUsers($application, $userId ? (int) $userId : null);
                if (! $result['success']) {
                    $this->error("Gagal push untuk {$application->app_key}: {$result['error']}");
                    continue;
                }

                $this->info("Berhasil push: {$result['message']} (jumlah IAM users: " . count($result['iam_users']) . ")");
            } catch (\Exception $e) {
                $this->error("Exception saat push untuk {$application->app_key}: " . $e->getMessage());
                Log::error('iam.force_push_users_error', [
                    'application_id' => $application->id,
                    'app_key' => $application->app_key,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return self::SUCCESS;
    }
}
