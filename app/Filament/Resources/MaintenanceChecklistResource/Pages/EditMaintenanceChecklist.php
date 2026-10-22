<?php

namespace App\Filament\Resources\MaintenanceChecklistResource\Pages;

use App\Filament\Resources\MaintenanceChecklistResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMaintenanceChecklist extends EditRecord
{
    protected static string $resource = MaintenanceChecklistResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
