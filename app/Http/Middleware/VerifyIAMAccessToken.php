<?php

namespace App\Http\Middleware;

use App\Services\JWTTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk memverifikasi IAM Access Token dari aplikasi klien.
 * Middleware ini harus digunakan di aplikasi klien (bukan di IAM).
 */
class VerifyIAMAccessToken
{
    public function __construct(
        private JWTTokenService $jwtService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Access token is required',
            ], 401);
        }

        try {
            $decoded = $this->jwtService->verifyToken($token);

            // Validasi token type
            if (! isset($decoded->type) || $decoded->type !== 'access') {
                return response()->json([
                    'error' => 'unauthorized',
                    'message' => 'Invalid token type',
                ], 401);
            }

            // Validasi app_key (opsional, set di config)
            $expectedAppKey = config('iam.app_key');
            if ($expectedAppKey && (! isset($decoded->app_key) || $decoded->app_key !== $expectedAppKey)) {
                return response()->json([
                    'error' => 'unauthorized',
                    'message' => 'Token not valid for this application',
                ], 401);
            }

            // Inject user context ke request
            $request->merge([
                'iam_user_id' => $decoded->sub,
                'iam_user_email' => $decoded->email ?? null,
                'iam_user_name' => $decoded->name ?? null,
                'iam_user_roles' => $decoded->roles ?? [],
                'iam_user_roles' => $decoded->roles ?? [],
                'iam_user_unit' => $decoded->unit ?? null,
            ]);

            // Set attributes untuk akses mudah
            $request->attributes->set('iam_token', $decoded);

            return $next($request);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'unauthorized',
                'message' => 'Invalid or expired token',
            ], 401);
        }
    }
}
