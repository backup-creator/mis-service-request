<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use Filament\Widgets\ChartWidget;

class RequestsPerMonthChart extends ChartWidget
{
    protected static ?string $heading = 'Monthly Service Request Trend';

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public ?string $filter = 'this_year';

    protected function getFilters(): ?array
    {
        return [
            'this_year' => 'This Year',
            'last_year' => 'Last Year',
        ];
    }

    protected function getData(): array
    {
        $year = (int) now()->format('Y') - (($this->filter ?? 'this_year') === 'last_year' ? 1 : 0);
        $firstMonth = now()->setDate($year, 1, 1)->startOfMonth();
        $labels = [];
        $counts = [];

        for ($month = $firstMonth->copy(); $month->year === $year; $month->addMonth()) {
            $labels[] = $month->format('M Y');

            $query = ServiceRequest::query()
                ->whereBetween('submitted_at', [
                    $month->copy()->startOfMonth(),
                    $month->copy()->endOfMonth(),
                ]);

            if (ServiceRequestResource::currentUserIsHr()) {
                $query->whereHas('category', function ($query): void {
                    $query->whereRaw('LOWER(code) = ?', ['id_request'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%id request%']);
                });
            }

            $counts[] = $query->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Requests',
                    'data' => $counts,
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.18)',
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
