<?php

namespace App\Filament\Panel\Widgets;

use App\Domain\Iam\Models\SsoAccessLog;
use App\Domain\Iam\Models\ApplicationRole;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopRolesChart extends ChartWidget
{
    protected ?string $heading = 'Top Roles Used';
    protected static ?int $sort = 3;
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
            ->select('role_id', DB::raw('count(*) as total'))
            ->whereNotNull('role_id');

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

        $data = $query->groupBy('role_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $roleIds = $data->pluck('role_id')->toArray();
        $roles = ApplicationRole::whereIn('id', $roleIds)->pluck('name', 'id');

        return [
            'datasets' => [
                [
                    'label' => 'Total Uses',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => ['#f59e0b', '#10b981', '#3b82f6', '#ef4444', '#8b5cf6'],
                ],
            ],
            'labels' => $data->map(fn ($log) => $roles[$log->role_id] ?? 'Unknown')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
