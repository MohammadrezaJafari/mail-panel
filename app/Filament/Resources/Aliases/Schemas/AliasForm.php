<?php

namespace App\Filament\Resources\Aliases\Schemas;

use App\Models\Domain;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class AliasForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = Auth::user();

        return $schema
            ->components([
                Section::make('Alias')->columns(2)->schema([
                    Select::make('domain_id')
                        ->label('Domain')
                        ->options(fn () => Domain::query()
                            ->when(! $user?->isSuperAdmin(), fn ($q) => $q->where('organization_id', $user?->organization_id))
                            ->orderBy('name')->pluck('name', 'id'))
                        ->default(fn () => request()->integer('domain') ?: null)
                        ->searchable()
                        ->required()
                        ->live()
                        ->disabledOn('edit')
                        ->dehydrated(),
                    TextInput::make('local_part')
                        ->label('Alias address')
                        ->required()
                        ->regex('/^[a-z0-9._+-]+$/i')
                        ->suffix(fn (Get $get) => '@'.(Domain::find($get('domain_id'))?->name ?? '…'))
                        ->visibleOn('create'),
                    TextInput::make('address')->disabled()->dehydrated(false)->visibleOn('edit'),
                    TagsInput::make('goto')
                        ->label('Deliver to')
                        ->placeholder('Add destination address')
                        ->required()
                        ->nestedRecursiveRules(['email'])
                        ->helperText('One or more mailboxes or external addresses. Multiple targets make a simple distribution list.')
                        ->columnSpanFull(),
                    Toggle::make('is_active')->label('Active')->default(true),
                ]),
            ]);
    }
}
