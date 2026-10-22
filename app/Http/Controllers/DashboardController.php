<?php

namespace App\Http\Controllers;

use App\Models\ServiceRequest;
use App\Models\ServiceRequestCategory;
use App\Models\ServiceRequestPriority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $userId = Auth::id();
        $requestQuery = ServiceRequest::query()->where('requester_id', $userId);

        $statusCounts = (clone $requestQuery)
            ->leftJoin('service_request_statuses', 'service_requests.service_request_status_id', '=', 'service_request_statuses.id')
            ->selectRaw('LOWER(COALESCE(service_request_statuses.slug, service_request_statuses.name, \'\')) as status_key')
            ->selectRaw('COUNT(*) as aggregate')
            ->groupBy('status_key')
            ->pluck('aggregate', 'status_key');

        $countStatuses = static fn (array $statuses): int => $statusCounts
            ->filter(fn ($count, string $status): bool => in_array(str_replace(' ', '-', $status), $statuses, true))
            ->sum();

        $recentRequests = (clone $requestQuery)
            ->with(['category:id,name,code', 'priority:id,name', 'status:id,name,slug', 'assignedStaff:id,name', 'attachments'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function (ServiceRequest $serviceRequest): array {
                $metadata = $serviceRequest->metadata ?? [];
                $metadata['id_photo_url'] = filled($metadata['id_photo_path'] ?? null)
                    ? Storage::disk('public')->url($metadata['id_photo_path'])
                    : null;
                $metadata['client_signature_url'] = filled($metadata['client_signature_path'] ?? null)
                    ? Storage::disk('public')->url($metadata['client_signature_path'])
                    : null;

                return [
                    'id' => $serviceRequest->id,
                    'request_reference' => $serviceRequest->request_reference,
                    'title' => $serviceRequest->title,
                    'description' => $serviceRequest->description,
                    'created_at' => $serviceRequest->created_at,
                    'submitted_at' => $serviceRequest->submitted_at,
                    'category' => [
                        'name' => $serviceRequest->category?->name,
                        'code' => $serviceRequest->category?->code,
                    ],
                    'priority' => $serviceRequest->priority?->name,
                    'status' => $serviceRequest->status?->name ?? 'Submitted',
                    'status_slug' => $serviceRequest->status?->slug ?? 'submitted',
                    'assigned_staff' => $serviceRequest->assignedStaff?->name,
                    'resolution_summary' => $serviceRequest->resolution_summary,
                    'contact_number' => $metadata['contact_number'] ?? $metadata['id_emergency_phone'] ?? null,
                    'subtype' => $metadata['subtype'] ?? $metadata['id_request_type'] ?? null,
                    'metadata' => $metadata,
                    'attachments' => $serviceRequest->attachments
                        ->map(fn ($attachment): array => [
                            'id' => $attachment->id,
                            'file_name' => $attachment->file_name,
                            'url' => Storage::disk('public')->url($attachment->file_path),
                            'mime_type' => $attachment->mime_type,
                            'size_bytes' => $attachment->size_bytes,
                        ])
                        ->all(),
                ];
            });

        return Inertia::render('Dashboard', [
            'recentRequests' => $recentRequests,
            'categories' => ServiceRequestCategory::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'code', 'description']),
            'priorities' => ServiceRequestPriority::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name']),
            'openCreateModal' => $request->boolean('createRequest'),
            'success' => session('success'),
            'stats' => [
                'total' => (clone $requestQuery)->count(),
                'pending' => $countStatuses(['submitted', 'pending']),
                'inProgress' => $countStatuses(['accepted', 'in-progress', 'processing']),
                'completed' => $countStatuses(['completed', 'closed', 'ready', 'ready-for-printing', 'resolved']),
            ],
        ]);
    }
}
