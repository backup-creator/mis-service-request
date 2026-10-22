<?php

namespace App\Filament\Resources\ServiceRequestResource\Pages;

use App\Filament\Resources\ServiceRequestResource;
use App\Models\ServiceRequestStatus;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateServiceRequest extends CreateRecord
{
    protected static string $resource = ServiceRequestResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['requester_id'] = auth()->id();
        $data['request_reference'] = 'SR-'.now()->format('Y').'-'.Str::upper(Str::random(8));
        $data['submitted_at'] = now();
        $data['service_request_status_id'] ??= ServiceRequestStatus::query()
            ->where('slug', 'submitted')
            ->value('id');

        return $data;
    }
}
