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

        // Flatten applications for Inertia prop, dedup by app_key so that the
        // same application from multiple access profiles is only listed once.
        $applicationsByKey = [];
        foreach ($accessProfiles as $profile) {
            foreach ($profile['applications'] as $app) {
                $key = $app['app_key'];
                if (isset($applicationsByKey[$key])) {
                    continue; // already added from another profile
                }
                // Use app_url from service (already extracted by getPrimaryUrl)
                $applicationsByKey[$key] = [
                    'app_key'     => $app['app_key'],
                    'name'        => $app['name'],
                    'description' => $app['description'] ?? '',
                    'app_url'     => $app['app_url'],
                    'enabled'     => $app['enabled'] ?? true,
                    'logo_url'    => $app['logo_url'] ?? null,
                    'icon'        => $app['icon'] ?? null,
                    'gradient'    => $app['gradient'] ?? null,
                ];
            }
        }
        $applications = array_values($applicationsByKey);

        return Inertia::render('Dashboard/DashboardPage', [
            'applications' => $applications,
            'accessProfiles' => $accessProfiles,
        ]);
    }
}
