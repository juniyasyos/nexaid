<?php

namespace App\Http\Controllers\Sso;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Services\TokenBuilder;
use App\Domain\Iam\Services\UserDataService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Sso\SsoLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class SsoVerifyController extends Controller
{
    public function __construct(
        private readonly TokenBuilder $tokenBuilder,
        private readonly SsoLogger $logger,
        private readonly UserDataService $userDataService
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $trackingId = $this->logger->startPerformanceTracking('sso_verify');

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'include_user_data' => ['boolean'],
        ]);

        $tokenPreview = substr($validated['token'], 0, 20) . '...';
        $includeUserData = $validated['include_user_data'] ?? false;

        $this->logger->logWithRequest($request, SsoLogger::CATEGORY_TOKEN_MGMT, 'verify_request', [
            'token_preview' => $tokenPreview,
            'token_length' => strlen($validated['token']),
            'include_user_data' => $includeUserData,
        ]);

        Log::info('[IAM] SSO: Verify request received', [
            'token_preview' => $tokenPreview,
            'token_length' => strlen($validated['token']),
        ]);

        try {
            // Use TokenBuilder which uses Firebase\JWT - consistent with token issuance
            $claims = $this->tokenBuilder->verify($validated['token']);

            if ($claims->type !== 'access') {
                throw new \Exception('Invalid token type. Expected access token.');
            }

            $payload = $claims->toPayload();

            Log::info('[IAM] SSO: Token verified successfully', [
                'user_id' => $payload['sub'] ?? null,
                'app' => $payload['app'] ?? null,
                'expires_at' => isset($payload['exp']) ? Carbon::createFromTimestamp($payload['exp'])->toIso8601String() : null,
            ]);

            $response = [
                // Backwards compatible: some clients expect `nip` at the root level
                'nip' => $payload['nip'] ?? null,
                'email' => $payload['email'] ?? null,
                'name' => $payload['name'] ?? null,

                // Root token claims for client mapping
                'sub' => $payload['sub'] ?? null,
                'app' => $payload['app'] ?? null,
                'roles' => $payload['roles_by_app'][$payload['app']] ?? [],
                'perms' => $payload['perms'] ?? [],

                // Token info
                'token_info' => [
                    'sub' => $payload['sub'] ?? null,
                    'app' => $payload['app'] ?? null,
                    'issuer' => $payload['iss'] ?? null,
                    'issued_at' => isset($payload['iat']) ? Carbon::createFromTimestamp($payload['iat'])->toIso8601String() : null,
                    'expires_at' => isset($payload['exp']) ? Carbon::createFromTimestamp($payload['exp'])->toIso8601String() : null,
                ],

                // Roles from token (explicit field remains good compatibility)
                'roles' => $payload['roles_by_app'][$payload['app']] ?? [],
            ];

            // Include comprehensive user data if requested
            if ($includeUserData && isset($payload['sub'])) {
                $user = User::find($payload['sub']);
                if ($user) {
                    $application = Application::where('app_key', $payload['app'])->first();
                    $response['user'] = $this->userDataService->getUserData($user, $application, true);
                }
            }

            $this->logger->endPerformanceTracking($trackingId, [
                'verification_successful' => true,
                'user_id' => $payload['sub'] ?? null,
                'app_key' => $payload['app'] ?? null,
                'token_length' => strlen($validated['token']),
                'included_user_data' => $includeUserData,
            ]);

            return response()->json($response);
        } catch (\Throwable $exception) {
            $this->logger->logException($exception, SsoLogger::CATEGORY_TOKEN_MGMT, [
                'operation' => 'sso_verify',
                'token_preview' => $tokenPreview,
                'token_length' => strlen($validated['token']),
                'request_ip' => $request->ip(),
            ]);

            Log::error('[IAM] SSO: Token verification failed', [
                'error' => $exception->getMessage(),
                'token_preview' => $tokenPreview,
            ]);

            $this->logger->endPerformanceTracking($trackingId, [
                'operation_failed' => true,
                'error' => $exception->getMessage(),
                'token_preview' => $tokenPreview,
            ]);

            return response()->json([
                'message' => 'Invalid or expired token.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }
    }
}
