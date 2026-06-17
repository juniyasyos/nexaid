<?php

namespace App\Domain\Iam\DataTransferObjects;

class TokenClaims
{
    /**
     * @param  int  $userId  User ID (sub claim)
     * @param  string|null  $nip  User NIP (primary identifier)
     * @param  string|null  $email  User email
     * @param  string  $name  User name
     * @param  array<string>  $apps  List of app_keys user has access to
     * @param  array<string, array<string>>  $rolesByApp  Map of app_key to array of role slugs
     * @param  string  $issuer  Token issuer (iss claim)
     * @param  int  $issuedAt  Issued at timestamp (iat claim)
     * @param  int  $expiresAt  Expiry timestamp (exp claim)
     * @param  string|null  $unit  User's unit/department (optional)
     * @param  string|null  $employeeId  Employee ID (optional)
     * @param  array<string, mixed>  $extra  Additional custom claims
     */
    public function __construct(
        public readonly int $userId,
        public readonly ?string $nip,
        public readonly ?string $email,
        public readonly string $name,
        public readonly array $apps,
        public readonly array $rolesByApp,
        public readonly string $issuer,
        public readonly int $issuedAt,
        public readonly int $expiresAt,
        public readonly ?string $unit = null,
        public readonly ?string $employeeId = null,
        public readonly string $type = 'access',
        public readonly array $extra = []
    ) {}

    /**
     * Convert to JWT payload array.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $payload = [
            'sub' => $this->userId,
            'nip' => $this->nip,
            'email' => $this->email,
            'name' => $this->name,
            'apps' => $this->apps,
            'roles_by_app' => $this->rolesByApp,
            'iss' => $this->issuer,
            'iat' => $this->issuedAt,
            'exp' => $this->expiresAt,
            'type' => $this->type,
        ];

        // Add unit and employee_id if present
        if ($this->unit !== null) {
            $payload['unit'] = $this->unit;
        }
        if ($this->employeeId !== null) {
            $payload['employee_id'] = $this->employeeId;
        }

        // Include 'app' field from extra if present (for SSO application context)
        if (isset($this->extra['app'])) {
            $payload['app'] = $this->extra['app'];
        }

        // Include any other extra fields
        foreach ($this->extra as $key => $value) {
            if ($key !== 'app' && !isset($payload[$key])) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }

    /**
     * Create from array (useful for decoding JWT).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        // Extract extra fields that aren't standard JWT claims
        $extra = [];
        $standardClaims = ['sub', 'nip', 'email', 'name', 'apps', 'roles_by_app', 'iss', 'iat', 'exp', 'unit', 'employee_id', 'type'];

        foreach ($data as $key => $value) {
            if (!in_array($key, $standardClaims, true)) {
                $extra[$key] = $value;
            }
        }

        return new self(
            userId: $data['sub'] ?? 0,
            nip: $data['nip'] ?? null,
            email: $data['email'] ?? null,
            name: $data['name'] ?? '',
            apps: $data['apps'] ?? [],
            rolesByApp: $data['roles_by_app'] ?? [],
            issuer: $data['iss'] ?? '',
            issuedAt: $data['iat'] ?? 0,
            expiresAt: $data['exp'] ?? 0,
            unit: $data['unit'] ?? null,
            employeeId: $data['employee_id'] ?? null,
            type: $data['type'] ?? 'access',
            extra: $extra
        );
    }

    /**
     * Check if token has expired.
     */
    public function isExpired(): bool
    {
        return time() >= $this->expiresAt;
    }

    /**
     * Get time until expiry in seconds.
     */
    public function getTimeUntilExpiry(): int
    {
        return max(0, $this->expiresAt - time());
    }

    /**
     * Check if user has access to a specific app.
     */
    public function hasAccessToApp(string $appKey): bool
    {
        return in_array($appKey, $this->apps);
    }

    /**
     * Get roles for a specific app.
     *
     * @return array<string>
     */
    public function getRolesForApp(string $appKey): array
    {
        return $this->rolesByApp[$appKey] ?? [];
    }

    /**
     * Check if user has a specific role in an app.
     */
    public function hasRoleInApp(string $appKey, string $roleSlug): bool
    {
        $roles = $this->getRolesForApp($appKey);

        return in_array($roleSlug, $roles);
    }
}
