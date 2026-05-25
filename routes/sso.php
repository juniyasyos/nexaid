<?php

use App\Domain\Iam\Http\Controllers\SsoTokenController;
use App\Domain\Iam\Http\Controllers\FetchClientRolesController;
use App\Http\Controllers\SSOController;
use App\Http\Controllers\UserInfoController;
use App\Http\Controllers\Sso\SsoRedirectController;
use App\Http\Controllers\Sso\SsoVerifyController;
use App\Http\Middleware\SsoLoggingMiddleware;
use Illuminate\Support\Facades\Route;

return [
    'web' => function (): void {
        Route::middleware(['auth', \App\Http\Middleware\BlockInactiveUser::class, SsoLoggingMiddleware::class])
            ->group(function (): void {
                Route::get('/sso/redirect', SsoRedirectController::class)
                    ->name('sso.redirect');
            });

        // OAuth2-like SSO Authorization Endpoint (new IAM)
        // Route::middleware('auth')->get('/oauth/authorize', [SsoTokenController::class, 'authorize'])
        //     ->name('oauth.authorize');

        Route::get('/sso/authorize', [\App\Http\Controllers\SSOController::class, 'authorize'])
            ->name('sso.authorize');

        // Front‑channel logout chain (public) — sequentially calls client `/iam/logout`
        Route::get('/sso/logout/chain', \App\Http\Controllers\Sso\SsoLogoutChainController::class)
            ->name('sso.logout.chain');
    },
    'api' => function (): void {
        Route::middleware(SsoLoggingMiddleware::class)
            ->group(function (): void {
                Route::post('/sso/verify', SsoVerifyController::class)
                    ->name('api.sso.verify');
            });

        // Token expiry notification from client apps
        Route::post('/iam/notify-token-expired', \App\Http\Controllers\Api\TokenExpiredNotificationController::class)
            ->middleware(SsoLoggingMiddleware::class)
            ->name('api.notify.token.expired');

        Route::get('/iam/client-roles', FetchClientRolesController::class)
            ->name('api.iam.client-roles');

        // Token exchange endpoints (no token required yet)
        Route::middleware([SsoLoggingMiddleware::class])
            ->group(function () {
                Route::post('/sso/token', [SsoTokenController::class, 'token'])
                    ->name('sso.token.exchange');

                Route::post('/sso/token/refresh', [SsoTokenController::class, 'refresh'])
                    ->name('sso.token.refresh');

                Route::post('/oauth/token', [SSOController::class, 'token'])
                    ->name('oauth.token');
            });

        // Protected endpoints (require SSO JWT token in Authorization header)
        Route::middleware(['sso.jwt', SsoLoggingMiddleware::class])
            ->group(function () {
                Route::post('/sso/token/issue', [SsoTokenController::class, 'issueToken'])
                    ->name('sso.token.issue');

                Route::get('/sso/userinfo', [SsoTokenController::class, 'userinfo'])
                    ->name('sso.userinfo');

                Route::get('/oauth/userinfo', [SSOController::class, 'userInfo'])
                    ->name('api.oauth.userinfo');

                Route::get('/iam/user-applications', [UserInfoController::class, 'applications'])
                    ->name('iam.user-applications');

                Route::get('/iam/user-access-profiles', [UserInfoController::class, 'accessProfiles'])
                    ->name('iam.user-access-profiles');

                // Restore users/applications endpoint for backward compatibility with client apps
                Route::get('/users/applications', [UserInfoController::class, 'applications'])
                    ->name('users.applications');

                Route::get('/users/applications/detail', [UserInfoController::class, 'applicationsDetail'])
                    ->name('users.applications.detail');
            });

        // Server-to-server endpoints (client app validates with app_secret)
        Route::middleware([SsoLoggingMiddleware::class])
            ->group(function () {
                Route::post('/sso/introspect', [SsoTokenController::class, 'introspect'])
                    ->name('sso.introspect');

                Route::post('/oauth/revoke', [SSOController::class, 'revoke'])
                    ->name('oauth.revoke');

                Route::post('/oauth/introspect', [SSOController::class, 'introspect'])
                    ->name('oauth.introspect');
            });
    },
];
