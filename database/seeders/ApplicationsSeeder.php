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

        $json = file_get_contents(database_path('applications.json'));
        $applications = json_decode($json, true);

        foreach ($applications as $data) {
            $data['created_by'] = $admin?->id;

            Application::updateOrCreate(
                ['app_key' => $data['app_key']],
                $data
            );
        }

        $this->command->info('Applications seeded successfully!');
    }
}
