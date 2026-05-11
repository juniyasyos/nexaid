<?php

return [

    /*
    |--------------------------------------------------------------------------
    | IAM Home Application (Default)
    |--------------------------------------------------------------------------
    |
    | Default app entry injected in user applications response. This value is
    | intended to point back to IAM home/dashboard.
    |
    */
    'home_app' => [
        'enabled' => env('IAM_HOME_APP_ENABLED', true),
        'app_key' => env('IAM_HOME_APP_KEY', 'iam-home'),
        'name' => env('IAM_HOME_APP_NAME', 'IAM Home'),
        'description' => env('IAM_HOME_APP_DESCRIPTION', 'Portal utama IAM'),
        'url' => env('IAM_HOME_URL', 'http://127.0.0.1:8010/'),
        'logo_url' => env('IAM_HOME_APP_LOGO_URL', null),
    ],

    /*
    |--------------------------------------------------------------------------
    | IAM Issuer
    |--------------------------------------------------------------------------
    |
    | The issuer identifier for JWT tokens. This should be the URL of your
    | IAM service and will be included in the 'iss' claim of all tokens.
    |
    */

    'issuer' => env('IAM_ISSUER', env('APP_URL', 'https://iam.local')),

    /*
    |--------------------------------------------------------------------------
    | Token Time-to-Live (TTL)
    |--------------------------------------------------------------------------
    |
    | The default lifetime in seconds for access tokens issued by the IAM.
    | Default is 3600 seconds (1 hour). Individual applications can override
    | this via their token_expiry field.
    |
    */

    'token_ttl' => env('IAM_TOKEN_TTL', 3600),

    /*
    |--------------------------------------------------------------------------
    | Unit Kerja Delete Behavior
    |--------------------------------------------------------------------------
    |
    | Control how deleted unit kerja and users are handled:
    | - true (default): soft delete, unit kerja stays recoverable
    | - false: force delete, permanently removed from IAM and pushed to clients
    |
    */
    'unit_kerja_delete_soft' => env('IAM_UNIT_KERJA_DELETE_SOFT', false),
    'user_delete_soft' => env('IAM_USER_DELETE_SOFT', false),

    /*
    |--------------------------------------------------------------------------
    | Push Deleted Records
    |--------------------------------------------------------------------------
    |
    | Include deleted records in push payload to signal client deletion.
    | Only applies when soft delete is disabled (force delete mode).
    |
    */
    'push_deleted_records' => env('IAM_PUSH_DELETED_RECORDS', true),

    /*
    |--------------------------------------------------------------------------
    | SSO Shared Secret
    |--------------------------------------------------------------------------
    |
    | Shared secret used for backchannel HMAC verification with client apps.
    | This should be configured the same in each client app (SSO_SECRET/iam.sso_secret).
    |
    */
    'sso_secret' => env('IAM_SSO_SECRET', env('SSO_SECRET', env('APP_KEY'))),

    'jwt_secret' => env('IAM_JWT_SECRET', env('APP_KEY')),

    /*
    |--------------------------------------------------------------------------
    | JWT Signing Key
    |--------------------------------------------------------------------------
    |
    | The secret key used to sign JWT tokens. Falls back to APP_KEY if not set.
    | For production, consider using a separate dedicated signing key.
    |
    */

    'signing_key' => env('IAM_SIGNING_KEY', env('APP_KEY')),

    /*
    |------------------------------------------------------------------------
    | Back‑channel authentication method
    |------------------------------------------------------------------------
    |
    | Mechanism used when IAM sends server‑to‑server requests (logout
    | notifications, user/role sync, etc).  Supported values:
    |
    | * `jwt` – send a short‑lived JWT in the Authorization header (preferred).
    | * `hmac` – legacy mode; compute HMAC over body using `sso.secret`.
    |
    | The corresponding client must be configured to expect the same method.
    |
    */

    /*
    |------------------------------------------------------------------------
    | Back-channel security toggle
    |------------------------------------------------------------------------
    |
    | When set to false the `iam.backchannel.verify` middleware will be
    | bypassed entirely.  This is useful during early development where
    | you just want the sync route to exist without worrying about valid
    | signatures or tokens.  The route itself remains under the
    | `sync_users` flag.
    |
    */
    'backchannel_verify' => env('IAM_BACKCHANNEL_VERIFY', true),

    'backchannel_method' => env('IAM_BACKCHANNEL_METHOD', 'jwt'),


    /*
    |--------------------------------------------------------------------------
    | JWT Algorithm
    |--------------------------------------------------------------------------
    |
    | The algorithm used to sign JWT tokens. Default is HS256 (HMAC SHA-256).
    | Supported algorithms: HS256, HS384, HS512, RS256, RS384, RS512, etc.
    |
    */

    'algorithm' => env('IAM_JWT_ALGORITHM', 'HS256'),

    /*
    |--------------------------------------------------------------------------
    | Refresh Token TTL
    |--------------------------------------------------------------------------
    |
    | The lifetime in seconds for refresh tokens. Default is 30 days.
    |
    */

    'refresh_token_ttl' => env('IAM_REFRESH_TOKEN_TTL', 86400 * 30),

    /*
    |--------------------------------------------------------------------------
    | Authorization Code TTL
    |--------------------------------------------------------------------------
    |
    | The lifetime in seconds for authorization codes. These should be short-
    | lived. Default is 5 minutes (300 seconds).
    |
    */

    'auth_code_ttl' => env('IAM_AUTH_CODE_TTL', 300),

    /*
    |--------------------------------------------------------------------------
    | Token Audience
    |--------------------------------------------------------------------------
    |
    | Optional audience claim for JWT tokens. If set, this will be included
    | in the 'aud' claim of all tokens. Can be a string or array.
    |
    */

    'audience' => env('IAM_TOKEN_AUDIENCE', null),

    /*
    |--------------------------------------------------------------------------
    | System Roles Protection
    |--------------------------------------------------------------------------
    |
    | Whether to enforce protection on system roles (prevent deletion, slug
    | changes, etc.). Default is true for production safety.
    |
    */

    'protect_system_roles' => env('IAM_PROTECT_SYSTEM_ROLES', true),

    /*
    |------------------------------------------------------------------------
    | Role synchronization mode
    |------------------------------------------------------------------------
    |
    | Mode determines direction of role sync between IAM and client.
    | * pull: IAM pulls roles from client and updates IAM (default)
    | * push: IAM pushes roles to client
    */
    'role_sync_mode' => env('IAM_ROLE_SYNC_MODE', 'push'),

    /*
    |------------------------------------------------------------------------
    | User synchronization mode
    |------------------------------------------------------------------------
    |
    | Mode determines direction of user sync between IAM and client.
    | * pull: IAM pulls users from client and updates IAM (default)
    | * push: IAM pushes users to client (including delete propagation)
    */
    'user_sync_mode' => env('IAM_USER_SYNC_MODE', 'push'),

    /*
    |------------------------------------------------------------------------
    | Push mode user creation policy
    |------------------------------------------------------------------------
    |
    | When `user_sync_mode` is `push`, new users are created only if this
    | setting is true. Default is true, enabling provisioning from IAM.
    */
    'user_sync_from_iam_allow_create' => env('IAM_USER_SYNC_FROM_IAM_ALLOW_CREATE', true),

    /*
    |------------------------------------------------------------------------
    | Push mode user delete policy
    |------------------------------------------------------------------------
    |
    | If true, when IAM pushes users the client may delete/disable local users
    | that are no longer present in IAM payload.
    |
    */
    'user_sync_from_iam_delete_missing' => env('IAM_USER_SYNC_FROM_IAM_DELETE_MISSING', false),

    /*
    |------------------------------------------------------------------------
    | Pull mode role creation policy
    |------------------------------------------------------------------------
    |
    | If true, IAM creates roles that exist in client but not in IAM.
    | If false, only existing roles are updated.
    */
    'role_sync_from_client_allow_create' => env('IAM_ROLE_SYNC_FROM_CLIENT_ALLOW_CREATE', false),

    /*
    |------------------------------------------------------------------------
    | Push mode role policy

    |------------------------------------------------------------------------
    |
    | In push mode, IAM sends roles to client; client decides if new role can be created.
    */
    'role_sync_from_iam_allow_create' => env('IAM_ROLE_SYNC_FROM_IAM_ALLOW_CREATE', false),

    /*
    |--------------------------------------------------------------------------
    | User Response Fields
    |--------------------------------------------------------------------------
    |
    | Define which user fields should be included in API responses.
    | Fields are returned in the order specified here.
    |
    | Default fields: id, name, nip, status
    |
    */

    'user_fields' => env('IAM_USER_FIELDS', 'id,name,nip,email,status'),

    'user_sync_force_pull' => env('IAM_USER_SYNC_FORCE_PULL', false),

    /*
    |------------------------------------------------------------------------
    | Password Field Sync Control
    |------------------------------------------------------------------------
    |
    | When enabled, password changes will trigger user synchronization to
    | client applications. Disable this if client applications manage their
    | own password hashing and you don't want to re-hash IAM passwords.
    |
    | Default: false (password changes do NOT trigger client sync)
    | Set to true to enable password sync on IAM updates.
    */
    'user_sync_password_field' => env('IAM_USER_SYNC_PASSWORD_FIELD', false),

    /*
    |--------------------------------------------------------------------------
    | Default User Roles
    |--------------------------------------------------------------------------
    |
    | Optional configuration for automatically assigning default roles to new
    | users for specific applications.
    |
    | Example: ['siimut' => ['viewer'], 'incident' => ['reporter']]
    |
    */

    'default_user_roles' => [
        // 'siimut' => ['viewer'],
    ],

    /*
    |--------------------------------------------------------------------------
    | IAM Admin Access Control
    |--------------------------------------------------------------------------
    |
    | Configure who can access IAM admin panel (Filament) and monitoring tools (Pulse).
    | Supports multiple rule types with flexible operators.
    |
    */

    'admin_access' => [

        /*
        |--------------------------------------------------------------------------
        | Access Rules
        |--------------------------------------------------------------------------
        |
        | Define access rules as an array. Each rule is evaluated and combined
        | based on the operator setting below.
        |
        | Rule types:
        | 
        | 1. field_in: Check if field value is in allowed list
        |    ['type' => 'field_in', 'field' => 'nip', 'values' => ['0000.00000', '1111.11111']]
        |
        | 2. field: Check field with operator
        |    ['type' => 'field', 'field' => 'is_admin', 'operator' => '=', 'value' => true]
        |    ['type' => 'field', 'field' => 'email', 'operator' => 'ends_with', 'value' => '@admin.com']
        |    Operators: =, ==, !=, !==, >, >=, <, <=, contains, starts_with, ends_with
        |
        | 3. callback: Custom function
        |    ['type' => 'callback', 'callback' => function($user) { return $user->hasPermission('admin'); }]
        |
        | 4. role: Check Spatie role (if using spatie/laravel-permission)
        |    ['type' => 'role', 'role' => 'super-admin']
        |
        | 5. permission: Check Spatie permission
        |    ['type' => 'permission', 'permission' => 'access-iam-panel']
        |
        */
        'rules' => [
            // Check if NIP is in whitelist
            [
                'type' => 'field_in',
                'field' => 'nip',
                'values' => array_filter(
                    array_map('trim', explode(',', env('IAM_ADMIN_NIPS', '0000.00000')))
                ),
            ],

            // Example: Check boolean field
            // [
            //     'type' => 'field',
            //     'field' => 'can_access_iam_panel',
            //     'operator' => '=',
            //     'value' => true,
            // ],

            // Example: Check email domain
            // [
            //     'type' => 'field',
            //     'field' => 'email',
            //     'operator' => 'ends_with',
            //     'value' => '@admin.company.com',
            // ],

            // Example: Custom callback
            // [
            //     'type' => 'callback',
            //     'callback' => function ($user) {
            //         return $user->department === 'IT' && $user->level >= 5;
            //     },
            // ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Rules Operator
        |--------------------------------------------------------------------------
        |
        | How to combine multiple rules:
        | - 'or': User passes if ANY rule passes (default)
        | - 'and': User passes only if ALL rules pass
        |
        */
        'operator' => env('IAM_ADMIN_ACCESS_OPERATOR', 'or'),

        /*
        |--------------------------------------------------------------------------
        | Access Denied Message
        |--------------------------------------------------------------------------
        |
        | Message to display when access is denied.
        |
        */
        'denied_message' => env(
            'IAM_ADMIN_DENIED_MESSAGE',
            'Access denied. Only authorized IAM administrators can access this area.'
        ),

        /*
        |--------------------------------------------------------------------------
        | Redirect After Denial
        |--------------------------------------------------------------------------
        |
        | Where to redirect users when access is denied.
        | Set to null to show 403 error page instead.
        |
        */
        'denied_redirect' => env('IAM_ADMIN_DENIED_REDIRECT', null), // null = show 403, or '/' for home
    ],

    /*
    |------------------------------------------------------------------------
    | Import settings
    |------------------------------------------------------------------------
    |
    | Controls for JSON import jobs.
    | - delete_source_after_import: when true, uploaded source file is removed
    |   from MinIO/S3 after job finishes.
    |
    */
    'imports' => [
        'delete_source_after_import' => env('IAM_IMPORT_DELETE_SOURCE_AFTER_IMPORT', false),
    ],

];
