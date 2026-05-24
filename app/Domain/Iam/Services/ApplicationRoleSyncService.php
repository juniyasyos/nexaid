<?php

namespace App\Domain\Iam\Services;

use App\Domain\Iam\Models\Application;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\JWTTokenService;

class ApplicationRoleSyncService
{
    /**
        * Sync IAM roles to the client application.
     */
    public function syncRoles(Application $application): array
    {
        return $this->pushRolesToClient($application);
    }

    /**
     * Push IAM role set to client application and let client update its role table.
     */
    protected function pushRolesToClient(Application $application): array
    {
        $roles = $this->getIamRoles($application);
        $syncUrl = $this->buildPushRoleUrl($application, $application->app_key);

        Log::info('Pushing roles to client application', [
            'app_key' => $application->app_key,
            'sync_url' => $syncUrl,
            'role_count' => count($roles),
        ]);

        try {
            $payload = ['roles' => $roles];
            $jsonBody = json_encode($payload);

            if (! setting('iam.backchannel_verify', true)) {
                $response = Http::timeout(50)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->withBody($jsonBody, 'application/json')
                    ->post($syncUrl);
            } elseif (setting('iam.backchannel_method', 'jwt') === 'jwt') {
                $token = app(JWTTokenService::class)->generateBackchannelToken($application);
                $response = Http::withToken($token)
                    ->timeout(50)
                    ->withHeaders(['Content-Type' => 'application/json'])
                    ->withBody($jsonBody, 'application/json')
                    ->post($syncUrl);
            } else {
                // Prefer global SSO secret from IAM settings (from iam.php),
                // fallback to old sso.secret + env (backward compatibility),
                // then fallback to per-app secret hash.
                $secret = config('iam.sso_secret', config('sso.secret', env('SSO_SECRET', ''))) ?: $application->secret;

                // Decode base64-encoded secrets (Laravel convention: base64:xxxxx)
                if (is_string($secret) && str_starts_with($secret, 'base64:')) {
                    $decoded = base64_decode(substr($secret, 7), true);
                    $secret = $decoded !== false ? $decoded : $secret;
                }

                $signature = hash_hmac('sha256', $jsonBody, $secret);
                $header = setting('sso.backchannel.signature_header', 'IAM-Signature');

                $response = Http::withHeaders([
                    $header => $signature,
                    'X-IAM-App-Key' => $application->app_key,
                    'Content-Type' => 'application/json',
                ])
                    ->timeout(50)
                    ->withBody($jsonBody, 'application/json')
                    ->post($syncUrl);
            }

            if (! $response->successful()) {
                return [
                    'success' => false,
                    'error' => "Client returned status {$response->status()}",
                    'iam_roles' => $roles,
                    'client_roles' => [],
                ];
            }

            $clientData = $response->json();
            return array_merge(['success' => true], $clientData);
        } catch (\Exception $e) {
            Log::error('Failed to push roles to client application', [
                'app_key' => $application->app_key,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'iam_roles' => $roles,
                'client_roles' => [],
            ];
        }
    }

    /**
     * Build the client endpoint to receive pushed roles.
     */
    protected function buildPushRoleUrl(Application $application, string $appKey): string
    {
        $base = $this->getBackchannelUrl($application);

        if (! $base) {
            throw new \InvalidArgumentException('Application has no callback/backchannel URL configured for sync.');
        }

        return $base . '/api/iam/push-roles?app_key=' . urlencode($appKey);
    }

    /**
     * Get all roles defined in IAM for this application.
     */
    protected function getIamRoles(Application $application): array
    {
        return $application->roles()
            ->get()
            ->map(fn($role) => [
                'id' => $role->id,
                'slug' => $role->slug,
                'name' => $role->name,
                'description' => $role->description,
                'is_system' => $role->is_system,
            ])
            ->toArray();
    }

    /**
     * Determine the base URL for back-channel operations (sync users/roles).
     */
    protected function getBackchannelUrl(Application $application): ?string
    {
        // Prefer explicit backchannel URL if configured.
        $backchannel = $application->backchannel_url ?: $application->callback_url;

        if (! $backchannel) {
            return null;
        }

        $parsed = parse_url($backchannel);

        if (empty($parsed['scheme']) || empty($parsed['host'])) {
            return null;
        }

        $base = $parsed['scheme'] . '://' . $parsed['host'];

        if (! empty($parsed['port'])) {
            $base .= ':' . $parsed['port'];
        }

        return rtrim($base, '/');
    }

    /**
     * Build sync URL from back-channel URL.
     */
    protected function buildSyncUrl(Application $application, string $appKey): string
    {
        $base = $this->getBackchannelUrl($application);

        if (! $base) {
            throw new \InvalidArgumentException('Application has no callback/backchannel URL configured for sync.');
        }

        return $base . '/api/iam/sync-roles?app_key=' . urlencode($appKey);
    }
}
