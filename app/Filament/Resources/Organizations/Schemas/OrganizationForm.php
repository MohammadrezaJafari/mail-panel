<?php

namespace App\Filament\Resources\Organizations\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class OrganizationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Organization')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(120)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($set, $state, $get) => $get('slug') ?: $set('slug', Str::slug($state))),
                        TextInput::make('slug')
                            ->required()
                            ->alphaDash()
                            ->unique(ignoreRecord: true),
                        Select::make('status')
                            ->options(['active' => 'Active', 'suspended' => 'Suspended'])
                            ->default('active')
                            ->required(),
                    ]),
                Section::make('Limits')
                    ->description('Leave empty for unlimited.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('max_domains')->numeric()->minValue(1),
                        TextInput::make('max_mailboxes')->numeric()->minValue(1),
                        TextInput::make('storage_limit_mb')->numeric()->minValue(1)->suffix('MB'),
                    ]),
            ]);
    }
}
