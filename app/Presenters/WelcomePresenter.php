<?php

namespace App\Presenters;

use App\Domain\Iam\Models\Application;

class WelcomePresenter
{
    public function presentApplication($app): array
    {
        return [
            'id' => $app->app_key,
            'name' => $app->name,
            'code' => strtoupper(substr($app->app_key, 0, 3)),
            'description' => $app->description ?? 'Aplikasi ' . $app->name,
            'status' => $this->determineAppStatus($app),
            'scope' => $this->determineAppScope($app),
            'tags' => $this->generateAppTags($app),
            'url' => $this->getAppUrl($app),
            'requiresAuth' => true,
            'logo_url' => $app->logo_url,
        ];
    }

    private function determineAppStatus($app): string
    {
        $statusMap = [
            'siimut' => 'ready',
            'tamasuma' => 'beta',
            'incident-report.app' => 'beta',
            'pharmacy.app' => 'ready',
            'client-example' => 'planned',
        ];

        return $statusMap[$app->app_key] ?? 'ready';
    }

    private function determineAppScope($app): string
    {
        $scopeMap = [
            'siimut' => 'Mutu & Manajemen RS',
            'tamasuma' => 'Manajemen Unit',
            'incident-report.app' => 'Keselamatan Pasien',
            'pharmacy.app' => 'Farmasi & Obat',
            'client-example' => 'Demo & Testing',
        ];

        return $scopeMap[$app->app_key] ?? 'Aplikasi RS';
    }

    private function generateAppTags($app): array
    {
        $tagMap = [
            'siimut' => ['indikator', 'dashboard', 'mutu'],
            'tamasuma' => ['unit-kerja', 'manajemen'],
            'incident-report.app' => ['insiden', 'keselamatan', 'audit'],
            'pharmacy.app' => ['farmasi', 'obat', 'inventory'],
            'client-example' => ['demo', 'contoh'],
        ];

        return $tagMap[$app->app_key] ?? ['aplikasi'];
    }

    private function getAppUrl($app): string
    {
        if (!empty($app->redirect_uris) && is_array($app->redirect_uris)) {
            return $app->redirect_uris[0];
        }

        return route('sso.authorize', ['app_key' => $app->app_key]);
    }

    public function getUserInitials(string $name): string
    {
        return collect(explode(' ', $name))
            ->map(fn($part) => strtoupper(substr($part, 0, 1)))
            ->take(2)
            ->join('');
    }

    public function getUserPrimaryRole($user): string
    {
        if (!$user) {
            return 'Guest';
        }

        try {
            $roles = $user->applicationRoles()
                ->with('application')
                ->get();

            if ($roles->isNotEmpty()) {
                $firstRole = $roles->first();
                return ucfirst($firstRole->slug) . ' - ' . $firstRole->application->name;
            }
        } catch (\Exception $e) {
        }

        try {
            if (method_exists($user, 'getRoleNames')) {
                $spatieRoles = $user->getRoleNames();
                if ($spatieRoles->isNotEmpty()) {
                    return ucfirst($spatieRoles->first());
                }
            }
        } catch (\Exception $e) {
        }

        return 'User';
    }
}
