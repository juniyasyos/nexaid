<?php

namespace App\Domain\Iam\Http\Controllers;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Services\ApplicationRoleSyncService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FetchClientRolesController extends Controller
{
    public function __construct(
        private readonly ApplicationRoleSyncService $roleSyncService
    ) {}

    /**
     * Fetch the role list from a client application.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'app_key' => ['required', 'string', 'exists:applications,app_key'],
        ]);

        $application = Application::findByKey($data['app_key']);

        if (! $application->enabled) {
            return response()->json([
                'success' => false,
                'error' => 'Application is disabled.',
                'app_key' => $application->app_key,
                'roles' => [],
                'total' => 0,
            ], 422);
        }

        $result = $this->roleSyncService->fetchClientRoles($application);

        if (! $result['success']) {
            return response()->json($result, 502);
        }

        return response()->json([
            'success' => true,
            'app_key' => $result['app_key'],
            'roles' => $result['roles'],
            'total' => $result['total'],
            'source_url' => $result['source_url'] ?? null,
        ]);
    }
}