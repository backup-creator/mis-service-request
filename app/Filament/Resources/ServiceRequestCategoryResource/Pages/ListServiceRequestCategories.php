<?php

namespace App\Filament\Resources\ServiceRequestCategoryResource\Pages;

use App\Filament\Resources\ServiceRequestCategoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListServiceRequestCategories extends ListRecords
{
    protected static string $resource = ServiceRequestCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
