<?php

namespace App\Domain\Iam\Http\Controllers;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Services\TokenBuilder;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SsoTokenController extends Controller
{
    public function __construct(
        private readonly TokenBuilder $tokenBuilder
    ) {}

    /**
     * Issue an access token for an authenticated user.
     *
     * This endpoint expects the user to be authenticated (via Bearer token or session)
     * and optionally validates the requesting application.
     */
    public function issueToken(Request $request): JsonResponse
    {
        $request->validate([
            'app_key' => 'nullable|string|exists:applications,app_key',
        ]);

        // Get user from middleware authentication or session
        $user = $request->user() ?? Auth::user();

        if (! $user) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'User not authenticated.',
            ], 401);
        }

        // Require at least one active access profile for the user.
        if (! $user->hasActiveAccessProfiles()) {
            return response()->json([
                'error' => 'access_denied',
                'message' => 'User requires at least one active access profile to request a token.',
            ], 403);
        }

        // Validate application if app_key provided
        if ($request->has('app_key')) {
            $application = Application::findByKey($request->app_key);

            if (! $application->enabled) {
                return response()->json([
                    'error' => 'invalid_application',
                    'message' => 'Application is not enabled.',
                ], 400);
            }

            // Ensure user has a profile that grants roles for this app.
            if (! $user->hasActiveAccessProfileForApp($application)) {
                return response()->json([
                    'error' => 'access_denied',
                    'message' => 'User does not have an active access profile with roles for this application.',
                ], 403);
            }
        }

        try {
            // Build and encode token
            $accessToken = $this->tokenBuilder->buildTokenForUser($user);
            $claims = $this->tokenBuilder->decode($accessToken);

            return response()->json([
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
                'expires_in' => $claims->getTimeUntilExpiry(),
                'user' => [
                    'id' => $user->id,
                    'nip' => $user->nip,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'apps' => $claims->apps,
                'roles_by_app' => $claims->rolesByApp,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'token_generation_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * OAuth2-like authorization endpoint.
     * Generates an authorization code that can be exchanged for a token.
     */
    public function authorize(Request $request): JsonResponse
    {
        $request->validate([
            'client_id' => 'required|string|exists:applications,app_key',
            'redirect_uri' => 'required|url',
            'response_type' => 'required|in:code',
            'state' => 'nullable|string',
        ]);

        /** @var User $user */
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'User not authenticated.',
            ], 401);
        }

        try {
            $application = Application::findByKey($request->client_id);

            if (! $application->enabled) {
                return response()->json([
                    'error' => 'invalid_client',
                    'message' => 'Application is not enabled.',
                ], 400);
            }

            if (! $application->isValidRedirectUri($request->redirect_uri)) {
                return response()->json([
                    'error' => 'invalid_redirect_uri',
                    'message' => 'Redirect URI not registered for this application.',
                ], 400);
            }

            // Generate authorization code
            $code = Str::random(64);
            $cacheKey = "auth_code:{$code}";

            Cache::put($cacheKey, [
                'user_id' => $user->id,
                'client_id' => $application->app_key,
                'redirect_uri' => $request->redirect_uri,
            ], config('iam.auth_code_ttl', 300));

            // Build redirect URL
            $redirectUrl = $request->redirect_uri . '?code=' . $code;
            if ($request->has('state')) {
                $redirectUrl .= '&state=' . $request->state;
            }

            return response()->json([
                'redirect_url' => $redirectUrl,
                'code' => $code,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'authorization_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * OAuth2-like token endpoint.
     * Exchange authorization code for access token.
     */
    public function token(Request $request): JsonResponse
    {
        $request->validate([
            'grant_type' => 'required|in:authorization_code,refresh_token',
            'client_id' => 'required|string|exists:applications,app_key',
            'client_secret' => 'required|string',
        ]);

        try {
            $application = Application::findByKey($request->client_id);

            if (! $application->enabled) {
                return response()->json([
                    'error' => 'invalid_client',
                    'message' => 'Application is not enabled.',
                ], 400);
            }

            if (! $application->verifySecret($request->client_secret)) {
                return response()->json([
                    'error' => 'invalid_client',
                    'message' => 'Invalid client credentials.',
                ], 401);
            }

            if ($request->grant_type === 'authorization_code') {
                return $this->handleAuthorizationCodeGrant($request, $application);
            }

            if ($request->grant_type === 'refresh_token') {
                return $this->handleRefreshTokenGrant($request);
            }

            return response()->json([
                'error' => 'unsupported_grant_type',
                'message' => 'Grant type not supported.',
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'token_request_failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Introspect a token to get its claims and validity.
     */
    public function introspect(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        try {
            $claims = $this->tokenBuilder->verify($request->token);

            return response()->json([
                'active' => true,
                'sub' => $claims->userId,
                'nip' => $claims->nip ?? null,
                'email' => $claims->email,
                'name' => $claims->name,
                'apps' => $claims->apps,
                'roles_by_app' => $claims->rolesByApp,
                'iss' => $claims->issuer,
                'iat' => $claims->issuedAt,
                'exp' => $claims->expiresAt,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'active' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get user information from token.
     * The user is authenticated by VerifySsoJwtApi middleware.
     */
    public function userinfo(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'invalid_request',
                'message' => 'User not authenticated.',
            ], 401);
        }

        // Get token payload from middleware
        $ssoPayload = $request->attributes->get('sso_payload');

        // Build and return user claims
        $claims = $this->tokenBuilder->buildClaimsForUser($user);

        return response()->json([
            'sub' => $claims->userId,
            'nip' => $claims->nip ?? null,
            'email' => $claims->email,
            'name' => $claims->name,
            'apps' => $claims->apps,
            'roles_by_app' => $claims->rolesByApp,
            'iss' => $claims->issuer,
            'iat' => $ssoPayload['iat'] ?? $claims->issuedAt,
            'exp' => $ssoPayload['exp'] ?? $claims->expiresAt,
        ]);
    }

    /**
     * Refresh an access token.
     */
    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            'token' => 'required|string',
        ]);

        try {
            $newToken = $this->tokenBuilder->refresh($request->token);
            $claims = $this->tokenBuilder->decode($newToken);

            return response()->json([
                'access_token' => $newToken,
                'token_type' => 'Bearer',
                'expires_in' => $claims->getTimeUntilExpiry(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'refresh_failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Handle authorization code grant.
     */
    private function handleAuthorizationCodeGrant(Request $request, Application $application): JsonResponse
    {
        $request->validate([
            'code' => 'required|string',
            'redirect_uri' => 'required|url',
        ]);

        $cacheKey = "auth_code:{$request->code}";
        $authData = Cache::get($cacheKey);

        if (! $authData) {
            return response()->json([
                'error' => 'invalid_grant',
                'message' => 'Authorization code is invalid or expired.',
            ], 400);
        }

        if ($authData['client_id'] !== $application->app_key) {
            return response()->json([
                'error' => 'invalid_grant',
                'message' => 'Authorization code was issued to a different client.',
            ], 400);
        }

        if ($authData['redirect_uri'] !== $request->redirect_uri) {
            return response()->json([
                'error' => 'invalid_grant',
                'message' => 'Redirect URI does not match.',
            ], 400);
        }

        // Delete authorization code (one-time use)
        Cache::forget($cacheKey);

        // Issue token
        $user = User::findOrFail($authData['user_id']);
        $accessToken = $this->tokenBuilder->buildTokenForUser($user);
        $claims = $this->tokenBuilder->decode($accessToken);

        return response()->json([
            'access_token' => $accessToken,
            'token_type' => 'Bearer',
            'expires_in' => $claims->getTimeUntilExpiry(),
        ]);
    }

    /**
     * Handle refresh token grant.
     */
    private function handleRefreshTokenGrant(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => 'required|string',
        ]);

        try {
            $newToken = $this->tokenBuilder->refresh($request->refresh_token);
            $claims = $this->tokenBuilder->decode($newToken);

            return response()->json([
                'access_token' => $newToken,
                'token_type' => 'Bearer',
                'expires_in' => $claims->getTimeUntilExpiry(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'invalid_grant',
                'message' => 'Refresh token is invalid or expired.',
            ], 400);
        }
    }
}
