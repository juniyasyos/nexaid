<?php

namespace App\Http\Controllers\Sso;

use App\Http\Controllers\Controller;
use App\Domain\Iam\Services\TokenBuilder;
use App\Domain\Iam\Services\UserRoleAssignmentService;
use App\Services\Sso\SsoLogger;
use Illuminate\Http\RedirectResponse;
use App\Domain\Iam\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SsoRedirectController extends Controller
{
    public function __construct(
        private readonly TokenBuilder $tokenBuilder,
        private readonly UserRoleAssignmentService $roleService,
        private readonly SsoLogger $logger
    ) {}

    public function __invoke(Request $request): RedirectResponse
    {
        $trackingId = $this->logger->startPerformanceTracking('sso_redirect');

        $validated = $request->validate([
            'app' => ['required', 'string'],
        ]);

        $this->logger->logWithRequest($request, SsoLogger::CATEGORY_AUTH_FLOW, 'sso_redirect_request', [
            'app_key' => $validated['app'],
            'user_id' => $request->user()?->id,
            'user_nip' => $request->user()?->nip,
        ]);

        try {
            $application = Application::enabled()
                ->where('app_key', $validated['app'])
                ->first();

            if ($application === null) {
                $this->logger->logSecurity('application_not_found', [
                    'app_key' => $validated['app'],
                    'user_id' => $request->user()?->id,
                    'ip_address' => $request->ip(),
                ]);

                Log::error('[IAM] SSO: Application not found or disabled', [
                    'app' => $validated['app'],
                ]);

                throw ValidationException::withMessages([
                    'app' => 'Application is not registered or disabled.',
                ]);
            }

            if (empty($application->callback_url)) {
                $this->logger->logSecurity('callback_url_not_configured', [
                    'app_key' => $validated['app'],
                    'application_id' => $application->id,
                    'user_id' => $request->user()?->id,
                ]);

                Log::error('[IAM] SSO: Application callback URL not configured', [
                    'app' => $validated['app'],
                ]);

                throw ValidationException::withMessages([
                    'app' => 'Application callback URL is not configured.',
                ]);
            }

            $this->logger->logSsoRedirect(
                userId: $request->user()->id,
                appKey: $application->app_key,
                callbackUrl: $application->callback_url,
                additionalContext: [
                    'application_id' => $application->id,
                    'application_name' => $application->name ?? 'N/A',
                    'user_nip' => $request->user()->nip,
                ]
            );

            Log::info('[IAM] SSO: User authenticated', [
                'user_id' => $request->user()->id,
                'app' => $application->app_key,
                'callback_url' => $application->callback_url,
            ]);

            // Build token using TokenBuilder (same as refresh) for signature consistency
            // Add app_key to extra field to preserve it across token lifecycle
            $extra = ['app' => $application->app_key];
            $token = $this->tokenBuilder->buildTokenForUser($request->user(), $extra);

            Log::info('[IAM] SSO: Token generated', [
                'token_preview' => substr($token, 0, 20) . '...',
                'app' => $application->app_key,
            ]);

            $separator = str_contains($application->callback_url, '?') ? '&' : '?';
            $redirectUrl = $application->callback_url . $separator . http_build_query([
                'token' => $token,
            ]);

            $this->logger->logAuthFlow('sso_callback_redirect', [
                'redirect_url' => $redirectUrl,
                'has_token' => !empty($token),
                'token_length' => strlen($token),
                'app_key' => $application->app_key,
                'user_id' => $request->user()->id,
            ]);

            Log::info('[IAM] SSO: Redirecting to callback', [
                'url' => $redirectUrl,
                'has_token' => !empty($token),
            ]);

            $this->logger->endPerformanceTracking($trackingId, [
                'app_key' => $application->app_key,
                'user_id' => $request->user()->id,
                'redirect_successful' => true,
                'callback_url' => $application->callback_url,
            ]);

            return redirect()->away($redirectUrl);
        } catch (\Throwable $exception) {
            $this->logger->logException($exception, SsoLogger::CATEGORY_AUTH_FLOW, [
                'operation' => 'sso_redirect',
                'app_key' => $validated['app'],
                'user_id' => $request->user()?->id,
                'request_url' => $request->fullUrl(),
            ]);

            $this->logger->endPerformanceTracking($trackingId, [
                'operation_failed' => true,
                'error' => $exception->getMessage(),
                'app_key' => $validated['app'],
            ]);

            // If we have a callback URL configured, redirect back with error details.
            if (! empty($application->callback_url ?? null)) {
                $separator = str_contains($application->callback_url, '?') ? '&' : '?';
                $redirectUrl = $application->callback_url . $separator . http_build_query([
                    'error' => 'access_denied',
                    'error_description' => 'An error occurred during the authentication process.',
                    'error_type' => class_basename($exception),
                ]);

                Log::warning('[IAM] SSO: Redirecting to callback with error', [
                    'redirect_url' => $redirectUrl,
                    'app' => $validated['app'],
                    'user_id' => $request->user()?->id,
                ]);

                return redirect()->away($redirectUrl);
            }

            // Fallback to default exception behavior if no callback URL is available.
            throw $exception;
        }
    }
}
