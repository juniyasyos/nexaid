<?php

namespace App\Console\Commands;

use App\Domain\Iam\Models\Application;
use App\Services\JWTTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class CheckClientConnectivity extends Command
{
    protected $signature = 'iam:check-client
                            {app_key? : app_key dari aplikasi client yang ingin dicek. Kosongkan atau gunakan --all untuk mengecek seluruh aplikasi di DB}
                            {--all : Cek konektivitas seluruh aplikasi yang ada di database}
                            {--no-auth : Skip penambahan header JWT Bearer / signature}';

    protected $description = 'Cek konektivitas backchannel ke container/instance aplikasi client secara READ-ONLY (Tanpa membuat/mengubah data DB)';

    public function handle(): int
    {
        $appKeyOption = $this->argument('app_key');
        $checkAll = $this->option('all') || empty($appKeyOption);
        $noAuth = $this->option('no-auth');

        /** @var \Illuminate\Database\Eloquent\Collection<int, Application> $applications */
        if ($checkAll) {
            $applications = Application::all();
            if ($applications->isEmpty()) {
                $this->warn('Tidak ada aplikasi yang ditemukan di database.');
                return self::SUCCESS;
            }
        } else {
            $application = Application::where('app_key', $appKeyOption)->first();
            if (! $application) {
                $this->error("Aplikasi dengan app_key='{$appKeyOption}' tidak ditemukan di database.");
                return self::FAILURE;
            }
            $applications = collect([$application]);
        }

        $this->info("Memeriksa koneksi backchannel ke container/instance aplikasi (READ-ONLY mode)...");
        $this->line("");

        $results = [];
        $overallSuccess = true;

        foreach ($applications as $application) {
            $base = $application->backchannel_url ?: $application->callback_url;

            if (! $base) {
                $results[] = [
                    'App Key' => $application->app_key,
                    'Nama Aplikasi' => $application->name,
                    'URL Backchannel' => 'Belum dikonfigurasi',
                    'Status' => 'FAIL',
                    'HTTP Code' => 'N/A',
                    'Keterangan / Diagnosa' => 'URL backchannel_url maupun callback_url tidak diisi di DB',
                ];
                $overallSuccess = false;
                continue;
            }

            // Endpoints to check
            $endpoints = [
                '/api/iam/health',
                '/api/iam/client-roles',
            ];

            $appPassed = false;
            $lastStatus = 'N/A';
            $lastInfo = '';

            foreach ($endpoints as $endpointPath) {
                $endpointUrl = $this->buildUrl($application, $endpointPath);
                [$ok, $status, $message] = $this->executeEndpoint($application, $endpointUrl, $noAuth);
                $lastStatus = $status;
                $lastInfo = $message;

                if ($ok) {
                    $appPassed = true;
                    $lastInfo = "Endpoint {$endpointPath} merespons OK";
                    break;
                }
            }

            if (! $appPassed) {
                $overallSuccess = false;
            }

            $results[] = [
                'App Key' => $application->app_key,
                'Nama Aplikasi' => $application->name,
                'URL Backchannel' => $base,
                'Status' => $appPassed ? 'OK' : 'FAIL',
                'HTTP Code' => $lastStatus,
                'Keterangan / Diagnosa' => mb_strimwidth($lastInfo, 0, 85, '...'),
            ];
        }

        $this->table(['App Key', 'Nama Aplikasi', 'URL Backchannel', 'Status', 'HTTP Code', 'Keterangan / Diagnosa'], $results);

        if (!$overallSuccess) {
            $this->warn("\nTip Diagnosa Koneksi Container:");
            $this->line("1. Pastikan nama host / IP container pada 'backchannel_url' di DB dapat dijangkau dari container IAM ini.");
            $this->line("2. Periksa apakah port aplikasi client (misal :8000, :8001) terbuka di jaringan Docker / network container.");
            $this->line("3. Pastikan endpoint /api/iam/health atau /api/iam/client-roles sudah diimplementasikan di aplikasi client.");
        }

        return $overallSuccess ? self::SUCCESS : self::FAILURE;
    }

    protected function buildUrl(Application $application, string $path): string
    {
        $base = $application->backchannel_url ?: $application->callback_url;

        if (! $base) {
            throw new \RuntimeException('No backchannel or callback url configured for this application');
        }

        $base = rtrim($base, '/');
        return $base . $path . '?app_key=' . urlencode($application->app_key);
    }

    protected function executeEndpoint(Application $application, string $url, bool $noAuth): array
    {
        $headers = ['Accept' => 'application/json'];

        if (! $noAuth) {
            $token = app(JWTTokenService::class)->generateBackchannelToken($application);
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        try {
            $response = Http::withHeaders($headers)->timeout(8)->get($url);

            return [
                $response->successful(),
                $response->status(),
                $response->body() ?: "HTTP Status {$response->status()}",
            ];
        } catch (\Throwable $e) {
            return [false, 'ERR', $e->getMessage()];
        }
    }
}
