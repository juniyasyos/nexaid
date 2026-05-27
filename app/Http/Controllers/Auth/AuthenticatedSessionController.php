<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\Session;
use App\Services\Sso\SsoLogger;
use App\Jobs\RevokeUserRefreshTokensJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly SsoLogger $logger) {}

    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        if ($request->filled('app')) {
            $appKey = $request->string('app')->toString();

            $request->session()->put('sso.intended_app', $appKey);
            $request->session()->put(
                'url.intended',
                route('sso.redirect', ['app' => $appKey], absolute: true)
            );

            // Log SSO login page access
            $this->logger->logWithRequest($request, SsoLogger::CATEGORY_AUTH_FLOW, 'sso_login_page_accessed', [
                'app_key' => $appKey,
                'intended_url' => route('sso.redirect', ['app' => $appKey], absolute: true),
            ]);
        }

        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'devAutofill' => app()->environment('local') ? [
                'nip' => '0000.00000',
                'password' => 'adminpassword',
            ] : null,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): \Symfony\Component\HttpFoundation\Response
    {
        $trackingId = $this->logger->startPerformanceTracking('login_process');
        $nip = $request->string('nip')->toString();

        // Log login attempt
        $this->logger->logLoginAttempt(
            email: $nip, // Using NIP as identifier
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

            if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
                $request->session()->put([
                    'login.id' => $user->getKey(),
                    'login.remember' => $request->boolean('remember'),
                ]);

                // Log 2FA redirect
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
                    Log::debug('auth.login_session_model_found', [
                        'session_id' => $sessionModel->id,
                        'user_id' => $user->id,
                        'user_id_before' => $sessionModel->getOriginal('user_id'),
                        'is_active_before' => $sessionModel->getOriginal('is_active'),
                    ]);

                    $sessionModel->user_id = $user->id;
                    $sessionModel->is_active = true;
                    $sessionModel->save();

                    Log::warning('auth.login_session_model_saved', [
                        'session_id' => $sessionModel->id,
                        'user_id' => $user->id,
                        'user_id_after' => $sessionModel->user_id,
                        'is_active_after' => $sessionModel->is_active,
                    ]);
                }
            }

            Log::warning('auth.record_last_login_now', [
                'user_id' => $user->id,
                'nip' => $user->nip,
                'email' => $user->email,
            ]);

            $user->recordLastLogin();

            // Log successful login
            $this->logger->logLoginSuccess(
                userId: $user->id,
                email: $user->email,
                ipAddress: $request->ip(),
                additionalContext: [
                    'remember_me' => $request->boolean('remember'),
                    'has_sso_context' => $request->session()->has('sso.intended_app'),
                ]
            );

            // Default redirect to home page (root) instead of panel
            $intended = $request->session()->pull('url.intended', route('home', absolute: true));

            if ($request->session()->has('sso.intended_app')) {
                $appKey = (string) $request->session()->pull('sso.intended_app');

                // Log SSO flow completion
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
            // Log login failure
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

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            Log::warning('auth.logout_called', [
                'user_id' => $user->id,
                'nip' => $user->nip,
                'email' => $user->email,
                'session_id' => $request->session()->getId(),
            ]);

            // Log logout
            // $this->logger->logAuthFlow('logout', [
            //     'user_id' => $user->id,
            //     'email' => $user->email,
            //     'ip_address' => $request->ip(),
            //     'session_id' => $request->session()->getId(),
            // ]);

            // Revoke refresh tokens (best-effort) and mark logout time so
            // previously issued access tokens are treated as invalid.
            // Dispatch revocation to background job to avoid blocking logout
            RevokeUserRefreshTokensJob::dispatch($user->id);

            if ($request->hasSession() && $request->session()->getId()) {
                $sessionModel = Session::find($request->session()->getId());

                if (! $sessionModel) {
                    Log::warning('auth.logout_session_model_not_found', [
                        'session_id' => $request->session()->getId(),
                        'user_id' => $user->id,
                        'nip' => $user->nip,
                    ]);
                } else {
                    Log::debug('auth.logout_session_model_found', [
                        'session_id' => $sessionModel->id,
                        'user_id' => $user->id,
                        'is_active_before' => $sessionModel->getOriginal('is_active'),
                    ]);

                    $sessionModel->is_active = false;
                    $sessionModel->save();

                    Log::warning('auth.logout_session_model_saved', [
                        'session_id' => $sessionModel->id,
                        'user_id' => $user->id,
                        'is_active_after' => $sessionModel->is_active,
                    ]);
                }
            }

            Cache::put("user_logout_at:{$user->id}", time());
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Backchannel logout is disabled; rely on token invalidation and remote verification instead.
        return redirect('/login');
    }
}
