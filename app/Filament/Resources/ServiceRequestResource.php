<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ServiceRequestResource\Pages;
use App\Models\ServiceRequest;
use App\Models\ServiceRequestCategory;
use App\Models\ServiceRequestPriority;
use App\Models\ServiceRequestStatus;
use App\Models\Department;
use App\Models\User;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section as InfolistSection;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ServiceRequestResource extends Resource
{
    protected static ?string $model = ServiceRequest::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?string $navigationGroup = 'MIS Management';

    protected static ?string $navigationLabel = 'Service Requests';

    protected static ?string $recordTitleAttribute = 'request_reference';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Request Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('request_reference')
                            ->label('Reference')
                            ->disabled()
                            ->visible(fn (string $operation): bool => $operation !== 'create'),
                        Placeholder::make('requester_name')
                            ->label('Requester')
                            ->content(fn (?ServiceRequest $record): string => $record?->requester?->name ?? 'Not set')
                            ->visible(fn (string $operation): bool => $operation !== 'create'),
                        Placeholder::make('requester_email')
                            ->label('Requester email')
                            ->content(fn (?ServiceRequest $record): string => $record?->requester?->email ?? 'Not set')
                            ->visible(fn (string $operation): bool => $operation !== 'create'),
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->disabled(fn (string $operation): bool => $operation === 'view'),
                        Textarea::make('description')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull()
                            ->disabled(fn (string $operation): bool => $operation === 'view'),
                        Select::make('service_request_category_id')
                            ->label('Category')
                            ->options(fn (): array => ServiceRequestCategory::query()
                                ->when(static::currentUserIsHr(), fn (Builder $query) => $query->where(function (Builder $query): void {
                                    $query->whereRaw('LOWER(code) = ?', ['id_request'])
                                        ->orWhereRaw('LOWER(name) LIKE ?', ['%id request%']);
                                }))
                                ->orderBy('sort_order')
                                ->pluck('name', 'id')
                                ->all())
                            ->required()
                            ->disabled(fn (string $operation): bool => $operation === 'view' || (static::currentUserIsHr() && $operation !== 'create')),
                        Select::make('service_request_priority_id')
                            ->label('Priority')
                            ->options(fn (): array => ServiceRequestPriority::query()
                                ->orderBy('sort_order')
                                ->pluck('name', 'id')
                                ->all())
                            ->required()
                            ->disabled(fn (string $operation): bool => $operation === 'view'),
                        Select::make('location_id')
                            ->relationship('location', 'name')
                            ->label('Location / Campus')
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('department_id', null))
                            ->preload()
                            ->required()
                            ->default(fn (): ?int => auth()->user()?->location_id)
                            ->disabled(fn (string $operation): bool => $operation === 'view'),
                        Select::make('department_id')
                            ->label('Department / Office')
                            ->options(fn (Get $get): array => Department::query()
                                ->where('location_id', $get('location_id'))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn (): ?int => auth()->user()?->department_id)
                            ->disabled(fn (Get $get, string $operation): bool => $operation === 'view' || ! filled($get('location_id')))
                            ->rules(fn (Get $get): array => [
                                Rule::exists('departments', 'id')
                                    ->where('location_id', $get('location_id')),
                            ]),
                        KeyValue::make('metadata')
                            ->label('Submitted Metadata (Editable)')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->columnSpanFull()
                            ->visible(fn (string $operation): bool => $operation !== 'create'),
                        Placeholder::make('attachments')
                            ->label('Attached files')
                            ->columnSpanFull()
                            ->content(function (?ServiceRequest $record): string|HtmlString {
                                if (! $record) {
                                    return 'No files are attached yet.';
                                }

                                $attachments = $record->attachments;

                                if ($attachments->isEmpty()) {
                                    return 'No files attached.';
                                }

                                $links = $attachments->map(function ($attachment): string {
                                    $url = e(Storage::disk('public')->url($attachment->file_path));
                                    $name = e($attachment->file_name);

                                    return "<a class=\"text-primary-600 underline\" href=\"{$url}\" target=\"_blank\" rel=\"noopener noreferrer\">{$name}</a>";
                                });

                                return new HtmlString($links->implode('<br>'));
                            }),
                    ]),
                Section::make('MIS Management')
                    ->columns(2)
                    ->schema([
                        Select::make('service_request_status_id')
                            ->label('Status')
                            ->relationship(
                                name: 'status',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('sort_order'),
                            )
                            ->searchable()
                            ->preload()
                            ->required()
                            ->default(fn (): ?int => ServiceRequestStatus::query()
                                ->where('slug', 'submitted')
                                ->value('id'))
                            ->disabled(fn (string $operation): bool => $operation === 'view'),
                        Select::make('assigned_staff_id')
                            ->label('Assigned Technician')
                            ->relationship(
                                name: 'assignedStaff',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn ($query) => $query
                                    ->where(fn ($query) => $query
                                        ->whereIn('role', ['admin', 'mis_staff'])
                                        ->orWhere('is_admin', true))
                                    ->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->placeholder('Unassigned')
                            ->disabled(fn (string $operation): bool => $operation === 'view'),
                        Textarea::make('resolution_summary')
                            ->label('Resolution / Remarks')
                            ->rows(4)
                            ->columnSpanFull()
                            ->disabled(fn (string $operation): bool => $operation === 'view'),
                    ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                InfolistSection::make('Request Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('request_reference')->label('Reference'),
                        TextEntry::make('requester.name')->label('Requester'),
                        TextEntry::make('requester.email')->label('Requester email'),
                        TextEntry::make('title'),
                        TextEntry::make('category.name')->label('Category'),
                        TextEntry::make('location.name')->label('Location / Campus')->placeholder('Not assigned'),
                        TextEntry::make('department.name')->label('Department / Office')->placeholder('Not assigned'),
                        TextEntry::make('priority.name')->label('Priority'),
                        TextEntry::make('description')->columnSpanFull(),
                        KeyValueEntry::make('metadata')
                            ->label('Submitted Metadata')
                            ->state(fn (ServiceRequest $record): array => collect($record->metadata ?? [])
                                ->except(['id_photo_path', 'client_signature_path', 'students', 'applicants', 'is_bulk'])
                                ->filter(fn ($value): bool => filled($value))
                                ->map(fn ($value): string => is_array($value)
                                    ? (json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '[]')
                                    : (is_scalar($value) ? (string) $value : (json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '')))
                                ->all())
                            ->columnSpanFull(),
                    ]),
                InfolistSection::make('Bulk Account Applicants')
                    ->schema([
                        RepeatableEntry::make('metadata.applicants')
                            ->label('Applicants')
                            ->columns(6)
                            ->schema([
                                TextEntry::make('id_number')->label('Student / Employee ID'),
                                TextEntry::make('full_name')->label('Full Name'),
                                TextEntry::make('personal_email')->label('Personal Email'),
                                TextEntry::make('department_office')->label('Department / Office')->placeholder('Not provided'),
                                TextEntry::make('generated_email')->label('Suggested Institutional Email'),
                                TextEntry::make('status')->label('Status')->badge(),
                            ]),
                    ])
                    ->visible(fn (ServiceRequest $record): bool => (bool) ($record->metadata['is_bulk'] ?? false)
                        && is_array($record->metadata['applicants'] ?? null)),
                InfolistSection::make('Legacy Bulk Student Accounts')
                    ->schema([
                        RepeatableEntry::make('metadata.students')
                            ->label('Students')
                            ->columns(5)
                            ->schema([
                                TextEntry::make('student_id')->label('Student ID'),
                                TextEntry::make('full_name')->label('Full Name'),
                                TextEntry::make('personal_email')->label('Personal Email'),
                                TextEntry::make('generated_email')->label('Suggested Institutional Email'),
                                TextEntry::make('status')->label('Status')->badge(),
                            ]),
                    ])
                    ->visible(fn (ServiceRequest $record): bool => (bool) ($record->metadata['is_bulk'] ?? false)
                        && is_array($record->metadata['students'] ?? null)),
                InfolistSection::make('MIS Management')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('status.name')
                            ->label('Status')
                            ->badge(),
                        TextEntry::make('assignedStaff.name')
                            ->label('Assigned Technician')
                            ->placeholder('Unassigned'),
                        TextEntry::make('resolution_summary')
                            ->label('Resolution / Remarks')
                            ->placeholder('No resolution remarks yet.')
                            ->columnSpanFull(),
                    ]),
                InfolistSection::make('ID Request Images')
                    ->columns(2)
                    ->schema([
                        ImageEntry::make('id_photo_preview')
                            ->label('2x2 ID Picture')
                            ->state(function (ServiceRequest $record): ?string {
                                $path = $record->metadata['id_photo_path'] ?? null;

                                if (! $path) {
                                    return null;
                                }

                                $cleanPath = preg_replace('#^(public/|storage/)+#', '', ltrim($path, '/'));

                                return asset('storage/'.$cleanPath);
                            })
                            ->extraImgAttributes([
                                'class' => 'h-48 w-48 object-cover rounded-xl border shadow-sm bg-slate-100',
                            ]),
                        ImageEntry::make('client_signature_preview')
                            ->label('Digital Signature')
                            ->state(function (ServiceRequest $record): ?string {
                                $path = $record->metadata['client_signature_path'] ?? null;

                                if (! $path) {
                                    return null;
                                }

                                $cleanPath = preg_replace('#^(public/|storage/)+#', '', ltrim($path, '/'));

                                return asset('storage/'.$cleanPath);
                            })
                            ->extraImgAttributes([
                                'class' => 'h-48 w-auto object-contain rounded-xl border bg-slate-50 p-2 shadow-sm',
                            ]),
                    ])
                    ->visible(fn (ServiceRequest $record): bool => $record->category?->code === 'ID_REQUEST'),
                InfolistSection::make('Actual ID Card Preview')
                    ->collapsible()
                    ->schema([
                        ViewEntry::make('id_card_preview')
                            ->view('filament.infolists.components.id-card-preview')
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (ServiceRequest $record): bool => $record->category?->code === 'ID_REQUEST'),
                InfolistSection::make('Attachments')
                    ->schema([
                        RepeatableEntry::make('attachments')
                            ->label('')
                            ->schema([
                                TextEntry::make('file_name')
                                    ->label('File')
                                    ->url(fn ($record): string => Storage::disk('public')->url($record->file_path))
                                    ->openUrlInNewTab(),
                            ]),
                    ])
                    ->visible(fn (ServiceRequest $record): bool => $record->category?->code !== 'ID_REQUEST'
                        && $record->attachments()->exists()),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('request_reference')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->limit(50)
                    ->tooltip(fn (ServiceRequest $record): string => $record->title),
                Tables\Columns\TextColumn::make('requester.name')
                    ->label('Requester')
                    ->searchable(),
                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable(),
                Tables\Columns\TextColumn::make('priority.name')
                    ->label('Priority')
                    ->badge()
                    ->color(fn (?string $state): string => match (Str::lower($state ?? '')) {
                        'critical' => 'danger',
                        'high' => 'warning',
                        'standard' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('status.name')
                    ->label('Status')
                    ->badge()
                    ->color(fn (?string $state): string => match (Str::lower($state ?? '')) {
                        'submitted' => 'info',
                        'in progress' => 'warning',
                        'resolved' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->label('Submitted')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->relationship('category', 'name')
                    ->label('Category'),
                SelectFilter::make('priority')
                    ->relationship('priority', 'name')
                    ->label('Priority'),
                SelectFilter::make('status')
                    ->relationship('status', 'name')
                    ->label('Status'),
            ])
            ->recordUrl(fn (ServiceRequest $record): string => ServiceRequestResource::getUrl('view', ['record' => $record]))
            ->defaultSort('submitted_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        if (static::currentUserIsHr()) {
            $query->whereHas('category', function (Builder $query): void {
                $query->whereRaw('LOWER(code) = ?', ['id_request'])
                    ->orWhereRaw('LOWER(name) LIKE ?', ['%id request%']);
            });
        }

        return $query->with(['requester', 'assignedStaff', 'category', 'priority', 'status', 'attachments']);
    }

    public static function canViewAny(): bool
    {
        return auth()->check();
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof ServiceRequest && static::canEditRequest($record);
    }

    public static function currentUserIsHr(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->role === 'hr' || $user->email === 'hr@capsu.edu.ph');
    }

    public static function isAcceptedOrInProgress(ServiceRequest $record): bool
    {
        return in_array(Str::lower($record->status?->name ?? ''), ['accepted', 'in progress'], true);
    }

    public static function canAcceptRequest(ServiceRequest $record): bool
    {
        return ! static::currentUserIsHr()
            && (! $record->assigned_staff_id
                || Str::lower($record->status?->name ?? '') === 'submitted');
    }

    public static function canEditRequest(ServiceRequest $record): bool
    {
        return ! static::currentUserIsHr();
    }

    public static function canAssignIdNumber(ServiceRequest $record): bool
    {
        $user = auth()->user();

        return Str::upper($record->category?->code ?? '') === 'ID_REQUEST'
            && $user instanceof User
            && ($user->is_admin
                || in_array($user->role, ['admin', 'hr'], true)
                || $user->email === 'hr@capsu.edu.ph');
    }

    public static function assignIdNumber(ServiceRequest $record, string $idNumber): void
    {
        $metadata = $record->metadata ?? [];
        $metadata['id_number'] = $idNumber;

        $record->update(['metadata' => $metadata]);

        Notification::make()
            ->title('Official ID Number assigned successfully')
            ->success()
            ->send();
    }

    public static function acceptRequest(ServiceRequest $record): void
    {
        $inProgressStatus = ServiceRequestStatus::query()
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereIn('code', ['IN_PROGRESS', 'IN-PROGRESS'])
                    ->orWhere('slug', 'in-progress')
                    ->orWhereRaw('LOWER(name) = ?', ['in progress']);
            })
            ->first();

        if (! $inProgressStatus) {
            Notification::make()
                ->title('Unable to accept request')
                ->body('The active In Progress status is missing. Seed the service request lookups and try again.')
                ->danger()
                ->send();

            return;
        }

        $record->update([
            'assigned_staff_id' => auth()->id(),
            'service_request_status_id' => $inProgressStatus->id,
        ]);

        Notification::make()
            ->title('Request accepted and assigned to you.')
            ->success()
            ->send();
    }

    public static function completeRequest(ServiceRequest $record, string $remarks): void
    {
        $resolvedStatus = ServiceRequestStatus::query()
            ->where('is_active', true)
            ->where(function (Builder $query): void {
                $query->whereIn('code', ['RESOLVED', 'COMPLETED'])
                    ->orWhereIn('slug', ['resolved', 'completed'])
                    ->orWhereRaw('LOWER(name) IN (?, ?)', ['resolved', 'completed']);
            })
            ->orderByRaw("CASE WHEN code = 'COMPLETED' OR slug = 'completed' THEN 0 ELSE 1 END")
            ->first();

        if (! $resolvedStatus) {
            Notification::make()
                ->title('Unable to complete request')
                ->body('The active Resolved or Completed status is missing. Seed the service request lookups and try again.')
                ->danger()
                ->send();

            return;
        }

        $record->update([
            'service_request_status_id' => $resolvedStatus->id,
            'resolution_summary' => $remarks,
        ]);

        Notification::make()
            ->title('Request marked as completed.')
            ->success()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListServiceRequests::route('/'),
            'create' => Pages\CreateServiceRequest::route('/create'),
            'view' => Pages\ViewServiceRequest::route('/{record}'),
            'edit' => Pages\EditServiceRequest::route('/{record}/edit'),
        ];
    }
}
