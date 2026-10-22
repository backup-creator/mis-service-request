<?php

namespace App\Filament\Resources\MaintenanceChecklistResource\Pages;

use App\Filament\Resources\MaintenanceChecklistResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListMaintenanceChecklists extends ListRecords
{
    protected static string $resource = MaintenanceChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
