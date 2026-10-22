<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ServiceRequestResource;
use App\Models\ServiceRequestCategory;
use Filament\Widgets\ChartWidget;

class RequestsByCategoryChart extends ChartWidget
{
    protected static ?string $heading = 'Service Requests by Category';

    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $categories = ServiceRequestCategory::query()
            ->withCount('serviceRequests')
            ->when(ServiceRequestResource::currentUserIsHr(), function ($query): void {
                $query->where(function ($query): void {
                    $query->whereRaw('LOWER(code) = ?', ['id_request'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%id request%']);
                });
            })
            ->orderBy('sort_order')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Requests',
                    'data' => $categories->pluck('service_requests_count')->all(),
                    'backgroundColor' => [
                        '#f59e0b',
                        '#3b82f6',
                        '#10b981',
                        '#8b5cf6',
                        '#ef4444',
                        '#06b6d4',
                        '#f97316',
                        '#64748b',
                    ],
                ],
            ],
            'labels' => $categories->pluck('name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => ['display' => false],
                'y' => ['display' => false],
            ],
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
