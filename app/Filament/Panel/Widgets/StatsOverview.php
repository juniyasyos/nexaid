<?php

namespace App\Filament\Panel\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $statusCounts = User::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $totalUsers = $statusCounts->sum();
        $activeUsers = $statusCounts->get('active', 0);
        $inactiveUsers = $statusCounts->get('inactive', 0);
        $suspendedUsers = $statusCounts->get('suspended', 0);

        $nonActiveUsers = $inactiveUsers + $suspendedUsers;

        $activePercentage = $totalUsers > 0
            ? round(($activeUsers / $totalUsers) * 100, 1)
            : 0;

        // 📈 user baru 7 hari terakhir
        $newUsers7Days = User::where('created_at', '>=', now()->subDays(7))->count();

        // =========================
        // SESSION BASED ACTIVITY (optimized)
        // =========================
        $lifetimeSeconds = config('session.lifetime') * 60;
        $threshold = now()->subSeconds($lifetimeSeconds)->getTimestamp();

        // Count distinct users with recent activity (considered "active")
        $activeSessions = DB::table('sessions')
            ->where('last_activity', '>=', $threshold)
            ->distinct()
            ->count('user_id');

        // We no longer compute per-user expired sessions here; keep zero as fallback.
        $expiredSessions = 0;

        return [

            // =========================
            // TOTAL USER
            // =========================
            Stat::make('Total Pengguna', number_format($totalUsers))
                ->description("{$newUsers7Days} user baru (7 hari)")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info')
                ->icon('heroicon-o-user-group'),

            // =========================
            // ACTIVE USER
            // =========================
            Stat::make('User Aktif', number_format($activeUsers))
                ->description("{$activePercentage}% dari total")
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success')
                ->icon('heroicon-o-check-circle')
                ->chart([
                    $activeUsers,
                    $totalUsers - $activeUsers,
                ]),

            // =========================
            // NON ACTIVE
            // =========================
            Stat::make('Tidak Aktif', number_format($nonActiveUsers))
                ->description("Inactive: {$inactiveUsers}, Suspended: {$suspendedUsers}")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->icon('heroicon-o-no-symbol'),

            // =========================
            // LIVE ACTIVITY (SESSION)
            // =========================
            Stat::make('Sedang Online', number_format($activeSessions))
                ->description("{$expiredSessions} sesi berakhir")
                ->descriptionIcon('heroicon-m-bolt')
                ->color('warning')
                ->icon('heroicon-o-signal'),
        ];
    }
}
