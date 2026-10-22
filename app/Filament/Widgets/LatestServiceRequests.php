<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ServiceRequestResource;
use App\Models\ServiceRequest;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestServiceRequests extends BaseWidget
{
    protected static ?string $heading = 'Latest Service Requests';

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns($this->getTableColumns())
            ->recordUrl($this->getTableRecordUrlUsing())
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalWidth('4xl')
                    ->form([
                        TextInput::make('request_reference')
                            ->label('Reference')
                            ->disabled(),
                        TextInput::make('title')
                            ->disabled(),
                        Placeholder::make('requester')
                            ->content(fn (ServiceRequest $record): string => $record->requester?->name ?? 'Unknown'),
                        Placeholder::make('category')
                            ->content(fn (ServiceRequest $record): string => $record->category?->name ?? 'Unknown'),
                        Placeholder::make('priority')
                            ->content(fn (ServiceRequest $record): string => $record->priority?->name ?? 'Unknown'),
                        Placeholder::make('status')
                            ->content(fn (ServiceRequest $record): string => $record->status?->name ?? 'Unknown'),
                        Textarea::make('description')
                            ->rows(4)
                            ->disabled()
                            ->columnSpanFull(),
                        Textarea::make('resolution_summary')
                            ->label('Resolution / Remarks')
                            ->rows(3)
                            ->disabled()
                            ->columnSpanFull(),
                    ]),
            ])
            ->defaultPaginationPageOption(5)
            ->paginationPageOptions([5, 10, 25]);
    }

    protected function getTableQuery(): Builder
    {
        return ServiceRequest::query()
            ->with(['requester', 'category', 'priority', 'status'])
            ->when(ServiceRequestResource::currentUserIsHr(), function (Builder $query): void {
                $query->whereHas('category', function (Builder $query): void {
                    $query->whereRaw('LOWER(code) = ?', ['id_request'])
                        ->orWhereRaw('LOWER(name) LIKE ?', ['%id request%']);
                });
            })
            ->latest('submitted_at');
    }

    protected function getTableColumns(): array
    {
        return [
            TextColumn::make('request_reference')
                ->label('Reference')
                ->searchable(),
            TextColumn::make('title')
                ->limit(45)
                ->searchable(),
            TextColumn::make('category.name')
                ->label('Category')
                ->searchable(),
            TextColumn::make('status.name')
                ->label('Status')
                ->badge()
                ->placeholder('Unknown'),
            TextColumn::make('submitted_at')
                ->label('Submitted')
                ->dateTime()
                ->sortable(),
        ];
    }

    protected function getTableRecordUrlUsing(): ?\Closure
    {
        return fn (ServiceRequest $record): string => ServiceRequestResource::getUrl('view', ['record' => $record]);
    }

    protected function getTableRecordsPerPageSelectOptions(): array
    {
        return [5, 10, 25];
    }

    public function getDefaultTableRecordsPerPageSelectOption(): int
    {
        return 5;
    }
}
