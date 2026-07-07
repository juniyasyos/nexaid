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

    protected function getData(): array
    {
        $data = SsoAccessLog::query()
            ->select('role_id', DB::raw('count(*) as total'))
            ->whereNotNull('role_id')
            ->groupBy('role_id')
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
