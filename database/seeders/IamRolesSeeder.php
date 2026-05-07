<?php

namespace Database\Seeders;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\ApplicationRole;
use Illuminate\Database\Seeder;

class IamRolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🎭 Seeding IAM Roles per Application...');

        $rolesData = $this->getRolesData();

        foreach ($rolesData as $appKey => $roles) {
            $application = Application::where('app_key', $appKey)->first();

            if (! $application) {
                $this->command->warn("⚠️  Application '{$appKey}' not found, skipping roles.");

                continue;
            }

            $this->command->info("  Creating roles for: {$application->name}");

            foreach ($roles as $roleData) {
                ApplicationRole::firstOrCreate(
                    [
                        'application_id' => $application->id,
                        'slug' => $roleData['slug'],
                    ],
                    [
                        'name' => $roleData['name'],
                        'description' => $roleData['description'],
                        'is_system' => $roleData['is_system'] ?? false,
                    ]
                );
            }

            $this->command->info("    ✅ Created " . count($roles) . ' roles');
        }

        $this->command->newLine();
        $this->command->info('✅ IAM Roles seeding completed!');
    }

    /**
     * Get roles data for each application.
     */
    private function getRolesData(): array
    {
        return [
            'siimut' => [
                [
                    'slug' => 'super_admin',
                    'name' => 'Super Administrator',
                    'description' => 'Memiliki akses penuh untuk mengelola seluruh konfigurasi, data, pengguna, dan fitur pada sistem SIIMUT.',
                    'is_system' => true,
                ],
                [
                    'slug' => 'tim_mutu',
                    'name' => 'Koordinator Mutu',
                    'description' => 'Bertugas melakukan monitoring, evaluasi, verifikasi, serta pengelolaan data mutu pada unit kerja terkait.',
                    'is_system' => false,
                ],
                [
                    'slug' => 'pengumpul_data',
                    'name' => 'Petugas Input Data Mutu',
                    'description' => 'Bertanggung jawab melakukan pengumpulan, pengisian, dan pembaruan data mutu sesuai kebutuhan pelaporan.',
                    'is_system' => false,
                ],
                [
                    'slug' => 'validator_pic',
                    'name' => 'Validator Data Mutu',
                    'description' => 'Melakukan pemeriksaan dan validasi terhadap data mutu sebelum diproses atau dipublikasikan.',
                    'is_system' => false,
                ],
            ],

            'incident-reporting' => [
                [
                    'slug' => 'super_admin',
                    'name' => 'Super Administrator Incident Report',
                    'description' => 'Memiliki akses penuh untuk mengelola sistem pelaporan insiden, pengguna, data, dan pengaturan aplikasi.',
                    'is_system' => true,
                ],
                [
                    'slug' => 'tim_mutu',
                    'name' => 'Tim Investigasi & Mutu',
                    'description' => 'Bertugas melakukan monitoring, investigasi, tindak lanjut, dan evaluasi terhadap laporan insiden yang masuk.',
                    'is_system' => false,
                ],
                [
                    'slug' => 'kepala_unit',
                    'name' => 'Kepala Unit / Penanggung Jawab',
                    'description' => 'Bertugas melakukan pemantauan, evaluasi, persetujuan, serta tindak lanjut terhadap laporan insiden pada unit kerja yang dipimpin.',
                    'is_system' => false,
                ],
                [
                    'slug' => 'pelapor',
                    'name' => 'Pelapor Insiden',
                    'description' => 'Dapat membuat dan mengelola laporan insiden sesuai kewenangan yang diberikan.',
                    'is_system' => false,
                ],
            ],
        ];
    }
}
