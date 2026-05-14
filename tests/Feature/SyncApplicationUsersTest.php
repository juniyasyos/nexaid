<?php

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\ApplicationRole;
use App\Domain\Iam\Models\AccessProfile;
use App\Jobs\SyncApplicationUsers;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use App\Models\User;
use App\Models\UnitKerja;
use App\Domain\Iam\Services\ApplicationUserSyncService;

beforeEach(function () {
    // default to legacy hmac behaviour and keep verification enabled
    config([
        'iam.backchannel_method' => 'hmac',
        'iam.backchannel_verify' => true,
    ]);
    Queue::fake();
    Http::fake();
});

it('queues a job that sends HMAC header when verification enabled', function () {
    $app = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'abc',
    ]);

    // legacy behaviour: no profile restriction
    SyncApplicationUsers::dispatch([]);

    Http::assertSent(function ($request) use ($app) {
        $urlOK = $request->url() === 'http://client.test/api/iam/sync-users?app_key=abc';
        $header = config('sso.backchannel.signature_header');
        return $urlOK && ! empty($request->header($header));
    });
});

it('omits auth headers when verification disabled', function () {
    config(['iam.backchannel_verify' => false]);

    $app = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'abc',
    ]);

    SyncApplicationUsers::dispatch([]);

    Http::assertSent(function ($request) use ($app) {
        return $request->url() === 'http://client.test/api/iam/sync-users?app_key=abc'
            && empty($request->header(config('sso.backchannel.signature_header')))
            && empty($request->header('Authorization'));
    });
});


// when users are synced we no longer assign roles directly; the service
// should pair the user with access profiles that contain the requested
// slugs for the application.
it('syncs a client user by attaching the appropriate access profiles', function () {
    $app = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'abc',
    ]);

    // create two application roles and a pair of profiles
    $role1 = ApplicationRole::create([
        'application_id' => $app->id,
        'slug' => 'alpha',
        'name' => 'Alpha',
    ]);
    $role2 = ApplicationRole::create([
        'application_id' => $app->id,
        'slug' => 'beta',
        'name' => 'Beta',
    ]);

    $profile1 = AccessProfile::factory()->create();
    $profile1->roles()->attach($role1->id);
    $profile2 = AccessProfile::factory()->create();
    $profile2->roles()->attach($role2->id);

    // fake the client returning a single user with the 'alpha' role only
    Http::fake([
        '*' => Http::response([
            'users' => [
                ['nip' => '111', 'name' => 'Foo', 'email' => 'foo@example.com', 'roles' => ['alpha']],
            ],
        ], 200),
    ]);

    $service = new App\Domain\Iam\Services\ApplicationUserSyncService();
    $result = $service->syncUsers($app);

    $user = User::where('nip', '111')->first();
    expect($user)->not->toBeNull();

    // user should be linked only to profile1 and not profile2
    expect($user->accessProfiles->pluck('id')->toArray())->toContain($profile1->id);
    expect($user->accessProfiles->pluck('id')->toArray())->not->toContain($profile2->id);

    // we also expect the returned iam_users array to show the alpha role
    expect($result['iam_users'][0]['roles'])->toContain('alpha');

    // direct application_roles table should remain empty for this user
    expect($user->applicationRoles)->toBeEmpty();
});

it('previews sync without creating/updating users and shows role to bundle mapping', function () {
    $app = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'abc',
    ]);

    $role1 = ApplicationRole::create([
        'application_id' => $app->id,
        'slug' => 'alpha',
        'name' => 'Alpha',
    ]);
    $role2 = ApplicationRole::create([
        'application_id' => $app->id,
        'slug' => 'beta',
        'name' => 'Beta',
    ]);

    $profile1 = AccessProfile::factory()->create();
    $profile1->roles()->attach($role1->id);
    $profile2 = AccessProfile::factory()->create();
    $profile2->roles()->attach($role2->id);

    Http::fake([
        '*' => Http::response([
            'users' => [
                ['nip' => '111', 'name' => 'Foo', 'email' => 'foo@example.com', 'roles' => ['alpha', 'beta']],
            ],
        ], 200),
    ]);

    $service = new ApplicationUserSyncService();
    $result = $service->previewUsers($app);

    expect($result['success'])->toBeTrue();
    expect($result['preview'][0]['client_role_slugs'])->toEqualCanonicalizing(['alpha', 'beta']);
    expect($result['preview'][0]['planned_profile_assignment']['candidate_profiles'])
        ->toHaveCount(2);
    expect($result['preview'][0]['planned_profile_assignment']['missing_role_slugs'])
        ->toBeEmpty();
    expect(User::where('nip', '111')->exists())->toBeFalse();
});


// job should only attach profiles that were selected in the modal
it('job respects chosen bundles and ignores the rest', function () {
    $app = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'klm',
    ]);

    $roleA = ApplicationRole::create([
        'application_id' => $app->id,
        'slug' => 'a',
        'name' => 'A',
    ]);
    $roleB = ApplicationRole::create([
        'application_id' => $app->id,
        'slug' => 'b',
        'name' => 'B',
    ]);

    $profileA = AccessProfile::factory()->create();
    $profileA->roles()->attach($roleA->id);
    $profileB = AccessProfile::factory()->create();
    $profileB->roles()->attach($roleB->id);

    Http::fake([
        '*' => Http::response([
            'users' => [
                ['nip' => '333', 'name' => 'Baz', 'email' => 'baz@example.com', 'roles' => ['a', 'b']],
            ],
        ], 200),
    ]);

    $job = new SyncApplicationUsers([
        $profileA->id,
    ]);
    $job->handle();

    $user = App\Models\User::where('nip', '333')->first();
    expect($user)->not->toBeNull();

    // only profileA attached, profileB ignored
    expect($user->accessProfiles->pluck('id')->toArray())->toEqualCanonicalizing([$profileA->id]);
});


// dispatching via application record should also work and limit to that app
it('dispatching with an application restricts sync to that app', function () {
    $app1 = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'one',
    ]);
    $app2 = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'two',
    ]);

    $role1 = ApplicationRole::create([
        'application_id' => $app1->id,
        'slug' => 'x',
        'name' => 'X',
    ]);
    $role2 = ApplicationRole::create([
        'application_id' => $app2->id,
        'slug' => 'y',
        'name' => 'Y',
    ]);

    $profile1 = App\Domain\Iam\Models\AccessProfile::factory()->create();
    $profile1->roles()->attach($role1->id);
    $profile2 = App\Domain\Iam\Models\AccessProfile::factory()->create();
    $profile2->roles()->attach($role2->id);

    Http::fake([
        '*' => Http::response([
            'users' => [
                ['nip' => '444', 'name' => 'Qux', 'email' => 'qux@example.com', 'roles' => ['x', 'y']],
            ],
        ], 200),
    ]);

    SyncApplicationUsers::dispatch($app1, [$profile1->id, $profile2->id]);

    $user = User::where('nip', '444')->first();
    expect($user)->not->toBeNull();

    // only profile1 attached, because job limited to app1
    expect($user->accessProfiles->pluck('id')->toArray())->toEqualCanonicalizing([$profile1->id]);
});


it('does not remove existing bundles when client roles shrink', function () {
    $app = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'shrink',
    ]);

    $role1 = App\Domain\Iam\Models\ApplicationRole::create([
        'application_id' => $app->id,
        'slug' => 'one',
        'name' => 'One',
    ]);
    $role2 = App\Domain\Iam\Models\ApplicationRole::create([
        'application_id' => $app->id,
        'slug' => 'two',
        'name' => 'Two',
    ]);

    $profile1 = App\Domain\Iam\Models\AccessProfile::factory()->create();
    $profile1->roles()->attach($role1->id);
    $profile2 = App\Domain\Iam\Models\AccessProfile::factory()->create();
    $profile2->roles()->attach($role2->id);

    $user = App\Models\User::factory()->create(['nip' => '777']);
    $user->accessProfiles()->attach([$profile1->id, $profile2->id]);

    // client returns only role1, so natural bundle calculation would yield
    // profile1 only – but we expect profile2 to remain attached
    Http::fake([
        '*' => Http::response([
            'users' => [
                ['nip' => '777', 'name' => 'Keep', 'email' => 'keep@example.com', 'roles' => ['one']],
            ],
        ], 200),
    ]);

    $service = new App\Domain\Iam\Services\ApplicationUserSyncService();
    $service->syncUsers($app);

    $fresh = $user->fresh();
    expect($fresh->accessProfiles->pluck('id')->toArray())
        ->toEqualCanonicalizing([$profile1->id, $profile2->id]);
});

it('pushes IAM users to client when user_sync_mode is push', function () {
    config(['iam.user_sync_mode' => 'push']);

    $app = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'abc',
    ]);

    $role = ApplicationRole::create([
        'application_id' => $app->id,
        'slug' => 'direct',
        'name' => 'Direct Role',
    ]);

    $user = User::factory()->create(['nip' => '999', 'email' => 'push@example.com']);
    $user->applicationRoles()->attach($role->id, ['application_id' => $app->id]);

    Http::fake([
        'http://client.test/api/iam/push-users*' => Http::response(['success' => true], 200),
    ]);

    $service = new ApplicationUserSyncService();
    $result = $service->syncUsers($app);

    expect($result['success'])->toBeTrue();
    expect($result['message'])->toBe('Push completed');

    Http::assertSent(function ($request) use ($app) {
        return $request->url() === 'http://client.test/api/iam/push-users?app_key=abc'
            && $request->method() === 'POST'
            && collect($request->data()['users'])->pluck('email')->contains('push@example.com');
    });
});

it('includes unit kerja descriptions when pushing IAM users to a client', function () {
    config(['iam.user_sync_mode' => 'push']);

    $app = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'unit-desc',
    ]);

    $role = ApplicationRole::create([
        'application_id' => $app->id,
        'slug' => 'direct',
        'name' => 'Direct Role',
    ]);

    $unitKerja = UnitKerja::create([
        'unit_name' => 'Rekam Medis',
        'description' => 'Mengelola rekam medis pasien',
    ]);

    $user = User::create([
        'nip' => '1009',
        'name' => 'Unit Tester',
        'email' => 'unit@example.com',
        'password' => 'secret123',
        'status' => 'active',
    ]);
    $user->applicationRoles()->attach($role->id, ['application_id' => $app->id]);
    $user->unitKerjas()->attach($unitKerja->id);

    Http::fake([
        'http://client.test/api/iam/push-users*' => Http::response(['success' => true], 200),
    ]);

    $service = new ApplicationUserSyncService();
    $service->syncUsers($app);

    Http::assertSent(function ($request) use ($user, $unitKerja) {
        $unitPayload = data_get($request->data(), 'users.0.unit_kerja.0');

        return $request->method() === 'POST'
            && data_get($request->data(), 'users.0.email') === $user->email
            && data_get($unitPayload, 'id') === $unitKerja->id
            && data_get($unitPayload, 'unit_name') === $unitKerja->unit_name
            && data_get($unitPayload, 'description') === $unitKerja->description;
    });
});

it('pushes only the targeted IAM user when a user id is provided', function () {
    config(['iam.user_sync_mode' => 'push']);

    $app = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'targeted',
    ]);

    $role = ApplicationRole::create([
        'application_id' => $app->id,
        'slug' => 'direct',
        'name' => 'Direct Role',
    ]);

    $targetUser = User::factory()->create(['nip' => '1001', 'email' => 'target@example.com']);
    $targetUser->applicationRoles()->attach($role->id, ['application_id' => $app->id]);

    $otherUser = User::factory()->create(['nip' => '1002', 'email' => 'other@example.com']);
    $otherUser->applicationRoles()->attach($role->id, ['application_id' => $app->id]);

    Http::fake([
        'http://client.test/api/iam/push-users*' => Http::response(['success' => true], 200),
    ]);

    $service = new ApplicationUserSyncService();
    $service->syncUsers($app, $targetUser->id);

    Http::assertSent(function ($request) use ($targetUser) {
        $users = collect($request->data()['users'] ?? []);

        return $request->method() === 'POST'
            && $users->count() === 1
            && $users->pluck('email')->all() === [$targetUser->email];
    });
});

it('includes users with a direct role when computing iam_users (no SQL ambiguity)', function () {
    config(['iam.user_sync_mode' => 'push']);

    $app = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'xyz',
    ]);

    $role = App\Domain\Iam\Models\ApplicationRole::create([
        'application_id' => $app->id,
        'slug' => 'direct',
        'name' => 'Direct Role',
    ]);

    $user = App\Models\User::factory()->create(['nip' => '999', 'email' => 'direct@example.com']);
    $user->applicationRoles()->attach($role->id, ['application_id' => $app->id]);

    Http::fake([
        'http://client.test/api/iam/push-users*' => Http::response(['success' => true], 200),
    ]);

    $service = new App\Domain\Iam\Services\ApplicationUserSyncService();
    $result = $service->syncUsers($app);

    expect($result['success'])->toBeTrue();
    expect($result['iam_users'])->toHaveCount(1);
    expect(collect($result['iam_users'])->pluck('id')->toArray())->toContain($user->id);
});

it('does not create a new access profile if none exist for a role', function () {
    $app = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'xyz',
    ]);

    $role = App\Domain\Iam\Models\ApplicationRole::create([
        'application_id' => $app->id,
        'slug' => 'gamma',
        'name' => 'Gamma',
    ]);

    Http::fake([
        '*' => Http::response([
            'users' => [
                ['nip' => '222', 'name' => 'Bar', 'email' => 'bar@example.com', 'roles' => ['gamma']],
            ],
        ], 200),
    ]);

    $service = new App\Domain\Iam\Services\ApplicationUserSyncService();
    $service->syncUsers($app);

    $user = User::where('nip', '222')->first();
    expect($user)->not->toBeNull();

    // no profile should be created automatically
    $profile = AccessProfile::where('slug', 'xyz_gamma')->first();
    expect($profile)->toBeNull();

    // user should not be attached to any profile for that role
    expect($user->accessProfiles->pluck('id')->toArray())->toBeEmpty();
});

it('reuses existing auto profile and updates it instead of duplicating', function () {
    $app = Application::factory()->create([
        'callback_url' => 'http://client.test',
        'app_key' => 'xyz',
    ]);

    $role = App\Domain\Iam\Models\ApplicationRole::create([
        'application_id' => $app->id,
        'slug' => 'gamma',
        'name' => 'Gamma',
    ]);

    $existingProfile = AccessProfile::create([
        'slug' => 'xyz_gamma',
        'name' => 'gamma (existing)',
        'description' => 'Existing profile',
        'is_system' => false,
        'is_active' => true,
    ]);

    Http::fake([
        '*' => Http::response([
            'users' => [
                ['nip' => '222', 'name' => 'Bar', 'email' => 'bar@example.com', 'roles' => ['gamma']],
            ],
        ], 200),
    ]);

    $service = new App\Domain\Iam\Services\ApplicationUserSyncService();
    $service->syncUsers($app);

    $profile = AccessProfile::where('slug', 'xyz_gamma')->get();
    expect($profile->count())->toEqual(1);

    $existingProfile->refresh();
    expect($existingProfile->roles->pluck('id')->toArray())->toContain($role->id);
});
