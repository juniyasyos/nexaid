<?php

namespace App\Http\Controllers;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Services\UserDataService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(
        private UserDataService $userDataService,
    ) {}

    public function index()
    {
        $user = auth()->user();

        // Fetch applications by access profile - only accessible apps
        $accessProfiles = $this->userDataService->getUserApplicationsByAccessProfile($user);

        // Flatten applications for Inertia prop (component will organize by profile)
        $applications = [];
        foreach ($accessProfiles as $profile) {
            foreach ($profile['applications'] as $app) {
                // Use app_url from service (already extracted by getPrimaryUrl)
                $applications[] = [
                    'app_key' => $app['app_key'],
                    'name' => $app['name'],
                    'description' => $app['description'] ?? '',
                    'app_url' => $app['app_url'],
                    'enabled' => $app['enabled'] ?? true,
                    'logo_url' => $app['logo_url'] ?? null,
                    'icon' => $app['icon'] ?? null,
                    'gradient' => $app['gradient'] ?? null,
                ];
            }
        }

        return Inertia::render('Dashboard/DashboardPage', [
            'applications' => $applications,
            'accessProfiles' => $accessProfiles,
        ]);
    }
}
