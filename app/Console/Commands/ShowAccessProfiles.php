<?php

namespace App\Console\Commands;

use App\Domain\Iam\Models\AccessProfile;
use Illuminate\Console\Command;
use Symfony\Component\Console\Helper\Table;

class ShowAccessProfiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'access-profile:list
                            {--active : Show only active profiles}
                            {--inactive : Show only inactive profiles}
                            {--system : Show only system profiles}
                            {--generate-identifiers : Backfill missing key_hash values for access profiles}
                            {--format=table : Output format (table, json, csv)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display all access profiles from the database';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('generate-identifiers')) {
            $this->generateMissingIdentifiers();
        }

        $query = AccessProfile::query();

        // Apply filters
        if ($this->option('active')) {
            $query->where('is_active', true);
        } elseif ($this->option('inactive')) {
            $query->where('is_active', false);
        }

        if ($this->option('system')) {
            $query->where('is_system', true);
        }

        $profiles = $query->get();

        if ($profiles->isEmpty()) {
            $this->warn('❌ No access profiles found.');
            return self::SUCCESS;
        }

        $format = $this->option('format');

        match ($format) {
            'table' => $this->displayTable($profiles),
            'json' => $this->displayJson($profiles),
            'csv' => $this->displayCsv($profiles),
            default => $this->error("Invalid format: {$format}. Supported: table, json, csv"),
        };

        $this->newLine();
        $this->info("✅ Total: {$profiles->count()} access profiles");

        return self::SUCCESS;
    }

    /**
     * Display profiles as a table
     * OPTIMIZATION: Use withCount() to load counts efficiently instead of N+1 queries
     */
    private function displayTable($profiles): void
    {
        $headers = ['ID', 'Identifier', 'Slug', 'Name', 'Description', 'Active', 'System', 'Users', 'Roles', 'Created'];
        $rows = [];

        // OPTIMIZATION: Reload profiles with counts to prevent N+1
        // This ensures we have users_count and roles_count attributes instead of querying them
        $profileIds = $profiles->pluck('id')->toArray();
        $profilesWithCounts = AccessProfile::query()
            ->whereIn('id', $profileIds)
            ->withCount('users', 'roles')
            ->get()
            ->keyBy('id');

        foreach ($profiles as $profile) {
            $profileWithCounts = $profilesWithCounts->get($profile->id);
            $usersCount = $profileWithCounts->users_count ?? 0;
            $rolesCount = $profileWithCounts->roles_count ?? 0;

            $rows[] = [
                $profile->id,
                $this->truncate($profile->key_hash ?? '-', 16),
                $profile->slug,
                $profile->name,
                $this->truncate($profile->description ?? '-', 30),
                $profile->is_active ? '✓ Yes' : '✗ No',
                $profile->is_system ? '✓ Yes' : '✗ No',
                $usersCount,
                $rolesCount,
                $profile->created_at->format('Y-m-d H:i'),
            ];
        }

        $table = new Table($this->output);
        $table->setHeaders($headers);
        $table->setRows($rows);
        $table->render();
    }

    /**
     * Display profiles as JSON
     * OPTIMIZATION: Use withCount() to load counts efficiently instead of N+1 queries
     */
    private function displayJson($profiles): void
    {
        // OPTIMIZATION: Reload profiles with counts to prevent N+1
        $profileIds = $profiles->pluck('id')->toArray();
        $profilesWithCounts = AccessProfile::query()
            ->whereIn('id', $profileIds)
            ->withCount('users', 'roles')
            ->get()
            ->keyBy('id');

        $data = $profiles->map(function ($profile) use ($profilesWithCounts) {
            $profileWithCounts = $profilesWithCounts->get($profile->id);
            return [
                'id' => $profile->id,
                'key_hash' => $profile->key_hash,
                'slug' => $profile->slug,
                'name' => $profile->name,
                'description' => $profile->description,
                'is_active' => $profile->is_active,
                'is_system' => $profile->is_system,
                'users_count' => $profileWithCounts->users_count ?? 0,
                'roles_count' => $profileWithCounts->roles_count ?? 0,
                'created_at' => $profile->created_at,
                'updated_at' => $profile->updated_at,
            ];
        });

        $this->line(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Display profiles as CSV
     * OPTIMIZATION: Use withCount() to load counts efficiently instead of N+1 queries
     */
    private function displayCsv($profiles): void
    {
        $headers = ['ID', 'Identifier', 'Slug', 'Name', 'Description', 'Active', 'System', 'Users Count', 'Roles Count', 'Created At'];
        $this->line(implode(',', $headers));

        // OPTIMIZATION: Reload profiles with counts to prevent N+1
        $profileIds = $profiles->pluck('id')->toArray();
        $profilesWithCounts = AccessProfile::query()
            ->whereIn('id', $profileIds)
            ->withCount('users', 'roles')
            ->get()
            ->keyBy('id');

        foreach ($profiles as $profile) {
            $profileWithCounts = $profilesWithCounts->get($profile->id);
            $usersCount = $profileWithCounts->users_count ?? 0;
            $rolesCount = $profileWithCounts->roles_count ?? 0;

            $row = [
                $profile->id,
                '"' . ($profile->key_hash ?? '') . '"',
                '"' . $profile->slug . '"',
                '"' . $profile->name . '"',
                '"' . ($profile->description ?? '') . '"',
                $profile->is_active ? 'Yes' : 'No',
                $profile->is_system ? 'Yes' : 'No',
                $usersCount,
                $rolesCount,
                $profile->created_at->format('Y-m-d H:i'),
            ];

            $this->line(implode(',', $row));
        }
    }

    /**
     * Generate identifiers for access profiles that still do not have one.
     */
    private function generateMissingIdentifiers(): void
    {
        $profiles = AccessProfile::query()
            ->whereNull('key_hash')
            ->orWhere('key_hash', '')
            ->orderBy('id')
            ->get();

        if ($profiles->isEmpty()) {
            $this->info('No access profiles need identifier backfill.');
            return;
        }

        $updated = 0;

        foreach ($profiles as $profile) {
            $profile->forceFill([
                'key_hash' => AccessProfile::generateKeyHash(),
            ])->save();

            $updated++;
        }

        $this->info("Generated identifiers for {$updated} access profiles.");
    }

    /**
     * Truncate string to specified length
     */
    private function truncate(string $string, int $length = 50): string
    {
        if (strlen($string) > $length) {
            return substr($string, 0, $length) . '...';
        }
        return $string;
    }
}
