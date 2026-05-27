<?php

namespace App\Services\Auth;

use App\Models\Session;
use App\Services\Sso\SsoLogger;
use App\Services\JWTTokenService;
use App\Jobs\RevokeUserRefreshTokensJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class SessionService
{
    public function __construct(
        private readonly SsoLogger $logger,
        private readonly JWTTokenService $jwtService
    ) {}

    public function handleLogin(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $trackingId = $this->logger->startPerformanceTracking('login_process');
        $nip = $request->string('nip')->toString();

        $this->logger->logLoginAttempt(
            email: $nip,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent() ?? 'Unknown',
            additionalContext: [
                'has_sso_context' => $request->session()->has('sso.intended_app'),
                'intended_app' => $request->session()->get('sso.intended_app'),
                'remember_me' => $request->boolean('remember'),
                'login_method' => 'nip',
            ]
        );

        try {
            $user = $request->validateCredentials();

            if (\Laravel\Fortify\Features::enabled(\Laravel\Fortify\Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
                $request->session()->put([
                    'login.id' => $user->getKey(),
                    'login.remember' => $request->boolean('remember'),
                ]);

                $this->logger->logAuthFlow('two_factor_redirect', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'ip_address' => $request->ip(),
                ]);

                $this->logger->endPerformanceTracking($trackingId, [
                    'login_successful' => true,
                    'requires_2fa' => true,
                    'user_id' => $user->id,
                ]);

                return to_route('two-factor.login');
            }

            if ($user->status !== 'active') {
                $reason = $user->status === 'suspended'
                    ? 'Akun Anda telah ditangguhkan oleh administrator.'
                    : 'Akun Anda sedang dinonaktifkan oleh administrator.';

                return redirect()->route('account.status')
                    ->with('inactive_reason', $reason);
            }

            Log::warning('auth.login_store_called', [
                'user_id' => $user->id,
                'nip' => $user->nip,
                'email' => $user->email,
            ]);

            Auth::login($user, $request->boolean('remember'));
            $request->session()->regenerate();

            session()->put('user_status', $user->status);
            session()->put('user_id', $user->id);

            if ($request->hasSession() && $request->session()->getId()) {
                $request->session()->save();

                $sessionModel = Session::find($request->session()->getId());

                if (! $sessionModel) {
                    Log::warning('auth.login_session_model_not_found', [
                        'session_id' => $request->session()->getId(),
                        'user_id' => $user->id,
                        'nip' => $user->nip,
                    ]);
                } else {
                    $sessionModel->user_id = $user->id;
                    $sessionModel->is_active = true;
                    $sessionModel->save();
                }
            }

            $user->recordLastLogin();

            $this->logger->logLoginSuccess(
                userId: $user->id,
                email: $user->email,
                ipAddress: $request->ip(),
                additionalContext: [
                    'remember_me' => $request->boolean('remember'),
                    'has_sso_context' => $request->session()->has('sso.intended_app'),
                ]
            );

            $intended = $request->session()->pull('url.intended', route('home', absolute: true));

            if ($request->session()->has('sso.intended_app')) {
                $appKey = (string) $request->session()->pull('sso.intended_app');

                $this->logger->logAuthFlow('sso_login_completed', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'app_key' => $appKey,
                    'redirect_url' => route('sso.redirect', ['app' => $appKey], absolute: true),
                    'ip_address' => $request->ip(),
                ]);

                $this->logger->endPerformanceTracking($trackingId, [
                    'login_successful' => true,
                    'sso_flow' => true,
                    'app_key' => $appKey,
                    'user_id' => $user->id,
                ]);

                return Inertia::location(
                    route('sso.redirect', ['app' => $appKey], absolute: true)
                );
            }

            $this->logger->endPerformanceTracking($trackingId, [
                'login_successful' => true,
                'sso_flow' => false,
                'user_id' => $user->id,
            ]);

            return Inertia::location($intended);
        } catch (\Throwable $exception) {
            $this->logger->logLoginFailed(
                email: $nip,
                reason: $exception->getMessage(),
                ipAddress: $request->ip(),
                additionalContext: [
                    'exception_class' => get_class($exception),
                    'has_sso_context' => $request->session()->has('sso.intended_app'),
                    'intended_app' => $request->session()->get('sso.intended_app'),
                    'login_method' => 'nip',
                ]
            );

            $this->logger->endPerformanceTracking($trackingId, [
                'login_successful' => false,
                'error' => $exception->getMessage(),
                'nip' => $nip,
            ]);

            throw $exception;
        }
    }

    public function handleLogout(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            Log::warning('auth.logout_called', [
                'user_id' => $user->id,
                'nip' => $user->nip,
                'email' => $user->email,
                'session_id' => $request->session()->getId(),
            ]);

            // Revoke refresh tokens asynchronously to avoid blocking logout request
            RevokeUserRefreshTokensJob::dispatch($user->id);

            if ($request->hasSession() && $request->session()->getId()) {
                $sessionModel = Session::find($request->session()->getId());

                if ($sessionModel) {
                    $sessionModel->is_active = false;
                    $sessionModel->save();
                }
            }

            Cache::put("user_logout_at:{$user->id}", time());
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
