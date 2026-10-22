<?php

namespace App\Filament\Resources\ServiceRequestPriorityResource\Pages;

use App\Filament\Resources\ServiceRequestPriorityResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServiceRequestPriorities extends ListRecords
{
    protected static string $resource = ServiceRequestPriorityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
