<?php

namespace App\Filament\Panel\Widgets;

use App\Domain\Iam\Models\SsoAccessLog;
use App\Domain\Iam\Models\AccessProfile;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopAccessProfilesChart extends ChartWidget
{
    protected ?string $heading = 'Top Access Profiles';
    protected static ?int $sort = 2;
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];
    public ?string $filter = 'month';

    protected function getFilters(): ?array
    {
        return [
            'today' => 'Hari Ini',
            'week' => '7 Hari Terakhir',
            'month' => '30 Hari Terakhir',
            'year' => 'Tahun Ini',
            'all' => 'Semua Waktu',
        ];
    }

    protected function getData(): array
    {
        $query = SsoAccessLog::query()
            ->select('access_profile_id', DB::raw('count(*) as total'))
            ->whereNotNull('access_profile_id');

        if ($this->filter !== 'all') {
            $startDate = match ($this->filter) {
                'today' => now()->startOfDay(),
                'week' => now()->subDays(7)->startOfDay(),
                'month' => now()->subDays(30)->startOfDay(),
                'year' => now()->startOfYear(),
                default => now()->subDays(30)->startOfDay(),
            };
            $query->where('accessed_at', '>=', $startDate);
        }

        $data = $query->groupBy('access_profile_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $profileIds = $data->pluck('access_profile_id')->toArray();
        $profiles = AccessProfile::whereIn('id', $profileIds)->pluck('name', 'id');

        return [
            'datasets' => [
                [
                    'label' => 'Total Logins',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => ['#8b5cf6', '#ec4899', '#f43f5e', '#3b82f6', '#10b981'],
                ],
            ],
            'labels' => $data->map(fn ($log) => $profiles[$log->access_profile_id] ?? 'Unknown')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
