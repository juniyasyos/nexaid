<?php

namespace App\Actions;

use App\Domain\Iam\Models\AccessProfile;
use App\Models\User;
use App\Models\UnitKerja;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class ImportUsersFromJsonAction
{
    /**
     * Import users from JSON data including access profiles and unit_kerjas relationships
     * 
     * The "roles" key in JSON will be mapped to AccessProfiles with matching slugs
     *
     * @param array $data JSON data array
     * @return array Statistics of import operation
     */
    public function execute(array $data): array
    {
        $created = 0;
        $updated = 0;
        $failed = 0;
        $errors = [];
        $accessProfilesNotFound = [];
        $unitKerjasNotFound = [];

        foreach ($data as $index => $userData) {
            try {
                DB::beginTransaction();

                $beforeData = null;
                $actionType = 'create';

                // Ambil data sebelum update/create
                if (! empty($userData['id'])) {
                    $existingUser = User::with(['accessProfiles', 'unitKerjas'])
                        ->find($userData['id']);

                    if ($existingUser) {
                        $actionType = 'update';

                        $beforeData = [
                            'user' => $existingUser->toArray(),
                            'roles' => $existingUser->accessProfiles
                                ->pluck('name')
                                ->toArray(),
                            'unit_kerjas' => $existingUser->unitKerjas
                                ->pluck('name')
                                ->toArray(),
                        ];
                    }
                }

                $user = User::updateOrCreate(
                    ['id' => $userData['id'] ?? null],
                    $this->sanitizeUserData($userData)
                );

                if ($user->wasRecentlyCreated) {
                    $created++;
                    $actionType = 'create';
                } else {
                    $updated++;
                }

                // Handle access profiles assignment (mapped from "roles" key)
                if (! empty($userData['roles']) && is_array($userData['roles'])) {
                    $this->syncAccessProfiles($user, $userData['roles'], $accessProfilesNotFound);
                }

                // Handle unit_kerjas assignment
                if (! empty($userData['unit_kerjas']) && is_array($userData['unit_kerjas'])) {
                    $this->syncUnitKerjas($user, $userData['unit_kerjas'], $unitKerjasNotFound);
                }

                // Refresh relasi terbaru
                $user->load(['accessProfiles', 'unitKerjas']);

                $afterData = [
                    'user' => $user->toArray(),
                    'roles' => $user->accessProfiles
                        ->pluck('name')
                        ->toArray(),
                    'unit_kerjas' => $user->unitKerjas
                        ->pluck('name')
                        ->toArray(),
                ];

                if ($userData['name'] == 'Agung Sunaryo, S.Kom') {
                    dd([
                        'action' => $actionType,
                        'is_created' => $user->wasRecentlyCreated,

                        'before' => $beforeData,

                        'after' => $afterData,

                        'incoming_payload' => $userData,
                    ]);
                }

                DB::commit();
            } catch (Throwable $e) {
                DB::rollBack();
                $failed++;
                $errors[] = [
                    'row' => $index + 1,
                    'nip' => $userData['nip'] ?? 'N/A',
                    'name' => $userData['name'] ?? 'N/A',
                    'error' => $e->getMessage(),
                ];
            }
        }

        $result = [
            'created' => $created,
            'updated' => $updated,
            'failed' => $failed,
            'total' => count($data),
            'errors' => $errors,
        ];

        // Add warnings if any access profiles or unit_kerjas were not found
        if (! empty($accessProfilesNotFound)) {
            $result['warnings'] = $result['warnings'] ?? [];
            $result['warnings']['access_profiles_not_found'] = array_unique($accessProfilesNotFound);
        }

        if (! empty($unitKerjasNotFound)) {
            $result['warnings'] = $result['warnings'] ?? [];
            $result['warnings']['unit_kerjas_not_found'] = array_unique($unitKerjasNotFound);
        }

        return $result;
    }

    /**
     * Sanitize and validate user data
     */
    private function sanitizeUserData(array $data): array
    {
        return [
            'nip' => $data['nip'] ?? null,
            'name' => $data['name'] ?? 'No Name',
            'email' => $data['email'] ?? null,
            'password' => $data['password'] ?? bcrypt('rschjaya1234'),
            'place_of_birth' => $data['place_of_birth'] ?? null,
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'gender' => $data['gender'] ?? null,
            'address_ktp' => $data['address_ktp'] ?? null,
            'phone_number' => $data['phone_number'] ?? null,
            'status' => $data['status'] ?? 'active',
            'avatar_url' => $data['avatar_url'] ?? null,
            'ttd_url' => $data['ttd_url'] ?? null,
            'email_verified_at' => $data['email_verified_at'] ?? null,
            'remember_token' => $data['remember_token'] ?? null,
        ];
    }


    /**
     * Sync user access profiles
     * Maps the "roles" array from JSON to AccessProfiles with matching slugs
     *
     * @param User $user
     * @param array $rolesArray Array of role slugs (will be matched to AccessProfile slugs)
     * @param array &$notFound Reference to track access profiles not found
     * @return void
     */
    private function syncAccessProfiles(User $user, array $rolesArray, array &$notFound): void
    {
        $accessProfileIds = [];

        // Normalize role slugs and prepare for efficient lookup
        $normalizedRoles = array_values(array_filter(array_map(fn($r) => is_scalar($r) ? trim((string) $r) : null, $rolesArray)));

        if (count($normalizedRoles) === 0) {
            // If no valid roles provided, detach all profiles
            $user->accessProfiles()->sync([]);
            return;
        }

        $profiles = AccessProfile::whereIn('slug', $normalizedRoles)->get()->keyBy('slug');

        foreach ($normalizedRoles as $roleSlug) {
            if (isset($profiles[$roleSlug])) {
                $accessProfileIds[] = $profiles[$roleSlug]->id;
            } else {
                $notFound[] = $roleSlug;
            }
        }

        // Sync the found access profiles (this replaces existing relationships)
        $user->accessProfiles()->sync($accessProfileIds);
    }

    /**
     * Sync user unit_kerjas
     *
     * @param User $user
     * @param array $unitKerjas Array of unit_kerja data (can be id, name, or slug)
     * @param array &$notFound Reference to track unit_kerjas not found
     * @return void
     */
    private function syncUnitKerjas(User $user, array $unitKerjas, array &$notFound): void
    {
        $unitKerjaIds = [];

        foreach ($unitKerjas as $unitKerjaData) {
            $unitKerja = null;

            // Prefer slug / name from payload because source IDs can differ from local IDs
            if (isset($unitKerjaData['slug'])) {
                $unitKerja = UnitKerja::where('slug', $unitKerjaData['slug'])->first();
            }
            // Try to find by name
            elseif (isset($unitKerjaData['unit_name'])) {
                $unitKerja = UnitKerja::where('unit_name', $unitKerjaData['unit_name'])->first();
            }
            // Fallback to id only if no slug/name match is available
            elseif (isset($unitKerjaData['id'])) {
                $unitKerja = UnitKerja::find($unitKerjaData['id']);
            }
            // If it's just a string (id or slug), try both
            elseif (is_string($unitKerjaData)) {
                $unitKerja = UnitKerja::find($unitKerjaData)
                    ?? UnitKerja::where('slug', $unitKerjaData)->first();
            }

            if ($unitKerja) {
                $unitKerjaIds[] = $unitKerja->id;
            } else {
                $notFound[] = is_array($unitKerjaData)
                    ? ($unitKerjaData['slug'] ?? $unitKerjaData['unit_name'] ?? 'unknown')
                    : $unitKerjaData;
            }
        }

        // Sync the found unit_kerjas (this replaces existing relationships)
        $user->unitKerjas()->sync($unitKerjaIds);
    }
}
