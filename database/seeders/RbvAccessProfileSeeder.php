<?php

namespace Database\Seeders;

use App\Domain\Iam\Models\AccessProfile;
use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\ApplicationRole;
use Illuminate\Database\Seeder;

class RbvAccessProfileSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $app = Application::where('app_key', 'rbv-services')->first();

        if (! $app) {
            $this->command->warn("⚠️ Application 'rbv-services' not found. Pastikan ApplicationsSeeder sudah dijalankan.");
            return;
        }

        $roles = ApplicationRole::where('application_id', $app->id)->get();

        $sekretarisRole = $roles->firstWhere('slug', 'sekretaris');

        if (! $sekretarisRole) {
            $this->command->warn("⚠️ Role 'sekretaris' not found for 'rbv-services'. Pastikan IamRolesSeeder sudah dijalankan.");
            return;
        }

        // Buat atau update Access Profile 'sekretaris'
        $profile = AccessProfile::updateOrCreate(
            ['slug' => 'sekretaris'],
            [
                'name'        => 'Sekretaris',
                'description' => 'Akses profil khusus untuk Sekretaris pada aplikasi RBV (Ruang Baca Virtual) untuk pengelolaan administrasi, jadwal pemesanan, dan alur persuratan.',
                'is_system'   => false,
                'is_active'   => true,
            ]
        );

        // Sync role sekretaris ke access profile
        $profile->roles()->syncWithoutDetaching([$sekretarisRole->id]);

        $this->command->info("✅ Access Profile 'sekretaris' untuk aplikasi RBV berhasil di-seed!");
    }
}
