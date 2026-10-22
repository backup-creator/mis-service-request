<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MaintenanceChecklistResource\Pages;
use App\Models\Department;
use App\Models\MaintenanceChecklist;
use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class MaintenanceChecklistResource extends Resource
{
    protected static ?string $model = MaintenanceChecklist::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationLabel = 'Preventive Maintenance';

    protected static ?string $modelLabel = 'Maintenance Checklist';

    protected static ?string $navigationGroup = 'Operations & Maintenance';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && ($user->is_admin || in_array($user->role, ['admin', 'mis', 'mis_staff', 'technician'], true));
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDeleteAny(): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        $checklistItems = [
            'system_boot' => [
                'label' => 'System Boot',
                'checks' => [
                    'cold_start' => 'Boot system from a cold start. Monitor for errors and speed of entire boot process.',
                ],
            ],
            'system_log_in' => [
                'label' => 'System Log-in',
                'checks' => ['login_errors' => 'Monitor for errors.'],
            ],
            'network_settings' => [
                'label' => 'Network Settings',
                'checks' => [
                    'tcp_ip' => 'TCP/IP is correct',
                    'domain_name' => 'Domain Name',
                    'security_settings' => 'Security Settings',
                    'client_configurations' => 'Client Configurations',
                    'computer_name' => 'Computer Name',
                ],
            ],
            'computer_hardware_settings' => [
                'label' => 'Computer Hardware Settings',
                'checks' => [
                    'device_manager' => 'Verify Device Manager Settings',
                    'bios' => 'BIOS up-to-date',
                    'hard_disk' => 'Hard Disk',
                    'drive_firmware' => 'DVD or CD/RW-drive firmware up-to-date',
                    'memory' => 'Memory is OK',
                    'battery_runtime' => '(Laptop) Battery run-time is normal',
                ],
            ],
            'browser_proxy_settings' => [
                'label' => 'Browser/Proxy Settings',
                'checks' => ['settings_operation' => 'Verify proper settings and operation'],
            ],
            'proper_software_loads' => [
                'label' => 'Proper Software loads',
                'checks' => [
                    'required_software' => 'Required software is installed and operating',
                    'windows_update' => 'Windows Update',
                    'disk_defragmenter' => 'Run Disk Defragmenter',
                    'backup_data' => 'Back-up Data',
                ],
            ],
            'viruses_and_malware' => [
                'label' => 'Viruses and Malware',
                'checks' => [
                    'antivirus_installed' => 'Antivirus installed',
                    'scan_disk' => 'Run Scan Disk / Check Disk',
                ],
            ],
            'clearance' => [
                'label' => 'Clearance',
                'checks' => [
                    'unused_software' => 'Unused software removed',
                    'disk_cleanup' => 'Run Disk clean up',
                    'temporary_files' => 'Temporary files removed',
                    'recycle_bin_caches' => 'Recycle Bin and Caches emptied',
                    'periphery_devices' => 'Periphery devices clean',
                ],
            ],
            'interiors_and_cleaning' => [
                'label' => 'Interiors and Cleaning',
                'checks' => [
                    'dust_removed' => 'Dust removed',
                    'loose_parts' => 'No loose parts',
                    'airflow' => 'Airflow is OK',
                    'cables' => 'Cables unplugged and re-plugged',
                    'fans' => 'Fans are operating',
                ],
            ],
            'peripheral_devices' => [
                'label' => 'Peripheral devices',
                'checks' => [
                    'mouse' => 'Mouse',
                    'monitor' => 'Monitor',
                    'ups' => 'UPS',
                    'printer' => 'Printer',
                ],
            ],
        ];

        return $form
            ->schema([
                Section::make('Header Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('pc_name')
                            ->label('PC/Laptop Name')
                            ->prefixIcon('heroicon-m-computer-desktop')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('serial_no')
                            ->label('Serial No.')
                            ->prefixIcon('heroicon-m-qr-code')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('end_user')
                            ->prefixIcon('heroicon-m-user')
                            ->required()
                            ->maxLength(255),
                        Select::make('location_id')
                            ->relationship('location', 'name')
                            ->label('Location / Campus')
                            ->live()
                            ->afterStateUpdated(fn (Set $set): mixed => $set('department_id', null))
                            ->preload()
                            ->required()
                            ->default(fn (): ?int => auth()->user()?->location_id)
                            ->prefixIcon('heroicon-m-map-pin'),
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
                            ->disabled(fn (Get $get): bool => ! filled($get('location_id')))
                            ->rules(fn (Get $get): array => [
                                Rule::exists('departments', 'id')
                                    ->where('location_id', $get('location_id')),
                            ]),
                        Hidden::make('office'),
                        Select::make('technician_id')
                            ->label('Technician')
                            ->prefixIcon('heroicon-m-wrench-screwdriver')
                            ->options(fn (): array => User::query()
                                ->whereIn('role', ['mis', 'mis_staff', 'technician'])
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->nullable(),
                        DatePicker::make('scheduled_at')
                            ->label('Date')
                            ->required()
                            ->native(false),
                    ]),
                Section::make('Checklist Items')
                    ->description('MIS-F03: mark each check as OK or Repair and add remarks as needed.')
                    ->schema([
                        ...collect($checklistItems)
                            ->map(fn (array $task, string $key): Fieldset => Fieldset::make($task['label'])
                                ->schema([
                                    ...collect($task['checks'])
                                        ->map(fn (string $label, string $checkKey): Grid => Grid::make(12)
                                            ->schema([
                                                Placeholder::make("task_{$key}_{$checkKey}")
                                                    ->content($label)
                                                    ->hiddenLabel()
                                                    ->columnSpan(5),
                                                Radio::make("checklist_data.{$key}.{$checkKey}.status")
                                                    ->label('Status')
                                                    ->options([
                                                        'OK' => 'OK',
                                                        'Repair' => 'Repair',
                                                        'N/A' => 'N/A',
                                                    ])
                                                    ->inline()
                                                    ->hiddenLabel()
                                                    ->required()
                                                    ->columnSpan(3),
                                                TextInput::make("checklist_data.{$key}.{$checkKey}.remarks")
                                                    ->hiddenLabel()
                                                    ->placeholder('Optional remarks...')
                                                    ->maxLength(1000)
                                                    ->columnSpan(4),
                                            ]))
                                        ->all(),
                                ]))
                            ->all(),
                        Textarea::make('other')
                            ->label('Other')
                            ->rows(2)
                            ->maxLength(2000),
                    ]),
                Section::make('Sign-off & Notes')
                    ->columns(2)
                    ->schema([
                        Textarea::make('notes')
                            ->label('Overall Notes')
                            ->rows(3)
                            ->maxLength(5000),
                        TextInput::make('technician_signature')
                            ->label("Technician's Signature")
                            ->maxLength(255),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pc_name')
                    ->label('PC/Laptop Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('serial_no')
                    ->label('Serial No.')
                    ->searchable(),
                TextColumn::make('end_user')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location.name')
                    ->label('Location / Campus')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('department.name')
                    ->label('Dept./Office')
                    ->placeholder(fn (MaintenanceChecklist $record): string => $record->office)
                    ->searchable(),
                TextColumn::make('technician.name')
                    ->label('Technician')
                    ->placeholder('Not assigned')
                    ->searchable(),
                TextColumn::make('scheduled_at')
                    ->date()
                    ->sortable(),
            ])
            ->defaultSort('scheduled_at', 'desc')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMaintenanceChecklists::route('/'),
            'create' => Pages\CreateMaintenanceChecklist::route('/create'),
            'edit' => Pages\EditMaintenanceChecklist::route('/{record}/edit'),
        ];
    }
}
