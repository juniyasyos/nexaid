# 🔐 Laravel IAM + SSO RBAC

Central Identity & Access Management (IAM) server for Laravel with Single Sign-On (SSO) and role-based access control (RBAC).

<p align="left">
  <a href="https://www.php.net/releases/8.2/en.php"><img alt="PHP" src="https://img.shields.io/badge/PHP-%5E8.2-777BB4?logo=php&logoColor=white"></a>
  <a href="https://laravel.com"><img alt="Laravel" src="https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white"></a>
  <a href="https://filamentphp.com"><img alt="Filament" src="https://img.shields.io/badge/Filament-4-00B5D8"></a>
  <a href="https://spatie.be/docs/laravel-permission"><img alt="Spatie Permission" src="https://img.shields.io/badge/Spatie_Permission-6-00B5D8"></a>
</p>

---

## ⚠️ Important: NIP-Based Authentication

This IAM server is optimized for **NIP (Nomor Induk Pegawai)** as the primary user identifier.

- ✅ **Login field:** `nip`
- ✅ **Primary identifier:** unique `nip`
- ✅ **Email:** optional / nullable
- ✅ **NIP support is required for current migrations and auth flows**

---

## ✨ Features

- 🔐 **Central authentication server** for Laravel client applications
- 🆔 **NIP-based login** as the main identity field
- 🎫 **OAuth2-like SSO flow** with authorization and token exchange endpoints
- 👥 **RBAC management** using Spatie Permission
- 🔑 **JWT access token issuance, refresh, and verification**
- 📱 **Multi-application support** with application keys and redirect URIs
- 🛡️ **Security first**: token revocation, issuer validation, signed JWTs, and CSRF/state handling
- 📊 **Filament admin panel** for managing users, applications, roles, and permissions
- 🔄 **Token introspection** and user info endpoints for client apps
- 📝 **Built-in back-channel and session notification endpoints**

---

## 🏗️ Architecture

```
┌─────────────────┐         ┌─────────────────┐
│  Client Apps    │         │   IAM Server    │
│  (SIIMUT, etc)  │◄────────┤   (This Repo)   │
│                 │ Tokens  │                 │
└─────────────────┘         └─────────────────┘
```

Supported client integrations include SIIMUT, incident reporting systems, pharmacy systems, and custom hospital applications.

---

## 🚀 Quick Start

1. **Clone and install**

```bash
git clone https://github.com/juniyasyos/laravel-iam.git
cd laravel-iam
composer install
npm install
```

2. **Create your environment**

```bash
cp .env.example .env
php artisan key:generate
```

3. **Update `.env`**

Set database and app URLs, including the IAM issuer.

```env
APP_URL=http://localhost:8010
IAM_ISSUER=http://localhost:8010

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=iam
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

4. **Run migrations**

```bash
php artisan migrate
```

5. **Optional: seed sample data**

```bash
php artisan db:seed --class=IAMSampleDataSeeder
```

6. **Build assets and start the server**

```bash
npm run build
php artisan serve
```

7. **Visit the admin panel**

Default panel path is `/panel` and can be customized via `PANEL_PATH` in `.env`.

---

## 🔧 IAM Server Endpoints

### Public SSO routes

- `GET /sso/authorize` — authorization endpoint for client apps
- `GET /sso/logout/chain` — public logout chain for front-channel logout notifications
- `GET /sso/redirect` — redirect endpoint after local auth before issuing SSO codes

### Token exchange and refresh

- `POST /sso/token` — token exchange for SSO authorization flow
- `POST /sso/token/refresh` — refresh SSO access tokens
- `POST /oauth/token` — OAuth-compatible token endpoint

### Protected token-related endpoints

- `GET /sso/userinfo` — user info for SSO token consumers
- `GET /oauth/userinfo` — OAuth-style user info endpoint
- `GET /iam/user-applications` — user applications list
- `GET /iam/user-access-profiles` — user access profiles
- `GET /users/applications` — backward-compatible applications endpoint
- `GET /users/applications/detail` — backward-compatible application detail endpoint

### Server-to-server endpoints

- `POST /sso/introspect` — token introspection endpoint for client applications
- `POST /oauth/introspect` — OAuth introspection endpoint
- `POST /oauth/revoke` — revoke token/refresh access
- `POST /iam/notify-token-expired` — client token expiry notification

---

## 🧩 Client Integration Notes

Client applications connect using an `app_key` and a registered redirect URI.

- 📘 **Panduan otorisasi role-permission + unit kerja (untuk app client):** lihat `docs/role-permission-client-best-practices.md`

### Sample SSO flow

1. Redirect the user to:

```text
GET /sso/authorize?app_key=your_app_key&redirect_uri=https://client.app/callback&state=random123
```

2. After user consent, exchange the authorization code:

```bash
curl -X POST http://localhost:8010/oauth/token \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "authorization_code",
    "app_key": "your_app_key",
    "app_secret": "your_app_secret",
    "code": "AUTH_CODE",
    "redirect_uri": "https://client.app/callback"
  }'
```

3. Validate or introspect tokens using:

- `POST /sso/introspect`
- `POST /oauth/introspect`

4. Retrieve user details:

```bash
curl -X GET http://localhost:8010/oauth/userinfo \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

---

## 🧠 NIP and Authentication

This repository is designed around NIP as the primary identity field. Migrations already include:

- unique `nip` on users
- nullable `email`
- NIP-based password reset and login flows

If you are migrating from an email-based system, update your client apps and login forms to use `nip` instead.

---

## 👥 Managing Applications

### Filament admin panel

1. Open the panel at `/panel` (or configured `PANEL_PATH`).
2. Create a new application.
3. Set:
   - `app_key`
   - redirect URIs
   - client secret
   - allowed scopes
   - token expiry

### Seeded applications

Existing repo fixtures and seeders use keys such as `siimut` and `incident-reporting`.

---

## 👤 Managing Roles & Permissions

Manage roles and permissions through Filament or artisan.

```php
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

Permission::create(['name' => 'read:patients']);
$doctor = Role::create(['name' => 'doctor']);
$doctor->givePermissionTo('read:patients');
$user = User::find(1);
$user->assignRole('doctor');
```

---

## 🧪 Testing SSO Flow

### 1. Start the IAM server

```bash
php artisan serve
```

### 2. Test authorization

```text
http://localhost:8010/sso/authorize?app_key=siimut&redirect_uri=http://localhost:3000/auth/callback&state=test123
```

### 3. Exchange code for token

```bash
curl -X POST http://localhost:8010/oauth/token \
  -H "Content-Type: application/json" \
  -d '{
    "grant_type": "authorization_code",
    "app_key": "siimut",
    "app_secret": "siimut_secret_key_123",
    "code": "YOUR_AUTH_CODE",
    "redirect_uri": "http://localhost:3000/auth/callback"
  }'
```

### 4. Get user info

```bash
curl -X GET http://localhost:8010/oauth/userinfo \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"
```

---

## 🛡️ Security Features

- ✅ hashed client secrets
- ✅ redirect URI validation
- ✅ JWT signature validation
- ✅ token revocation
- ✅ refresh token lifecycle control
- ✅ NIP-based login with optional email
- ✅ CSRF/state handling for SSO flows

---

## 📦 Tech Stack

- Laravel 12
- PHP 8.2
- Filament v4
- Spatie Laravel Permission v6
- Laravel Passport for OAuth support
- Redis / database drivers for sessions and cache
- Inertia.js + Vue 3 + Tailwind CSS
- Pest PHP for testing

---

## 🧰 Development scripts

- `composer install`
- `npm install`
- `npm run dev`
- `npm run build`
- `composer test`
- `composer setup`

---

## 📝 License

MIT
