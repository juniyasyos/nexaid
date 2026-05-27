<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;

class CheckMinioCommand extends Command
{
    protected $signature = 'minio:check {disk? : The filesystem disk to check; defaults to the active filesystem disk} {--quick : Validate configuration only, do not attempt network connection}';

    protected $description = 'Check whether the active storage disk is configured correctly and reachable.';

    public function handle(): int
    {
        $environment = config('app.env', 'production');
        $disk = $this->argument('disk') ?: config('filesystems.default');
        $quick = $this->option('quick');

        $this->info("Environment: {$environment}");
        $this->info("Checking filesystem disk: {$disk}");

        $diskConfig = Config::get("filesystems.disks.{$disk}");

        if (! $diskConfig) {
            $this->error("Disk '{$disk}' is not configured in config/filesystems.php.");
            return self::FAILURE;
        }

        $driver = $diskConfig['driver'] ?? null;

        if ($driver === 'local') {
            $this->info("The '{$disk}' disk uses the local driver, which is expected outside production.");
        } elseif ($driver !== 's3') {
            $this->warn("The '{$disk}' disk is configured using driver '{$driver}'. MinIO requires an S3-compatible disk.");
        }

        $this->info('Configuration check passed.');

        if ($driver === 'local') {
            $this->line('Local storage path: ' . Storage::disk($disk)->path(''));
            $this->line('Quick mode: ' . ($quick ? '<fg=yellow>enabled</>' : '<fg=green>disabled</>'));
            $this->info('✅ Local storage is configured correctly.');
            return self::SUCCESS;
        }

        $required = [
            'key' => 'AWS_ACCESS_KEY_ID',
            'secret' => 'AWS_SECRET_ACCESS_KEY',
            'region' => 'AWS_DEFAULT_REGION',
            'bucket' => 'AWS_BUCKET',
            'endpoint' => 'AWS_ENDPOINT',
        ];

        $missing = [];

        foreach ($required as $configKey => $envKey) {
            if (! ($diskConfig[$configKey] ?? null)) {
                $missing[] = $envKey;
            }
        }

        if (! empty($missing)) {
            $this->error('Missing required MinIO/S3 configuration values:');
            foreach ($missing as $envKey) {
                $this->line("  - {$envKey}");
            }
            $this->line('Set them in your .env file or filesystem configuration, then try again.');
            return self::FAILURE;
        }

        $endpoint = $diskConfig['endpoint'];
        $isMinio = str_contains($endpoint, 'minio') || str_contains($endpoint, 'localhost') || str_contains($endpoint, '127.0.0.1');

        $this->line("Endpoint: {$endpoint}");
        $this->line('MinIO-style endpoint: ' . ($isMinio ? '<fg=green>yes</>' : '<fg=yellow>unknown</>'));
        $this->line('Quick mode: ' . ($quick ? '<fg=yellow>enabled</>' : '<fg=green>disabled</>'));

        if ($quick) {
            $this->info('✅ Configuration is valid for MinIO / S3 storage.');
            return self::SUCCESS;
        }

        return $this->testConnection($disk);
    }

    private function testConnection(string $disk): int
    {
        $this->info('Testing connection to MinIO / S3...');

        try {
            /** @var FilesystemAdapter $filesystem  */
            $filesystem = Storage::disk($disk);
            $driver = $filesystem->getDriver();

            if (method_exists($driver, 'listContents')) {
                // Flysystem/S3 can return a lazy listing, so force iteration to
                // ensure the client actually performs a network request.
                foreach ($driver->listContents('/', false) as $_item) {
                    break;
                }
            } else {
                $filesystem->exists('');
            }

            $this->info('✅ MinIO / S3 connection is working.');
            return self::SUCCESS;
        } catch (Exception $exception) {
            $this->error('❌ Failed to connect to MinIO / S3 storage.');
            $this->error($exception->getMessage());
            $this->line('Please verify AWS_* environment variables, endpoint URL, region, bucket name, and network access.');
            return self::FAILURE;
        }
    }
}
