<?php

namespace App\Filament\Resources\ServiceRequestCategoryResource\Pages;

use App\Filament\Resources\ServiceRequestCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditServiceRequestCategory extends EditRecord
{
    protected static string $resource = ServiceRequestCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
