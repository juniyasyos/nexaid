<?php

namespace Tests\Unit;

use App\Domain\Iam\Models\Application;
use App\Services\JWTTokenService;
use App\Domain\Iam\Services\ApplicationRoleSyncService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

/**
 * STRICTLY READ-ONLY UNIT TEST FOR BACKCHANNEL CONNECTIVITY
 * Does NOT use RefreshDatabase or any database mutation trait.
 * Will NOT drop, truncate, or alter any database tables or records.
 */
class BackchannelConnectivityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
    }

    /** @test */
    public function it_generates_valid_backchannel_jwt_token_without_database_writes()
    {
        // Pure in-memory model instance (not saved to DB)
        $application = new Application([
            'app_key' => 'test_client_app',
            'name' => 'Test Client Application',
        ]);

        $jwtService = app(JWTTokenService::class);
        $token = $jwtService->generateBackchannelToken($application);

        $this->assertNotEmpty($token);

        $decoded = $jwtService->verifyToken($token);
        $this->assertEquals('test_client_app', $decoded->app_key);
        $this->assertEquals('backchannel', $decoded->type);
        $this->assertGreaterThan($decoded->iat, $decoded->exp);
    }

    /** @test */
    public function it_prioritizes_backchannel_url_over_callback_url()
    {
        $application = new Application([
            'app_key' => 'test_app',
            'callback_url' => 'https://callback.client.test',
            'backchannel_url' => 'https://backchannel.client.test:8443',
        ]);

        $service = new class extends ApplicationRoleSyncService {
            public function testGetBackchannelUrl(Application $app): ?string
            {
                return $this->getBackchannelUrl($app);
            }
        };

        $url = $service->testGetBackchannelUrl($application);
        $this->assertEquals('https://backchannel.client.test:8443', $url);
    }

    /** @test */
    public function it_falls_back_to_callback_url_when_backchannel_url_is_null()
    {
        $application = new Application([
            'app_key' => 'test_app',
            'callback_url' => 'https://callback.client.test',
            'backchannel_url' => null,
        ]);

        $service = new class extends ApplicationRoleSyncService {
            public function testGetBackchannelUrl(Application $app): ?string
            {
                return $this->getBackchannelUrl($app);
            }
        };

        $url = $service->testGetBackchannelUrl($application);
        $this->assertEquals('https://callback.client.test', $url);
    }

    /** @test */
    public function it_sends_backchannel_request_with_jwt_bearer_token()
    {
        Config::set('iam.backchannel_method', 'jwt');
        Config::set('iam.backchannel_verify', true);

        Http::fake([
            'https://client.test/api/iam/*' => Http::response(['status' => 'ok'], 200),
        ]);

        $application = new Application([
            'app_key' => 'test_jwt_app',
            'callback_url' => 'https://client.test',
        ]);

        $service = new class extends ApplicationRoleSyncService {
            public function testSendGet(Application $app, string $url)
            {
                return $this->sendBackchannelGetRequest($app, $url);
            }
        };

        $response = $service->testSendGet($application, 'https://client.test/api/iam/health');

        $this->assertTrue($response->successful());
        $this->assertEquals(['status' => 'ok'], $response->json());

        Http::assertSent(function ($request) {
            return $request->url() === 'https://client.test/api/iam/health'
                && str_starts_with($request->header('Authorization')[0] ?? '', 'Bearer ');
        });
    }

    /** @test */
    public function it_sends_backchannel_request_with_hmac_signature()
    {
        Config::set('iam.backchannel_method', 'hmac');
        Config::set('iam.backchannel_verify', true);
        Config::set('iam.sso_secret', 'secret-key-123');

        Http::fake([
            'https://client.test/api/iam/*' => Http::response(['status' => 'ok'], 200),
        ]);

        $application = new Application([
            'app_key' => 'test_hmac_app',
            'secret' => 'secret-key-123',
            'callback_url' => 'https://client.test',
        ]);

        $service = new class extends ApplicationRoleSyncService {
            public function testSendGet(Application $app, string $url)
            {
                return $this->sendBackchannelGetRequest($app, $url);
            }
        };

        $response = $service->testSendGet($application, 'https://client.test/api/iam/health');

        $this->assertTrue($response->successful());

        Http::assertSent(function ($request) {
            $sigHeader = config('sso.backchannel.signature_header', 'IAM-Signature');
            return $request->url() === 'https://client.test/api/iam/health'
                && !empty($request->header($sigHeader))
                && $request->header('X-IAM-App-Key')[0] === 'test_hmac_app';
        });
    }

    /** @test */
    public function it_sends_unauthenticated_request_when_verification_is_disabled()
    {
        Config::set('iam.backchannel_verify', false);

        Http::fake([
            'https://client.test/api/iam/*' => Http::response(['status' => 'ok'], 200),
        ]);

        $application = new Application([
            'app_key' => 'test_noauth_app',
            'callback_url' => 'https://client.test',
        ]);

        $service = new class extends ApplicationRoleSyncService {
            public function testSendGet(Application $app, string $url)
            {
                return $this->sendBackchannelGetRequest($app, $url);
            }
        };

        $response = $service->testSendGet($application, 'https://client.test/api/iam/health');

        $this->assertTrue($response->successful());

        Http::assertSent(function ($request) {
            return $request->url() === 'https://client.test/api/iam/health'
                && empty($request->header('Authorization'))
                && empty($request->header('IAM-Signature'));
        });
    }
}
