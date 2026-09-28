<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\UserRole;
use App\Models\Organization;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $actor = Auth::user();

        $roles = collect(UserRole::cases())
            ->reject(fn (UserRole $role) => $role === UserRole::SuperAdmin && ! $actor?->isSuperAdmin())
            ->mapWithKeys(fn (UserRole $role) => [$role->value => $role->getLabel()]);

        return $schema
            ->components([
                Section::make('Account')->columns(2)->schema([
                    TextInput::make('name')->required()->maxLength(120),
                    TextInput::make('email')->email()->required()->unique(ignoreRecord: true),
                    Select::make('role')->options($roles)->default(UserRole::User->value)->required(),
                    Select::make('organization_id')
                        ->label('Organization')
                        ->options(fn () => $actor?->isSuperAdmin()
                            ? Organization::orderBy('name')->pluck('name', 'id')
                            : Organization::whereKey($actor?->organization_id)->pluck('name', 'id'))
                        ->default($actor?->organization_id)
                        ->searchable()
                        ->required(fn ($get) => $get('role') !== UserRole::SuperAdmin->value),
                    TextInput::make('password')
                        ->password()->revealable()
                        ->required(fn (string $operation) => $operation === 'create')
                        ->minLength(10)
                        ->dehydrated(fn ($state) => filled($state))
                        ->helperText('Leave empty to keep the current password.'),
                    Toggle::make('is_active')->label('Active')->default(true),
                ]),
            ]);
    }
}
