<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ServiceRequest extends Model
{
    protected $fillable = [
        'request_reference',
        'requester_id',
        'assigned_staff_id',
        'location_id',
        'department_id',
        'service_request_category_id',
        'service_request_priority_id',
        'service_request_status_id',
        'title',
        'description',
        'resolution_summary',
        'submitted_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceRequestCategory::class, 'service_request_category_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(ServiceRequestPriority::class, 'service_request_priority_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(ServiceRequestStatus::class, 'service_request_status_id');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(ServiceRequestAttachment::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $serviceRequest): void {
            if (! $serviceRequest->location_id && ! $serviceRequest->department_id) {
                return;
            }

            $department = Department::query()
                ->whereKey($serviceRequest->department_id)
                ->where('location_id', $serviceRequest->location_id)
                ->first();

            if (! $department) {
                throw ValidationException::withMessages([
                    'department_id' => 'Select a department that belongs to the chosen location.',
                ]);
            }
        });
    }
}
