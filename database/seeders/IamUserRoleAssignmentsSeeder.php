<?php

namespace Database\Seeders;

use App\Domain\Iam\Models\Application;
use App\Domain\Iam\Models\ApplicationRole;
use App\Domain\Iam\Models\UserApplicationRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class IamUserRoleAssignmentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('👥 Seeding IAM User Role Assignments...');

        $assignments = $this->getAssignments();

        foreach ($assignments as $assignment) {
            $user = User::where('nip', $assignment['nip'])->first();

            if (! $user) {
                $this->command->warn("⚠️  User with NIP '{$assignment['nip']}' not found, skipping.");

                continue;
            }

            foreach ($assignment['roles'] as $appKey => $roleSlugs) {
                $application = Application::where('app_key', $appKey)->first();

                if (! $application) {
                    $this->command->warn("⚠️  Application '{$appKey}' not found, skipping.");

                    continue;
                }

                foreach ($roleSlugs as $roleSlug) {
                    $role = ApplicationRole::where('application_id', $application->id)
                        ->where('slug', $roleSlug)
                        ->first();

                    if (! $role) {
                        $this->command->warn("⚠️  Role '{$roleSlug}' for app '{$appKey}' not found.");

                        continue;
                    }

                    UserApplicationRole::firstOrCreate([
                        'user_id' => $user->id,
                        'role_id' => $role->id,
                    ]);
                }

                $this->command->info("  ✅ Assigned " . count($roleSlugs) . " role(s) to {$user->name} for {$application->name}");
            }
        }

        $this->command->newLine();
        $this->command->info('✅ User role assignments completed!');
    }

    /**
     * Get user role assignments.
     */
    private function getAssignments(): array
    {
        $assignments = [];

        // Get all users
        $users = User::all();

        foreach ($users as $user) {
            if ($user->nip === '0000.00000') {
                // Super admin - gets super_admin for SIIMUT and admin for incident report
                $assignments[] = [
                    'nip' => $user->nip,
                    'roles' => [
                        'siimut' => ['super_admin'],
                        'incident-reporting' => ['super_admin'],
                    ],
                ];
            } else {
                // Regular users - only unit_kerja for SIIMUT
                $assignments[] = [
                    'nip' => $user->nip,
                    'roles' => [
                        'siimut' => ['unit_kerja'],
                    ],
                ];
            }
        }

        return $assignments;
    }
}
