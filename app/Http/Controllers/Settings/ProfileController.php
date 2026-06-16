<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        $user = clone $request->user();
        
        $user->load('accessProfiles');
        
        $effectiveRoles = $request->user()->effectiveApplicationRoles()->with('application')->get();
        
        $appsMap = [];
        foreach ($effectiveRoles as $role) {
            $app = $role->application;
            if (!$app) continue;
            
            if (!isset($appsMap[$app->app_key])) {
                $appsMap[$app->app_key] = [
                    'app_key' => $app->app_key,
                    'name' => $app->name,
                    'description' => $app->description,
                    'enabled' => $app->enabled,
                    'roles' => [],
                ];
            }
            
            $appsMap[$app->app_key]['roles'][] = [
                'name' => $role->name,
                'slug' => $role->slug,
            ];
        }
        
        $userData = $user->toArray();
        $userData['applications'] = array_values($appsMap);
        $userData['access_profiles'] = $user->accessProfiles->toArray();

        return Inertia::render('settings/Profile', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
            'user' => $userData,
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return to_route('profile.edit');
    }

    /**
     * Delete the user's profile.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
