<?php

use App\Domain\Iam\Models\AccessProfile;
use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\ApplicationRole;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use App\Filament\Panel\Resources\AccessProfiles\Pages\EditAccessProfile;
use Livewire\Livewire;

it('clears user relationship caches when access profile roles are synced via edit page', function () {
    $user = User::factory()->create();
    $app = Application::create(['app_key' => 'rbv-services', 'name' => 'RBV', 'secret' => 'secret123']);
    $role = ApplicationRole::create(['application_id' => $app->id, 'name' => 'Super Admin', 'slug' => 'super_admin']);
    
    $profile = AccessProfile::create([
        'name' => 'Test Profile',
        'slug' => 'test_profile',
    ]);
    
    $profile->users()->attach($user->id);
    
    // Warm up cache
    $rolesBefore = $user->rolesByApp();
    expect($rolesBefore)->toBeEmpty();
    
    Livewire::test(EditAccessProfile::class, [
        'record' => $profile->getRouteKey(),
    ])
    ->fillForm([
        'name' => 'Super Admin Bundle',
        'is_system' => true,
        'is_active' => true,
        'app_roles' => [
            $app->id => $role->id,
        ],
    ])
    ->call('save')
    ->assertHasNoFormErrors();
    
    // Check if cache is cleared and roles are updated
    expect(Cache::has("user.roles_by_app.{$user->id}"))->toBeFalse();
    
    $rolesAfter = $user->rolesByApp();
    expect($rolesAfter)->toHaveKey('rbv-services');
    expect($rolesAfter['rbv-services'])->toContain('super_admin');
});
