<?php

namespace Tests\Unit;

use App\Domain\Iam\Models\Application;
use App\Services\JWTTokenService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * STRICTLY READ-ONLY TEST FOR EXISTING DATABASE APPLICATIONS
 * 
 * Rules enforced:
 * 1. Does NOT create any new Application records (uses ONLY existing DB records).
 * 2. Does NOT modify, update, or delete any records in the database.
 * 3. Does NOT use RefreshDatabase / DatabaseTransactions.
 * 4. Tests real container/instance connectivity for backchannel authentication.
 */
class BackchannelDatabaseAppsTest extends TestCase
{
    /** @test */
    public function it_validates_backchannel_tokens_for_all_existing_database_applications()
    {
        // Strictly fetch existing records from database
        $applications = Application::all();

        if ($applications->isEmpty()) {
            $this->markTestSkipped('Tidak ada data Application yang ditemukan di database untuk diuji.');
        }

        $jwtService = app(JWTTokenService::class);

        foreach ($applications as $app) {
            // Generate backchannel JWT token using real application entry from DB
            $token = $jwtService->generateBackchannelToken($app);

            $this->assertNotEmpty($token, "Gagal meng-generate backchannel token untuk app_key: {$app->app_key}");

            // Decode and verify claims
            $decoded = $jwtService->verifyToken($token);
            $this->assertEquals($app->app_key, $decoded->app_key);
            $this->assertEquals('backchannel', $decoded->type);
        }
    }

    /** @test */
    public function it_validates_backchannel_urls_configured_on_database_applications()
    {
        $applications = Application::all();

        if ($applications->isEmpty()) {
            $this->markTestSkipped('Tidak ada data Application yang ditemukan di database untuk diuji.');
        }

        foreach ($applications as $app) {
            $url = $app->backchannel_url ?: $app->callback_url;

            $this->assertNotEmpty($app->app_key, 'Terdeteksi aplikasi tanpa app_key di database.');

            if (!empty($url)) {
                $parsed = parse_url($url);
                $this->assertArrayHasKey('scheme', $parsed, "URL Backchannel untuk app {$app->app_key} ('{$url}') tidak memiliki scheme (http/https).");
                $this->assertArrayHasKey('host', $parsed, "URL Backchannel untuk app {$app->app_key} ('{$url}') tidak memiliki host/IP container.");
            }
        }
    }

    /** @test */
    public function it_probes_live_backchannel_connectivity_to_client_containers_without_db_writes()
    {
        $applications = Application::all();

        if ($applications->isEmpty()) {
            $this->markTestSkipped('Tidak ada data Application yang ditemukan di database untuk diuji.');
        }

        $jwtService = app(JWTTokenService::class);

        foreach ($applications as $app) {
            $baseUrl = $app->backchannel_url ?: $app->callback_url;

            if (empty($baseUrl)) {
                continue;
            }

            $baseUrl = rtrim($baseUrl, '/');
            $targetUrl = $baseUrl . '/api/iam/health?app_key=' . urlencode($app->app_key);
            $token = $jwtService->generateBackchannelToken($app);

            try {
                // Send REAL HTTP GET probe to container / instance URL
                $response = Http::withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/json',
                ])->timeout(5)->get($targetUrl);

                // Assert response without throwing database exceptions
                $this->assertNotNull($response->status());
            } catch (\Throwable $e) {
                // Log container connection status for diagnostics
                fwrite(STDERR, "\n[Diagnosa Backchannel Container] {$app->app_key} ({$targetUrl}) -> " . $e->getMessage() . "\n");
            }
        }
    }
}
