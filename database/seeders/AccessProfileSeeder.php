<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domain\Iam\Models\AccessProfile;
use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\ApplicationRole;

class AccessProfileSeeder extends Seeder
{
    public function run(): void
    {
        /**
         * 🔥 SIIMUT ACCESS PROFILES (loaded from JSON)
         * Hanya untuk aplikasi siimut
         * IKP di-handle di IkpAccessProfileSeeder
         */
        $configPath = config_path('access-profiles-siimut.json');
        $mappings = json_decode(file_get_contents($configPath), true);

        /**
         * 🔥 Prefetch (biar hemat query)
         */
        $applications = Application::pluck('id', 'app_key');
        $roles = ApplicationRole::get()->groupBy('application_id');

        foreach ($mappings as $map) {
            /**
             * ✅ Upsert Access Profile
             */
            $profileData = $map['profile'];

            $profile = AccessProfile::updateOrCreate(
                ['slug' => $profileData['slug']],
                [
                    'name'        => $profileData['name'],
                    'description' => $profileData['description'],
                    'is_system'   => $profileData['is_system'],
                    'is_active'   => true,
                ]
            );

            $roleIds = [];

            /**
             * ✅ Loop Apps
             */
            foreach ($map['apps'] as $appKey => $roleConfigs) {
                $appId = $applications[$appKey] ?? null;

                if (! $appId) {
                    $this->command->warn("⚠️ Application '{$appKey}' not found");
                    continue;
                }

                $existingRoles = $roles->get($appId, collect());

                /**
                 * ✅ Loop Roles
                 */
                foreach ($roleConfigs as $roleData) {
                    if (! is_array($roleData) || empty($roleData['slug'])) {
                        $this->command->warn("⚠️ Invalid role config for app '{$appKey}': expected array with slug");
                        continue;
                    }

                    $role = $existingRoles->firstWhere('slug', $roleData['slug']);

                    // if (! $role) {
                    //     $role = ApplicationRole::create([
                    //         'application_id' => $appId,
                    //         'slug' => $roleData['slug'],
                    //         'name' => $roleData['name'] ?? ucfirst(str_replace(['_', '-'], ' ', $roleData['slug'])),
                    //         'description' => $roleData['description'] ?? 'Akses peran yang diatur oleh IAM',
                    //         'is_system' => false,
                    //     ]);

                    //     // update cache (penting biar gak miss di loop berikutnya)
                    //     if ($roles->has($appId)) {
                    //         $roles[$appId]->push($role);
                    //     } else {
                    //         $roles[$appId] = collect([$role]);
                    //     }

                    //     $this->command->info("  ℹ️ Created role '{$roleData['slug']}' for app '{$appKey}'");
                    // }

                    $roleIds[] = $role->id;
                }
            }

            /**
             * ✅ Sync Roles ke Profile
             * NOTE: Use sync() instead of syncWithoutDetaching() to completely
             * replace old role links with new ones (fresh mapping each run)
             */
            if (! empty($roleIds)) {
                $profile->roles()->sync($roleIds);

                $this->command->info(
                    "  ✅ Profile '{$profile->slug}' synced (" . count($roleIds) . " roles)"
                );
            }
        }
    }
}
