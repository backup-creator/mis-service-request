<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class MaintenanceChecklist extends Model
{
    protected $fillable = [
        'pc_name',
        'serial_no',
        'end_user',
        'office',
        'location_id',
        'department_id',
        'technician_id',
        'scheduled_at',
        'checklist_data',
        'notes',
        'other',
        'technician_signature',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'date',
            'checklist_data' => 'array',
        ];
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    protected static function booted(): void
    {
        static::saving(function (self $checklist): void {
            if (! $checklist->location_id && ! $checklist->department_id) {
                return;
            }

            $department = Department::query()
                ->whereKey($checklist->department_id)
                ->where('location_id', $checklist->location_id)
                ->first();

            if (! $department) {
                throw ValidationException::withMessages([
                    'department_id' => 'Select a department that belongs to the chosen location.',
                ]);
            }

            $checklist->office = $department->name;
        });
    }
}
