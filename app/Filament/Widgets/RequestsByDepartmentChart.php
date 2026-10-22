<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use Filament\Widgets\ChartWidget;

class RequestsByDepartmentChart extends ChartWidget
{
    protected static ?string $heading = 'Service Requests by Department';

    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected static ?array $options = [
        'indexAxis' => 'y',
    ];

    protected function getData(): array
    {
        $requests = ServiceRequest::query()
            ->with('department')
            ->when(ServiceRequestResource::currentUserIsHr(), function ($query): void {
                $query->whereHas('category', function ($query): void {
                    $query->whereRaw('LOWER(code) = ?', ['id_request'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%id request%']);
                });
            })
            ->get(['metadata', 'department_id']);

        $departmentCounts = $requests
            ->map(function (ServiceRequest $request): string {
                if (filled($request->department?->name)) {
                    return $request->department->name;
                }

                $metadata = $request->metadata ?? [];

                foreach (['dept_office', 'department_office', 'id_office', 'office'] as $key) {
                    $department = trim((string) ($metadata[$key] ?? ''));

                    if ($department !== '') {
                        return $department;
                    }
                }

                return 'Not specified';
            })
            ->countBy()
            ->sortDesc();

        return [
            'datasets' => [
                [
                    'label' => 'Requests',
                    'data' => $departmentCounts->values()->all(),
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => $departmentCounts->keys()->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
