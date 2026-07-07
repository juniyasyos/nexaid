<?php

namespace App\Filament\Panel\Widgets;

use App\Domain\Iam\Models\SsoAccessLog;
use App\Domain\Iam\Models\Application;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class TopApplicationsChart extends ChartWidget
{
    protected static ?string $heading = 'Top Applications Accessed';
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];

    protected function getData(): array
    {
        $data = SsoAccessLog::query()
            ->select('application_id', DB::raw('count(*) as total'))
            ->groupBy('application_id')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $appIds = $data->pluck('application_id')->toArray();
        $apps = Application::whereIn('id', $appIds)->pluck('name', 'id');

        return [
            'datasets' => [
                [
                    'label' => 'Total Accesses',
                    'data' => $data->pluck('total')->toArray(),
                    'backgroundColor' => ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'],
                ],
            ],
            'labels' => $data->map(fn ($log) => $apps[$log->application_id] ?? 'Unknown')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
