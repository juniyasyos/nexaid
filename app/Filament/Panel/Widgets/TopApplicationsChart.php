<?php

namespace App\Filament\Panel\Widgets;

use App\Domain\Iam\Models\SsoAccessLog;
use App\Domain\Iam\Models\Application;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

class TopApplicationsChart extends ChartWidget
{
    protected ?string $heading = 'Akses Aplikasi per Hari (Top 5)';
    protected static ?int $sort = 1;
    protected int | string | array $columnSpan = [
        'md' => 1,
        'xl' => 1,
    ];
    public ?string $filter = 'month';

    protected function getFilters(): ?array
    {
        return [
            'week' => '7 Hari Terakhir',
            'month' => '30 Hari Terakhir',
            'year' => 'Tahun Ini',
        ];
    }

    protected function getData(): array
    {
        $startDate = match ($this->filter) {
            'week' => now()->subDays(7)->startOfDay(),
            'month' => now()->subDays(30)->startOfDay(),
            'year' => now()->startOfYear(),
            default => now()->subDays(30)->startOfDay(),
        };

        // Ambil 5 aplikasi terpopuler dalam periode ini
        $topApps = SsoAccessLog::query()
            ->select('application_id', DB::raw('count(*) as total'))
            ->where('accessed_at', '>=', $startDate)
            ->groupBy('application_id')
            ->orderByDesc('total')
            ->limit(5)
            ->pluck('application_id');

        if ($topApps->isEmpty()) {
            return ['datasets' => [], 'labels' => []];
        }

        // Ambil data harian untuk ke-5 aplikasi tersebut
        $dailyData = SsoAccessLog::query()
            ->select('application_id', DB::raw('DATE(accessed_at) as date'), DB::raw('count(*) as total'))
            ->where('accessed_at', '>=', $startDate)
            ->whereIn('application_id', $topApps)
            ->groupBy('application_id', 'date')
            ->get();

        $appNames = Application::whereIn('id', $topApps)->pluck('name', 'id');
        
        // Buat rentang tanggal (X-Axis)
        $dates = [];
        $current = $startDate->copy();
        $end = now()->endOfDay();
        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->addDay();
        }

        $datasets = [];
        $colors = ['#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6'];
        
        foreach ($topApps as $index => $appId) {
            $appData = [];
            foreach ($dates as $date) {
                $count = $dailyData->where('application_id', $appId)->where('date', $date)->first();
                $appData[] = $count ? $count->total : 0;
            }
            
            $datasets[] = [
                'label' => $appNames[$appId] ?? 'Unknown',
                'data' => $appData,
                'borderColor' => $colors[$index % count($colors)],
                'fill' => false,
                'tension' => 0.3,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => array_map(fn($d) => Carbon::parse($d)->format('d M'), $dates),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
