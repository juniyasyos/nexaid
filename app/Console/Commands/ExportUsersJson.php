<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class ExportUsersJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:export-json {--path=exports/users.json}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export all user fields to a JSON file';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $query = User::query()
            ->with([
                'accessProfiles',
                'unitKerjas' => static function ($relation) {
                    $relation->withTrashed()->orderBy('unit_name');
                },
            ])
            ->orderBy('id');

        if (in_array(SoftDeletes::class, class_uses_recursive(User::class), true)) {
            $query->withTrashed();
        }

        $path = $this->option('path') ?: 'exports/users.json';
        $users = [];
        $totalUsers = 0;

        // OPTIMIZATION: Use chunking to avoid loading entire users table into memory
        $query->chunk(500, function ($chunk) use (&$users, &$totalUsers) {
            foreach ($chunk as $user) {
                $users[] = array_merge($user->getAttributes(), [
                    'accessProfiles' => $user->accessProfiles->pluck('slug')->values()->all(),
                    'unit_kerjas' => $user->unitKerjas->map(static function ($unitKerja): array {
                        return [
                            'id' => $unitKerja->id,
                            'unit_name' => $unitKerja->unit_name,
                            'slug' => $unitKerja->slug,
                        ];
                    })->values()->all(),
                ]);
                $totalUsers++;
            }
        });

        $payload = json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            $this->error('Failed to encode JSON: ' . json_last_error_msg());

            return self::FAILURE;
        }

        Storage::disk('local')->put($path, $payload);

        $this->info('Users exported: storage/app/' . $path);
        $this->info('Total users: ' . $totalUsers);

        return self::SUCCESS;
    }
}
