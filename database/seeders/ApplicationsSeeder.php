<?php

namespace Database\Seeders;

use App\Domain\Iam\Models\Application;
use App\Models\User;
use Illuminate\Database\Seeder;

class ApplicationsSeeder extends Seeder
{
    public function run(): void
    {
        // Get admin user untuk created_by
        $admin = User::where('nip', '0000.00000')->first();

        $applications = [
            // [
            //     'app_key' => 'siimut',
            //     'name' => 'SIIMUT - Sistem Informasi Manajemen Indikator Mutu Terpadu',
            //     'description' => 'Platform terintegrasi untuk pengelolaan, monitoring, evaluasi, dan pelaporan indikator mutu rumah sakit serta unit kerja secara digital.',
            //     'enabled' => true,
            //     'redirect_uris' => [
            //         'http://127.0.0.1:8088',
            //     ],
            //     'callback_url' => 'http://127.0.0.1:8088/sso/callback',
            //     'backchannel_url' => 'http://127.0.0.1:8088',
            //     'secret' => 'siimut_secret_key_123',
            //     'logo_url' => null,
            //     'token_expiry' => 3600,
            //     'created_by' => $admin?->id,
            // ],
            // [
            //     'app_key' => 'incident-reporting',
            //     'name' => 'IKP - Incident Reporting System',
            //     'description' => 'Sistem pelaporan dan manajemen insiden rumah sakit untuk mendukung proses pelaporan, investigasi, monitoring, dan tindak lanjut insiden secara terstruktur.',
            //     'enabled' => true,
            //     'redirect_uris' => [
            //         'http://127.0.0.1:8200',
            //     ],
            //     'callback_url' => 'http://127.0.0.1:8200/sso/callback',
            //     'backchannel_url' => 'http://127.0.0.1:8200',
            //     'secret' => 'ikp_secret_key_789',
            //     'logo_url' => null,
            //     'token_expiry' => 7200,
            //     'created_by' => $admin?->id,
            // ],
            [
                'app_key' => 'lms-services',
                'name' => 'LMS - Learning Management Systems',
                'description' => 'Sistem manajemen pembelajaran untuk mendukung pelatihan, distribusi materi, pemantauan peserta, dan pengembangan kompetensi sumber daya manusia.',
                'enabled' => true,
                'redirect_uris' => [
                    'http://127.0.0.1:7100',
                ],
                'callback_url' => 'http://127.0.0.1:7100/sso/callback',
                'backchannel_url' => 'http://127.0.0.1:7100',
                'secret' => 'lms_secret_key_789',
                'logo_url' => null,
                'token_expiry' => 7200,
                'created_by' => $admin?->id,
            ],
        ];

        foreach ($applications as $data) {
            Application::updateOrCreate(
                ['app_key' => $data['app_key']],
                $data
            );
        }

        $this->command->info('Applications seeded successfully!');
    }
}
