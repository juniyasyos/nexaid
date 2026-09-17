<?php

namespace App\Console\Commands;

use App\Domain\Iam\Models\Application;
use App\Services\JWTTokenService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

        $tableResults = [];
        $diagnosticDetails = [];
        $overallSuccess = true;

        foreach ($applications as $application) {
            $base = $application->backchannel_url ?: $application->callback_url;

            if (! $base) {
                $overallSuccess = false;
                $tableResults[] = [
                    'App Key' => $application->app_key,
                    'Nama Aplikasi' => $application->name,
                    'URL Backchannel' => 'Belum dikonfigurasi',
                    'Status' => 'FAIL',
                    'HTTP Code' => 'N/A',
                    'Ringkasan Error' => 'URL Belum Diisi',
                ];

                $diagnosticDetails[] = [
                    'app_key' => $application->app_key,
                    'app_name' => $application->name,
                    'url' => 'N/A',
                    'status' => 'N/A',
                    'summary' => 'Konfigurasi URL Kosong',
                    'cause' => 'Kolom backchannel_url maupun callback_url pada tabel applications di database masih kosong.',
                    'solution' => 'Isikan kolom backchannel_url pada aplikasi ini di database/dashboard IAM (contoh: http://client-app:8000).',
                ];

                Log::warning('iam.backchannel_check_missing_url', [
                    'app_key' => $application->app_key,
                    'app_id' => $application->id,
                ]);
                continue;
            }

            // Endpoints to probe
            $endpoints = [
                '/api/iam/health',
                '/api/iam/client-roles',
            ];

            $appPassed = false;
            $lastStatus = 'N/A';
            $lastErrorRaw = '';
            $lastEndpointTested = '';

            foreach ($endpoints as $endpointPath) {
                $endpointUrl = $this->buildUrl($application, $endpointPath);
                $lastEndpointTested = $endpointUrl;

                [$ok, $status, $message] = $this->executeEndpoint($application, $endpointUrl, $noAuth);
                $lastStatus = (string) $status;
                $lastErrorRaw = $message;

                if ($ok) {
                    $appPassed = true;
                    break;
                }
            }

            if ($appPassed) {
                $tableResults[] = [
                    'App Key' => $application->app_key,
                    'Nama Aplikasi' => $application->name,
                    'URL Backchannel' => $base,
                    'Status' => 'OK',
                    'HTTP Code' => $lastStatus,
                    'Ringkasan Error' => 'Koneksi Berhasil (OK)',
                ];

                Log::info('iam.backchannel_check_success', [
                    'app_key' => $application->app_key,
                    'url' => $base,
                    'status_code' => $lastStatus,
                ]);
            } else {
                $overallSuccess = false;
                $diagnosis = $this->analyzeError($application, $lastEndpointTested, $lastStatus, $lastErrorRaw);

                $tableResults[] = [
                    'App Key' => $application->app_key,
                    'Nama Aplikasi' => $application->name,
                    'URL Backchannel' => $base,
                    'Status' => 'FAIL',
                    'HTTP Code' => $lastStatus,
                    'Ringkasan Error' => $diagnosis['summary'],
                ];

                $diagnosticDetails[] = array_merge([
                    'app_key' => $application->app_key,
                    'app_name' => $application->name,
                    'url' => $lastEndpointTested,
                    'status' => $lastStatus,
                    'raw_error' => $lastErrorRaw,
                ], $diagnosis);

                Log::error('iam.backchannel_check_failed', [
                    'app_key' => $application->app_key,
                    'url' => $lastEndpointTested,
                    'status_code' => $lastStatus,
                    'error_summary' => $diagnosis['summary'],
                    'cause' => $diagnosis['cause'],
                    'solution' => $diagnosis['solution'],
                    'raw_message' => $lastErrorRaw,
                ]);
            }
        }

        // Display Summary Table
        $this->table(['App Key', 'Nama Aplikasi', 'URL Backchannel', 'Status', 'HTTP Code', 'Ringkasan Error'], $tableResults);

        // Display Detailed Diagnostic Logs & Explanations for Failed Connections
        if (! empty($diagnosticDetails)) {
            $this->line("");
            $this->warn("=========================================================================================");
            $this->warn(" 🔍 ANALISA DIAGNOSA ERROR KONEKSI BACKCHANNEL (" . count($diagnosticDetails) . " Aplikasi Bermasalah)");
            $this->warn("=========================================================================================");

            foreach ($diagnosticDetails as $index => $diag) {
                $num = $index + 1;
                $this->error("\n[#{$num}] Aplikasi: {$diag['app_name']} (app_key: {$diag['app_key']})");
                $this->line(" 📍 URL Target : {$diag['url']}");
                $this->line(" 📊 Status Code : <fg=red>{$diag['status']}</>");
                $this->line(" 💡 Ringkasan   : <fg=yellow>{$diag['summary']}</>");
                $this->line(" 📌 Penjelasan  : {$diag['cause']}");
                $this->line(" 🛠️ Solusi/Aksi : <fg=cyan>{$diag['solution']}</>");

                if (! empty($diag['raw_error']) && $diag['raw_error'] !== $diag['summary']) {
                    $this->line(" 📄 Log Mentah  : <fg=gray>" . mb_strimwidth($diag['raw_error'], 0, 150, '...') . "</>");
                }
            }
            $this->line("");
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
            try {
                $token = app(JWTTokenService::class)->generateBackchannelToken($application);
                $headers['Authorization'] = 'Bearer ' . $token;
            } catch (\Throwable $e) {
                return [false, 'JWT_ERR', 'Gagal membubuhkan token JWT: ' . $e->getMessage()];
            }
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

    /**
     * Menganalisa error koneksi dan memberikan penjelasan diagnosa serta solusi teknis.
     */
    protected function analyzeError(Application $app, string $url, string $status, string $rawError): array
    {
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? 'unknown_host';

        // 1. DNS / Host Unresolvable (cURL Error 6)
        if (str_contains($rawError, 'Could not resolve host') || str_contains($rawError, 'cURL error 6')) {
            return [
                'summary' => 'DNS / Host Unresolvable',
                'cause' => "Hostname '{$host}' pada URL backchannel tidak dapat di-resolve oleh DNS container IAM.",
                'solution' => "Pastikan nama host pada 'backchannel_url' di database sesuai dengan nama service container Docker / DNS network (misal: http://{$app->app_key}:8000 atau IP container).",
            ];
        }

        // 2. Connection Refused / Port Closed (cURL Error 7)
        if (str_contains($rawError, 'Failed to connect') || str_contains($rawError, 'Connection refused') || str_contains($rawError, 'cURL error 7')) {
            return [
                'summary' => 'Connection Refused (Port Closed)',
                'cause' => "Container host '{$host}' dapat dicapai, namun port aplikasi client menolak sambungan (service mati atau port tidak terbuka).",
                'solution' => "1. Jalankan `docker ps` untuk memastikan container client sedang running.\n 2. Periksa nomor port pada backchannel_url.\n 3. Pastikan container IAM dan container client berada dalam satu Docker Network yang sama.",
            ];
        }

        // 3. Connection Timeout (cURL Error 28)
        if (str_contains($rawError, 'timed out') || str_contains($rawError, 'cURL error 28')) {
            return [
                'summary' => 'Connection Timeout (>8s)',
                'cause' => "Permintaan dikirim ke '{$host}' namun tidak ada balasan hingga batas waktu (timeout 8 detik).",
                'solution' => "Periksa aturan firewall/security group antar container, atau periksa apakah container client mengalami deadlock/blocking di server-side.",
            ];
        }

        // 4. SSL Certificate Issues (cURL Error 60 / 35)
        if (str_contains($rawError, 'SSL certificate') || str_contains($rawError, 'cURL error 60') || str_contains($rawError, 'cURL error 35')) {
            return [
                'summary' => 'SSL Certificate Error',
                'cause' => "Sertifikat SSL/TLS pada URL HTTPS '{$host}' tidak dapat diverifikasi (self-signed atau expired).",
                'solution' => "Gunakan URL HTTP internal antar-container Docker (misal http://{$host}), atau konfigurasikan SSL Certificate yang valid pada Nginx client.",
            ];
        }

        // 5. HTTP 401 Unauthorized / 403 Forbidden
        if ($status === '401' || $status === '403') {
            return [
                'summary' => "HTTP {$status} (Auth Refused)",
                'cause' => "Container client menolak token JWT / kredensial backchannel dari IAM server.",
                'solution' => "1. Pastikan `iam.signing_key` / `app.key` pada IAM cocok dengan public key / verification key pada `.env` client.\n 2. Pastikan `app_key` ('{$app->app_key}') pada IAM dan client persis sama.\n 3. Cek sinkronisasi jam/waktu (NTP clock) antar container.",
            ];
        }

        // 6. HTTP 404 Not Found
        if ($status === '404') {
            return [
                'summary' => 'HTTP 404 (Route Not Found)',
                'cause' => "Container client dapat dihubungi, namun endpoint route `/api/iam/health` atau `/api/iam/client-roles` tidak terdaftar.",
                'solution' => "Daftarkan route endpoint backchannel (`/api/iam/health` atau `/api/iam/client-roles`) pada `routes/api.php` aplikasi client.",
            ];
        }

        // 7. HTTP 500 / 502 / 503 Internal Server Error
        if (in_array($status, ['500', '502', '503', '504'])) {
            return [
                'summary' => "HTTP {$status} (Client Server Error)",
                'cause' => "Aplikasi client menerima request namun mengalami crash internal / exception saat memprosesnya.",
                'solution' => "Buka dan periksa log internal aplikasi client pada `storage/logs/laravel.log` di dalam container client untuk melihat detail error traceback.",
            ];
        }

        // Default Fallback Error Analysis
        return [
            'summary' => "HTTP {$status} Error",
            'cause' => "Koneksi mengalami kendala: " . ($rawError ?: "HTTP status {$status}"),
            'solution' => "Periksa konfigurasi `backchannel_url` di DB, ketersediaan service di container client, dan log pada kedua container.",
        ];
    }
}
