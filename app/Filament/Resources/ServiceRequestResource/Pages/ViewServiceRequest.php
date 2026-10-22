<?php

namespace App\Filament\Resources\ServiceRequestResource\Pages;

use App\Filament\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use Filament\Actions;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;

class ViewServiceRequest extends ViewRecord
{
    protected static string $resource = ServiceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('assignIdNumber')
                ->label('Assign ID Number')
                ->icon('heroicon-o-identification')
                ->color('success')
                ->visible(fn (): bool => ServiceRequestResource::canAssignIdNumber($this->getRecord()))
                ->form([
                    TextInput::make('id_number')
                        ->label('Official Employee ID Number')
                        ->required()
                        ->placeholder('e.g., 2024-3030102')
                        ->default(fn (ServiceRequest $record): string => $record->metadata['id_number'] ?? ''),
                ])
                ->action(fn (ServiceRequest $record, array $data) => ServiceRequestResource::assignIdNumber($record, $data['id_number'])),
            Action::make('accept')
                ->label('Accept Request')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => ServiceRequestResource::canAcceptRequest($this->getRecord()))
                ->action(fn (ServiceRequest $record) => ServiceRequestResource::acceptRequest($record)),
            Action::make('complete')
                ->label('Mark as Completed')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => ServiceRequestResource::canEditRequest($this->getRecord())
                    && ServiceRequestResource::isAcceptedOrInProgress($this->getRecord()))
                ->form([
                    Textarea::make('remarks')
                        ->label('Resolution Remarks')
                        ->required()
                        ->rows(4),
                ])
                ->action(fn (ServiceRequest $record, array $data) => ServiceRequestResource::completeRequest($record, $data['remarks'])),
            Actions\EditAction::make()
                ->visible(fn (): bool => ServiceRequestResource::canEditRequest($this->getRecord())),
        ];
    }
}
