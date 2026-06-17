# Laporan Analisis Keamanan dan Logika Sistem SSO

Berdasarkan hasil penelusuran terhadap source code di `/home/juni/projects/apps/auth-server`, ditemukan beberapa celah keamanan (Security Flaws) dan cacat logika (Logic Flaws) yang cukup kritikal dalam implementasi Single Sign-On (SSO).

Berikut adalah daftar temuan beserta penjelasannya:

## 1. Information Disclosure (Kebocoran Informasi) via URL Parameter
**Lokasi:** `App\Http\Controllers\Sso\SsoRedirectController::invoke()`
**Tingkat Keparahan:** Medium - High

**Deskripsi:**
Ketika terjadi *exception* atau *error* saat proses *redirect* SSO, sistem akan melempar *user* kembali ke `callback_url` aplikasi klien. Sayangnya, parameter *query string* yang dikirimkan memuat informasi sensitif dari *exception* tersebut.

```php
$redirectUrl = $application->callback_url . $separator . http_build_query([
    'error' => 'access_denied',
    'error_description' => $exception->getMessage(),
    'error_type' => class_basename($exception),
    'error_location' => $exception->getFile() . ':' . $exception->getLine(),
]);
```

**Dampak:**
Mengekspos *path* direktori internal *server* (`getFile()`), baris kode (`getLine()`), dan kemungkinan pesan *error* internal basis data atau logika aplikasi (`getMessage()`) langsung ke *browser* pengguna dan aplikasi klien yang mungkin merupakan pihak ketiga. Ini memudahkan penyerang untuk memetakan struktur internal *server*.

**Rekomendasi:**
Catat (*log*) detail *exception* di sisi *server*, tetapi hanya kembalikan pesan *error* generik (seperti "Internal Server Error" atau "Authentication Failed") kepada klien tanpa parameter `error_location`.

---

## 2. Cacat Logika pada Proses *Refresh Token* (Masa Aktif Token Tak Terbatas)
**Lokasi:** `App\Domain\Iam\Services\TokenBuilder::refresh()`
**Tingkat Keparahan:** High

**Deskripsi:**
Fungsi `refresh` pada `TokenBuilder` menangkap (catch) *error* ketika token sudah kedaluwarsa (*expired*), namun secara eksplisit mengabaikan *error* tersebut dan tetap melanjutkan proses pembuatan token baru dengan melakukan *decode* secara paksa.

```php
} catch (\Exception $verifyErr) {
    $message = strtolower($verifyErr->getMessage());
    if (! str_contains($message, 'expired')) {
        throw new \Exception('Token refresh denied: ' . $verifyErr->getMessage());
    }
    // Jika error karena 'expired', kode tetap berjalan ke bawah untuk me-refresh token
```

**Dampak:**
Sebuah token yang sudah kedaluwarsa dapat terus-menerus di-*refresh* menjadi token baru tanpa batas waktu, asalkan tidak dicabut secara manual (seperti lewat *logout*). Ini menggagalkan fungsi keamanan utama dari masa berlaku (*expiry*) token.

**Rekomendasi:**
Sistem harus menggunakan *Refresh Token* khusus yang divalidasi masa berlakunya. Token akses (*Access Token*) yang sudah *expired* tidak boleh serta-merta bisa menghasilkan token akses yang baru tanpa verifikasi tambahan atau *Refresh Token* yang valid.

---

## 3. Tidak Ada Pemisahan antara *Access Token* dan *Refresh Token*
**Lokasi:** `App\Domain\Iam\Services\TokenBuilder` & `App\Domain\Iam\Http\Controllers\SsoTokenController`
**Tingkat Keparahan:** High

**Deskripsi:**
Tidak seperti implementasi `JWTTokenService` yang lama (di mana terdapat *claim* `'type' => 'access'` dan `'type' => 'refresh'`), `TokenBuilder` yang baru tidak mendefinisikan *claim* `type`. Akibatnya, *endpoint* `/sso/token/refresh` dapat menerima sembarang token (termasuk *Access Token*) untuk di-*refresh* menjadi token baru.

**Dampak:**
Jika seorang penyerang berhasil mencuri *Access Token* pengguna (yang seharusnya berumur pendek), penyerang tersebut dapat terus-menerus memanggil *endpoint* `refresh` untuk mendapatkan *Access Token* baru selamanya.

**Rekomendasi:**
Tambahkan atribut `type` pada `TokenClaims`. Pastikan *endpoint* `refresh` hanya menerima token dengan `type === 'refresh'`, dan *endpoint* verifikasi hanya menerima token dengan `type === 'access'`.

---

## 4. Kerentanan *Confused Deputy* (Token Re-use Lintas Aplikasi)
**Lokasi:** `App\Domain\Iam\Http\Controllers\SsoTokenController::issueToken()`
**Tingkat Keparahan:** High

**Deskripsi:**
Pada *endpoint* `issueToken`, parameter `app_key` bersifat opsional (`nullable`). Jika `app_key` tidak disertakan, sistem akan menerbitkan token generik yang berisi semua aplikasi (`apps`) dan semua hak akses (`roles_by_app`) yang dimiliki oleh *user* tersebut, tanpa membatasi (*scoping*) token untuk digunakan pada satu aplikasi spesifik (sebagai *audience*).

**Dampak:**
Aplikasi klien yang jahat (malicious app) dapat menggunakan token generik ini untuk mengakses aplikasi *lain* atas nama *user* tersebut, karena *endpoint* verifikasi mungkin tidak memvalidasi apakah token tersebut secara eksplisit diterbitkan khusus untuk aplikasi target.

**Rekomendasi:**
Wajibkan parameter `app_key` (atau *client_id*) setiap kali menerbitkan token. Pastikan setiap token memiliki *claim* *audience* (`aud` atau `app`) yang mengikat token tersebut hanya pada satu aplikasi tujuan secara spesifik.

---

## 5. *Race Condition* pada Validasi *Authorization Code*
**Lokasi:** `App\Services\Sso\SsoClientService::consumeAuthorizationCode()`
**Tingkat Keparahan:** Medium

**Deskripsi:**
Pengambilan dan penghapusan *Authorization Code* dari Cache dilakukan secara berurutan, bukan atomik:

```php
$codeData = Cache::get("auth_code:{$code}");
if (! is_array($codeData)) { return null; }
Cache::forget("auth_code:{$code}");
```

**Dampak:**
Dalam skenario konkuensi tinggi (seperti *double-click* atau *scripting attack*), jika dua *request* datang tepat bersamaan untuk menukar kode yang sama, keduanya bisa berhasil melewati pengecekan `Cache::get()` sebelum `Cache::forget()` sempat tereksekusi. Akibatnya, satu kode otorisasi dapat digunakan lebih dari satu kali (*Replay Attack*).

**Rekomendasi:**
Gunakan operasi atomik. Pada Laravel, Anda bisa menggunakan metode `Cache::pull()` yang secara atomik mengambil sekaligus menghapus data dalam satu proses eksekusi.
