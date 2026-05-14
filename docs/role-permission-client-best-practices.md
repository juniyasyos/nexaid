# Role, Permission, dan Unit Kerja di App Client

Dokumen ini menjelaskan cara implementasi otorisasi di aplikasi client agar:
- berbasis `permission` (best practice, lebih aman untuk jangka panjang),
- tetap kompatibel dengan data IAM saat ini,
- menghindari bentrok logic antara role tinggi (mis. `super_admin`) dan pembatasan `unit kerja`.

## 1. Prinsip Utama

1. Gunakan **permission sebagai sumber keputusan akhir** di sisi client.
2. Perlakukan **role sebagai bundle** (pengelompokan permission), bukan syarat akses akhir.
3. Perlakukan **unit kerja sebagai scope data**, bukan pengganti permission.
4. Terapkan **deny by default** jika data otorisasi tidak lengkap/ambigu.

Ringkasnya: `role -> turunkan ke permission -> filter dengan scope unit kerja`.

## 2. Konteks Implementasi IAM Saat Ini

Berikut perilaku yang relevan dari codebase IAM:

1. Token/userinfo membawa `apps` dan `roles_by_app` per aplikasi.
2. User role efektif dihitung dari kombinasi:
   - role langsung user,
   - role dari access profile aktif.
3. Sinkronisasi user ke client (`push-users`) membawa data:
   - `roles` (slug role per aplikasi),
   - `unit_kerja` (array nama unit kerja user).
4. Akses admin panel IAM (`isIAMAdmin`) bukan role `super_admin` aplikasi, tetapi logic khusus IAM internal.

Implikasi penting:
- Jangan samakan `super_admin` pada aplikasi client dengan admin panel IAM.
- Otorisasi fitur bisnis client harus berdasar role/permission untuk app itu sendiri.

## 3. Model Otorisasi yang Direkomendasikan

## 3.1. Struktur Akses

Di sisi client, simpan mapping:

- `role_slug -> permission[]`
- `permission -> scope` (global atau per unit)

Contoh sederhana:

```json
{
  "super_admin": ["incident.read.*", "incident.write.*", "unit.scope.all"],
  "tim_mutu": ["incident.read", "incident.verify", "unit.scope.assigned"],
  "pelapor": ["incident.create", "incident.read.own", "unit.scope.assigned"]
}
```

## 3.2. Urutan Evaluasi Akses (wajib konsisten)

Gunakan urutan ini di semua endpoint/service client:

1. Validasi token/sesi.
2. Validasi user punya akses ke `app_key` aktif.
3. Ambil role user untuk `app_key` tersebut.
4. Turunkan role menjadi kumpulan permission.
5. Cek apakah action diizinkan oleh permission.
6. Jika action terkait data unit kerja, terapkan filter scope unit kerja.
7. Jika salah satu langkah gagal, tolak akses.

## 4. Aturan Menghindari Bentrokan `super_admin` vs `unit kerja`

Masalah umum: user punya assignment unit kerja tertentu, tetapi juga punya role tinggi (`super_admin`) sehingga aturan jadi ambigu.

Gunakan aturan eksplisit berikut:

1. Role tinggi **tidak otomatis** menembus batas unit kerja.
2. Bypass unit kerja hanya boleh jika user memiliki permission khusus, misalnya `unit.scope.all`.
3. Jika tidak punya `unit.scope.all`, maka tetap gunakan scope unit kerja assignment user.

Rekomendasi keputusan akhir:

| Kondisi | Hasil |
|---|---|
| Punya permission action + `unit.scope.all` | Boleh lintas semua unit |
| Punya permission action + `unit.scope.assigned` | Hanya unit assignment user |
| Hanya punya role tanpa permission action | Tolak |
| Data unit kerja kosong untuk aksi scoped | Tolak (fail-safe) |

## 5. Pseudocode Otorisasi Client

```php
function canAccess(string $appKey, string $action, array $resourceUnitIds, UserContext $ctx): bool
{
    if (! $ctx->tokenValid) {
        return false;
    }

    if (! in_array($appKey, $ctx->apps, true)) {
        return false;
    }

    $roles = $ctx->rolesByApp[$appKey] ?? [];
    $permissions = mapRolesToPermissions($roles);

    if (! hasPermission($permissions, $action)) {
        return false;
    }

    if (hasPermission($permissions, 'unit.scope.all')) {
        return true;
    }

    if (! hasPermission($permissions, 'unit.scope.assigned')) {
        return false;
    }

    if (empty($ctx->unitKerjaIds)) {
        return false; // fail-safe
    }

    return intersects($resourceUnitIds, $ctx->unitKerjaIds);
}
```

## 6. Checklist Implementasi Client

1. Definisikan registry permission per modul bisnis.
2. Definisikan mapping role IAM ke permission client (versioned/configurable).
3. Simpan unit kerja user dari payload sinkronisasi IAM (`unit_kerja`) atau sumber relasi yang setara di client.
4. Implement middleware/policy terpusat (hindari copy-paste di controller).
5. Tambahkan test kasus minimal:
   - role biasa dengan unit sesuai,
   - role biasa dengan unit tidak sesuai,
   - `super_admin` tanpa `unit.scope.all`,
   - `super_admin` dengan `unit.scope.all`,
   - user tanpa unit kerja,
   - token valid tapi role kosong.

## 7. Anti-Pattern yang Harus Dihindari

1. Mengecek `role == super_admin` lalu langsung allow semua fitur.
2. Menaruh logika otorisasi tersebar di banyak controller.
3. Menjadikan unit kerja sebagai satu-satunya kontrol akses tanpa permission action.
4. Menganggap role admin IAM internal setara dengan role aplikasi client.

## 8. Catatan Operasional

Untuk keberlanjutan project, tetapkan kontrak akses ini sebagai standar lintas tim:

1. Tim IAM bertanggung jawab pada identitas, role, dan distribusi data akses.
2. Tim client bertanggung jawab pada evaluasi permission final + data scope unit kerja di domain bisnis masing-masing.
3. Setiap role baru wajib disertai mapping permission dan test regresi konflik unit kerja.
