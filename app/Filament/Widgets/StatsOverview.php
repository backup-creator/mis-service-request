<?php

namespace App\Filament\Widgets;

use App\Models\MaintenanceChecklist;
use App\Models\ServiceRequest;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        $resolvedRequests = ServiceRequest::query()
            ->whereHas('status', fn ($query) => $query->where('slug', 'resolved'))
            ->whereNotNull('updated_at')
            ->get(['created_at', 'updated_at', 'assigned_staff_id']);

        $resolutionHours = fn (ServiceRequest $request): float => $request->created_at
            ->diffInSeconds($request->updated_at) / 3600;
        $averageHours = $resolvedRequests->isEmpty()
            ? 0
            : $resolvedRequests->avg($resolutionHours);
        $technicianRequests = $resolvedRequests->whereNotNull('assigned_staff_id');
        $technicianSlaRate = $technicianRequests->isEmpty()
            ? 0
            : ($technicianRequests->filter(fn (ServiceRequest $request): bool => $resolutionHours($request) <= 8)->count()
                / $technicianRequests->count()) * 100;

        return [
            Stat::make('Total Service Requests', ServiceRequest::query()->count())
                ->description('All recorded requests')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary'),
            Stat::make(
                'Pending Requests',
                ServiceRequest::query()
                    ->whereHas('status', fn ($query) => $query->whereIn('slug', ['submitted', 'pending']))
                    ->count(),
            )
                ->description('Awaiting staff review')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            Stat::make('Preventive Maintenance', MaintenanceChecklist::query()->count())
                ->description('Total checklists submitted')
                ->descriptionIcon('heroicon-m-clipboard-document-check')
                ->color('success'),
            Stat::make(
                'Resolved Today',
                ServiceRequest::query()
                    ->whereHas('status', fn ($query) => $query->where('slug', 'resolved'))
                    ->whereDate('updated_at', now())
                    ->count(),
            )
                ->description('Requests completed today')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
            Stat::make('Average Resolution Time', number_format($averageHours, 1).' Hours')
                ->description('From creation to resolution')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('success'),
            Stat::make('Technician SLA Attainment Rate', number_format($technicianSlaRate, 1).'%')
                ->description('Assigned requests resolved within 8 hours')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color($technicianSlaRate >= 90 ? 'success' : 'warning'),
        ];
    }
}
