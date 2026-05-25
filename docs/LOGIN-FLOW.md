# Login Flow README

Dokumen ini merangkum flow login pada project auth-server berdasarkan implementasi route, controller, request validation, dan middleware saat ini.

## Komponen Kunci

- Route login:
  - `GET /login` -> `AuthenticatedSessionController@create`
  - `POST /login` -> `AuthenticatedSessionController@store`
- Validasi kredensial dan rate limit: `LoginRequest`
- Session auth Laravel: `Auth::login(...)` + `session()->regenerate()`
- Redirect pasca login:
  - Normal: ke `url.intended` atau `home` (`/` -> `/dashboard`)
  - SSO context: ke `/sso/redirect?app={app_key}`
- Proteksi akses user non-aktif: `BlockInactiveUser` middleware

## Alur Logika Login (Ringkas)

1. User membuka halaman login (`GET /login`).
2. Jika query `app` ada, sistem menyimpan konteks SSO ke session:
   - `sso.intended_app = app_key`
   - `url.intended = route('sso.redirect', ['app' => app_key])`
3. User submit form (`POST /login`) dengan `nip` dan `password`.
4. `LoginRequest::validateCredentials()`:
   - Cek rate limit (maks 5 percobaan per `nip|ip`).
   - Ambil user berdasarkan `nip`.
   - Validasi password via auth provider.
5. Jika kredensial valid:
   - Jika 2FA aktif -> simpan `login.id` dan `login.remember`, redirect ke `two-factor.login`.
   - Jika status user bukan `active` -> redirect ke `account.status`.
   - Jika aktif -> `Auth::login`, regenerate session, update model `sessions` (`user_id`, `is_active=true`), catat last login.
6. Redirect akhir:
   - Jika ada `sso.intended_app` -> `Inertia::location(route('sso.redirect', ...))`.
   - Jika tidak ada -> `Inertia::location($intended)` (default ke `home` -> `/dashboard`).
7. Jika kredensial gagal/rate limit/exception -> kirim error validasi atau exception handling bawaan.

## Sequence Diagram

```text
User
 │
 │ 1. Open login page
 ▼
GET /login?app=app_key
 │
 ▼
AuthenticatedSessionController@create
 │
 ├─ Jika query `app` tersedia:
 │    ├─ Simpan `sso.intended_app = app_key`
 │    └─ Simpan `url.intended = /sso/redirect?app=app_key`
 │
 ▼
Render Login Page
 │
 ▼
User submit NIP + password
 │
 ▼
POST /login
 │
 ▼
AuthenticatedSessionController@store
 │
 ▼
LoginRequest::validateCredentials()
 │
 ├─ Cek rate limit berdasarkan `nip|ip`
 ├─ Ambil user berdasarkan `nip`
 └─ Validasi password melalui Laravel Auth Provider
 │
 ├───────────────────────────────────────────────────────────────┐
 │                                                               │
 ▼                                                               ▼
Credential invalid                                         Credential valid
 │                                                               │
 ├─ Tambah hit rate limiter                                      ├─ Clear rate limiter
 └─ Return validation error                                      └─ Return user
                                                                 │
                                                                 ▼
                                                    Cek status autentikasi lanjutan
                                                                 │
                  ┌──────────────────────────────┬───────────────┴────────────────┐
                  │                              │                                │
                  ▼                              ▼                                ▼
            2FA enabled                   User not active                    User active
                  │                              │                                │
                  ├─ Simpan `login.id`           └─ Redirect ke                   ├─ Auth::login(user)
                  ├─ Simpan `login.remember`       `account.status`               ├─ Regenerate session
                  └─ Redirect ke                                                  ├─ Update session row
                     `two-factor.login`                                           └─ Record last login
                                                                                  │
                                                                                  ▼
                                                                        Resolve final redirect
                                                                                     │
                                           ┌─────────────────────────────────────────┴─────────────────────────────────────────┐
                                           │                                                                                   │
                                           ▼                                                                                   ▼
                                SSO context tersedia                                                                SSO context tidak tersedia
                                           │                                                                                   │
                                           ├─ Redirect ke                                                                      ├─ Redirect ke `url.intended`
                                           │  `/sso/redirect?app=app_key`                                                      │  atau `/dashboard`
                                           │                                                                                   │
                                           ▼                                                                                   ▼
                                SsoRedirectController                                                               Dashboard / intended page
                                           │
                                           ▼
                                Redirect ke client application
```

## Route dan File Implementasi Utama

- `routes/auth.php`
- `routes/web.php`
- `routes/sso.php`
- `app/Http/Controllers/Auth/AuthenticatedSessionController.php`
- `app/Http/Requests/Auth/LoginRequest.php`
- `app/Http/Controllers/Sso/SsoRedirectController.php`
- `app/Http/Middleware/BlockInactiveUser.php`
- `app/Http/Middleware/Authenticate.php`
- `resources/js/pages/Auth/LoginPage.tsx`

## Catatan

- Input identitas utama login adalah `nip` (bukan email), dengan kompatibilitas fallback dari field `email` ke `nip` di `prepareForValidation()`.
- Terdapat route SSO lain (`/sso/authorize`) dengan alur authorization code grant; namun flow di atas fokus pada login web dan redirect SSO berbasis `app` yang dipakai saat ini di login page flow.
