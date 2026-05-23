<?php

namespace App\Domain\Iam\Services;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\ApplicationRole;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\JWTTokenService;

class ApplicationRoleSyncService
{
    /**
     * Sync roles from client application - fetch and save to database.
     */
    public function syncRoles(Application $application): array
    {
        $mode = setting('iam.role_sync_mode', 'pull');

        if ($mode === 'push') {
            return $this->pushRolesToClient($application);
        }

        $result = $this->fetchClientRoles($application);

        if (! $result['success']) {
            return $result;
        }

        $clientRoles = $result['client_roles'];
        $comparison = $result['comparison'];

        // Create roles that exist in client but not in IAM
        $created = 0;
        foreach ($comparison['extra_in_client'] as $clientRole) {
            ApplicationRole::create([
                'application_id' => $application->id,
                'slug' => $clientRole['slug'],
                'name' => $clientRole['name'],
                'description' => $clientRole['description'],
                'is_system' => $clientRole['is_system'] ?? false,
            ]);
            $created++;
        }

        // Update existing roles with new data from client
        $updated = 0;
        foreach ($comparison['in_sync'] as $clientRole) {
            $iamRole = ApplicationRole::where('application_id', $application->id)
                ->where('slug', $clientRole['slug'])
                ->first();

            if ($iamRole) {
                $iamRole->update([
                    'name' => $clientRole['name'],
                    'description' => $clientRole['description'],
                    'is_system' => $clientRole['is_system'] ?? false,
                ]);
                $updated++;
            }
        }

        // Note: We don't delete roles that exist in IAM but not in client
        // to avoid accidentally removing roles that might be needed

        return [
            'success' => true,
            'message' => "Sync completed: {$created} roles created, {$updated} roles updated",
            'created' => $created,
            'updated' => $updated,
            'iam_roles' => $this->getIamRoles($application),
            'client_roles' => $clientRoles,
            'comparison' => $this->compareRoles($application, $clientRoles),
        ];
    }

    /**
     * Fetch roles from a client application via its sync endpoint.
     */
    public function fetchClientRoles(Application $application): array
    {
        try {
            $callbackUrl = $application->callback_url;

            if (!$callbackUrl) {
                return [
                    'success' => false,
                    'error' => 'Application has no callback URL configured',
                    'iam_roles' => [],
                    'client_roles' => [],
                ];
            }

            // Build the sync endpoint from application backchannel/callback URL.
            // Backchannel URL is preferred when set and may point to internal service hostname.
            $syncUrl = $this->buildSyncUrl($application, $application->app_key);

            Log::info('Fetching roles from client application', [
                'app_key' => $application->app_key,
                'sync_url' => $syncUrl,
            ]);

            // if verification is disabled we don't send any authentication
            if (! setting('iam.backchannel_verify', true)) {
                $response = Http::timeout(10)->get($syncUrl);
            } elseif (setting('iam.backchannel_method', 'jwt') === 'jwt') {
                $token = app(JWTTokenService::class)->generateBackchannelToken($application);
                $response = Http::withToken($token)
                    ->timeout(50)
                    ->get($syncUrl);
            } else {
                $secret = config('iam.sso_secret', config('sso.secret', env('SSO_SECRET', '')));

                // Decode base64-encoded secrets (Laravel convention: base64:xxxxx)
                if (is_string($secret) && str_starts_with($secret, 'base64:')) {
                    $decoded = base64_decode(substr($secret, 7), true);
                    $secret = $decoded !== false ? $decoded : $secret;
                }

                $signature = hash_hmac('sha256', '', $secret);
                $header = setting('sso.backchannel.signature_header', 'IAM-Signature');

                $response = Http::withHeaders([$header => $signature])
                    ->timeout(50)
                    ->get($syncUrl);
            }

            if (!$response->successful()) {
                return [
                    'success' => false,
                    'error' => "Client returned status {$response->status()}",
                    'iam_roles' => $this->getIamRoles($application),
                    'client_roles' => [],
                ];
            }

            $clientData = $response->json();
            $clientRoles = $clientData['roles'] ?? [];

            return [
                'success' => true,
                'iam_roles' => $this->getIamRoles($application),
                'client_roles' => $clientRoles,
                'comparison' => $this->compareRoles($application, $clientRoles),
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch client roles', [
                'app_key' => $application->app_key,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'iam_roles' => $this->getIamRoles($application),
                'client_roles' => [],
            ];
        }
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
     * Compare IAM roles with client roles.
     */
    protected function compareRoles(Application $application, array $clientRoles): array
    {
        $iamRoles = $this->getIamRoles($application);

        $iamSlugs = collect($iamRoles)->pluck('slug')->flip()->toArray();
        $clientSlugs = collect($clientRoles)->pluck('slug')->flip()->toArray();

        return [
            'in_sync' => collect($iamRoles)
                ->filter(fn($role) => isset($clientSlugs[$role['slug']]))
                ->values()
                ->toArray(),
            'missing_in_client' => collect($iamRoles)
                ->filter(fn($role) => !isset($clientSlugs[$role['slug']]))
                ->values()
                ->toArray(),
            'extra_in_client' => collect($clientRoles)
                ->filter(fn($role) => !isset($iamSlugs[$role['slug']]))
                ->values()
                ->toArray(),
        ];
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
