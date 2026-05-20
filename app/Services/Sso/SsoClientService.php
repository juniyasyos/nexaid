<?php

namespace App\Services\Sso;

use App\Domain\Iam\Models\Application;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class SsoClientService
{
    private const AUTH_CODE_TTL_SECONDS = 300;

    public function findApplication(string $appKey): Application
    {
        return Application::findByKey($appKey);
    }

    public function verifySecret(Application $application, string $secret): bool
    {
        return $application->verifySecret($secret);
    }

    public function issueAuthorizationCode(User $user, Application $application, string $redirectUri, string $sessionId): string
    {
        $authCode = Str::random(64);

        Cache::put("auth_code:{$authCode}", [
            'user_id' => $user->id,
            'app_key' => $application->app_key,
            'redirect_uri' => $redirectUri,
            'session_id' => $sessionId,
        ], self::AUTH_CODE_TTL_SECONDS);

        return $authCode;
    }

    public function consumeAuthorizationCode(string $code): ?array
    {
        $codeData = Cache::get("auth_code:{$code}");

        if (! is_array($codeData)) {
            return null;
        }

        Cache::forget("auth_code:{$code}");

        return $codeData;
    }
}