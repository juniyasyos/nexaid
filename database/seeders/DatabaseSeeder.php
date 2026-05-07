<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting IAM Database Seeding...');
        $this->command->newLine();

        // Order matters: Users -> Applications -> IAM Roles -> IAM User Role Assignments
        $this->call([
            // UserSeeder::class,                    // Create users first
            ApplicationsSeeder::class,            // Create registered applications
            IamRolesSeeder::class,                // Create IAM roles per application
            AccessProfileSeeder::class,           // Create access profiles and map to roles
            // UserAccessProfileSeeder::class,       // Assign access profiles to users ✅ ENABLE
            // IkpAccessProfileSeeder::class,        // Seed IKP-specific access profiles
            // IamUserRoleAssignmentsSeeder::class,  // Assign IAM roles to users directly
        ]);

        $this->command->newLine();
        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->newLine();

        // Display summary
        $this->displaySummary();
    }

    /**
     * Display seeding summary.
     */
    private function displaySummary(): void
    {
        $this->command->info('📊 Seeding Summary:');
        $this->command->newLine();

        $this->command->table(
            ['Resource', 'Count'],
            [
                ['Users', \App\Models\User::count()],
                ['Applications', \App\Domain\Iam\Models\Application::count()],
                ['IAM Roles', \App\Domain\Iam\Models\ApplicationRole::count()],
                ['Access Profiles', \App\Domain\Iam\Models\AccessProfile::count()],
                ['User Role Assignments', \App\Domain\Iam\Models\UserApplicationRole::count()],
                ['User Access Profiles', DB::table('user_access_profiles')->count()],
            ]
        );

        $this->command->newLine();
        $this->command->info('🌐 Access the admin panel: http://localhost:8000/admin');
        $this->command->newLine();
    }
}
