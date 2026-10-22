<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ServiceRequestResource;
use App\Models\ServiceRequestPriority;
use Filament\Widgets\ChartWidget;

class RequestsByPriorityChart extends ChartWidget
{
    protected static ?string $heading = 'Service Requests by Priority';

    protected static ?int $sort = 4;

    protected int | string | array $columnSpan = 1;

    protected function getData(): array
    {
        $priorities = ServiceRequestPriority::query()
            ->withCount([
                'serviceRequests' => function ($query): void {
                    if (ServiceRequestResource::currentUserIsHr()) {
                        $query->whereHas('category', function ($query): void {
                            $query->whereRaw('LOWER(code) = ?', ['id_request'])
                                ->orWhereRaw('LOWER(name) LIKE ?', ['%id request%']);
                        });
                    }
                },
            ])
            ->orderBy('sort_order')
            ->get();

        $colors = [
            'urgent' => '#ef4444',
            'high' => '#f97316',
            'medium' => '#eab308',
            'normal' => '#eab308',
            'low' => '#64748b',
        ];

        return [
            'datasets' => [
                [
                    'label' => 'Requests',
                    'data' => $priorities->pluck('service_requests_count')->all(),
                    'backgroundColor' => $priorities
                        ->map(fn (ServiceRequestPriority $priority): string => $colors[strtolower($priority->name)] ?? '#94a3b8')
                        ->all(),
                ],
            ],
            'labels' => $priorities->pluck('name')->all(),
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
