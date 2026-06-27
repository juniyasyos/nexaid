<?php

namespace App\Filament\Panel\Pages;

// use App\Filament\Panel\Widgets\AccessProfilesChart;
use App\Filament\Panel\Widgets\ApplicationAccessSummary;
use App\Filament\Panel\Widgets\ProfileRoleConfigurationMap;
use App\Filament\Panel\Widgets\StatsOverview;
use App\Filament\Panel\Widgets\UserAssignmentCoverageStatus;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?int $navigationSort = 0;

    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
            UserAssignmentCoverageStatus::class,
            ProfileRoleConfigurationMap::class,
            // AccessProfilesChart::class,
            ApplicationAccessSummary::class,
        ];
    }

    public static function getNavigationItems(): array
    {
        return []; // Hide from navigation - access via "/" path
    }
}
