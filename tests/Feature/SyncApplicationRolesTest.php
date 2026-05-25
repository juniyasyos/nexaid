<?php

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Services\ApplicationRoleSyncService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'iam.backchannel_method' => 'hmac',
        'iam.backchannel_verify' => true,
    ]);
    Http::fake();
});

it('fetches roles from the preferred client endpoint with HMAC header when verification enabled', function () {
    Http::fake([
        'http://client.test/api/iam/client-roles*' => Http::response([
            'app_key' => 'xyz',
            'roles' => [
                [
                    'id' => 1,
                    'slug' => 'admin',
                    'name' => 'Admin',
                    'description' => 'Administrator role',
                    'is_system' => true,
                ],
            ],
            'total' => 1,
        ], 200),
        'http://client.test/api/iam/sync-roles*' => Http::response([], 404),
    ]);

    $app = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'xyz',
    ]);

    $service = new ApplicationRoleSyncService();
    $result = $service->fetchClientRoles($app);

    expect($result)->toMatchArray([
        'success' => true,
        'app_key' => 'xyz',
        'total' => 1,
    ]);

    Http::assertSent(function ($request) use ($app) {
        $urlOK = $request->url() === 'http://client.test/api/iam/client-roles?app_key=xyz';
        $header = config('sso.backchannel.signature_header');
        return $urlOK && $request->method() === 'GET' && ! empty($request->header($header));
    });
});

it('falls back to the legacy sync endpoint when the preferred endpoint is missing', function () {
    Http::fake([
        'http://client.test/api/iam/client-roles*' => Http::response([], 404),
        'http://client.test/api/iam/sync-roles*' => Http::response([
            'app_key' => 'xyz',
            'roles' => [],
            'total' => 0,
        ], 200),
    ]);

    $app = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'xyz',
    ]);

    $service = new ApplicationRoleSyncService();
    $result = $service->fetchClientRoles($app);

    expect($result)->toMatchArray([
        'success' => true,
        'app_key' => 'xyz',
        'total' => 0,
    ]);

    Http::assertSent(function ($request) {
        return $request->url() === 'http://client.test/api/iam/client-roles?app_key=xyz';
    });

    Http::assertSent(function ($request) {
        return $request->url() === 'http://client.test/api/iam/sync-roles?app_key=xyz';
    });
});

it('returns client roles through the api controller', function () {
    Http::fake([
        'http://client.test/api/iam/client-roles*' => Http::response([
            'app_key' => 'xyz',
            'roles' => [
                [
                    'id' => 1,
                    'slug' => 'editor',
                    'name' => 'Editor',
                    'description' => null,
                    'is_system' => false,
                ],
            ],
            'total' => 1,
        ], 200),
        'http://client.test/api/iam/sync-roles*' => Http::response([], 404),
    ]);

    $app = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'xyz',
    ]);

    $response = $this->getJson('/api/iam/client-roles?app_key=xyz');

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'app_key' => 'xyz',
            'total' => 1,
        ])
        ->assertJsonStructure([
            'success',
            'app_key',
            'roles' => [
                ['id', 'slug', 'name', 'description', 'is_system'],
            ],
            'total',
        ]);

    Http::assertSent(function ($request) use ($app) {
        return $request->url() === 'http://client.test/api/iam/client-roles?app_key=xyz'
            && $request->method() === 'GET';
    });
});

it('sends no auth headers when verification disabled', function () {
    config(['iam.backchannel_verify' => false]);

    Http::fake([
        'http://client.test/api/iam/client-roles*' => Http::response([
            'app_key' => 'xyz',
            'roles' => [],
            'total' => 0,
        ], 200),
        'http://client.test/api/iam/sync-roles*' => Http::response([], 404),
    ]);

    $app = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'xyz',
    ]);

    $service = new ApplicationRoleSyncService();
    $service->fetchClientRoles($app);

    Http::assertSent(function ($request) use ($app) {
        return $request->url() === 'http://client.test/api/iam/client-roles?app_key=xyz'
            && $request->method() === 'GET'
            && empty($request->header(config('sso.backchannel.signature_header')))
            && empty($request->header('Authorization'));
    });
});
