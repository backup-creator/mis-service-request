<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Department;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'User Management';

    protected static ?string $navigationLabel = 'Users';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Select::make('location_id')
                    ->relationship('location', 'name')
                    ->label('Location / Campus')
                    ->live()
                    ->afterStateUpdated(fn (Set $set): mixed => $set('department_id', null))
                    ->searchable()
                    ->preload()
                    ->nullable(),
                Select::make('department_id')
                    ->label('Department / Office')
                    ->options(fn (Get $get): array => Department::query()
                        ->where('location_id', $get('location_id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->disabled(fn (Get $get): bool => ! filled($get('location_id')))
                    ->rules(fn (Get $get): array => filled($get('location_id')) ? [
                        Rule::exists('departments', 'id')->where('location_id', $get('location_id')),
                    ] : ['nullable']),
                Select::make('role')
                    ->options([
                        'user' => 'User',
                        'mis' => 'MIS',
                        'mis_staff' => 'MIS Staff',
                        'technician' => 'Technician',
                        'hr' => 'HR',
                        'admin' => 'Administrator',
                    ])
                    ->required()
                    ->default('user')
                    ->native(false),
                TextInput::make('password')
                    ->password()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrateStateUsing(fn (string $state): string => Hash::make($state))
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255)
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave blank to keep the current password.' : null),
                Forms\Components\Toggle::make('is_admin')
                    ->default(false)
                    ->label('Administrator access'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('location.name')
                    ->label('Location / Campus')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('department.name')
                    ->label('Department / Office')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('role')
                    ->badge(),
                IconColumn::make('is_admin')->boolean()->label('Administrator'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return static::currentUserCanManageUsers();
    }

    public static function canCreate(): bool
    {
        return static::currentUserCanManageUsers();
    }

    public static function canEdit(Model $record): bool
    {
        return static::currentUserCanManageUsers();
    }

    public static function canDelete(Model $record): bool
    {
        return static::currentUserCanManageUsers();
    }

    public static function canDeleteAny(): bool
    {
        return static::currentUserCanManageUsers();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::currentUserCanManageUsers();
    }

    private static function currentUserCanManageUsers(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->role !== 'hr'
            && $user->email !== 'hr@capsu.edu.ph'
            && ($user->is_admin || $user->role === 'admin');
    }
}
