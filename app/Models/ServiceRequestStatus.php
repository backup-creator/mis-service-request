<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ServiceRequestStatus extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'code',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
