<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class SwapGenderPerempuanFemaleSeeder extends Seeder
{
    /**
     * Normalize gender values to English:
     * - Perempuan -> female
     * - Laki-laki (and common variants) -> male
     */
    public function run(): void
    {
        $this->command->info('Starting gender normalization: Indonesian -> English');

        $updatedCount = 0;

        $map = [
            'Perempuan' => 'female',
            'perempuan' => 'female',
            'laki-laki' => 'male',
            'laki laki' => 'male',
            'laki' => 'male',
        ];

        User::query()
            ->select(['id', 'gender'])
            ->orderBy('id')
            ->chunkById(500, function ($users) use (&$updatedCount, $map): void {
                foreach ($users as $user) {
                    $normalized = strtolower(trim((string) $user->gender));
                    $newGender = $map[$normalized] ?? $user->gender;

                    if ($newGender === $user->gender) {
                        continue;
                    }

                    User::query()
                        ->whereKey($user->id)
                        ->update([
                            'gender' => $newGender,
                        ]);

                    $updatedCount++;
                }
            });

        $this->command->info("Done. Total updated users: {$updatedCount}");
    }
}
