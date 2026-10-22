<?php

namespace App\Filament\Resources\ServiceRequestStatusResource\Pages;

use App\Filament\Resources\ServiceRequestStatusResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServiceRequestStatuses extends ListRecords
{
    protected static string $resource = ServiceRequestStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
