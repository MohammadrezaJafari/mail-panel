<?php

namespace App\Filament\Resources\Domains\Schemas;

use App\Enums\DomainStatus;
use App\Models\Organization;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class DomainForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = Auth::user();

        return $schema
            ->components([
                Section::make('Domain')
                    ->columns(2)
                    ->schema([
                        Select::make('organization_id')
                            ->label('Organization')
                            ->options(fn () => $user?->isSuperAdmin()
                                ? Organization::orderBy('name')->pluck('name', 'id')
                                : Organization::whereKey($user?->organization_id)->pluck('name', 'id'))
                            ->default($user?->organization_id)
                            ->searchable()
                            ->required()
                            ->disabledOn('edit')
                            ->dehydrated(),
                        TextInput::make('name')
                            ->label('Domain name')
                            ->placeholder('example.com')
                            ->required()
                            ->regex('/^(?=.{1,253}$)([a-z0-9-]+\.)+[a-z]{2,}$/i')
                            ->unique(ignoreRecord: true)
                            ->disabledOn('edit')
                            ->dehydrated(),
                        TextInput::make('description')->maxLength(255),
                        Select::make('status')
                            ->options(DomainStatus::class)
                            ->default(DomainStatus::Active)
                            ->required(),
                    ]),
                Section::make('Limits & quota')
                    ->columns(3)
                    ->schema([
                        TextInput::make('max_mailboxes')->numeric()->minValue(1)->default(50)->required(),
                        TextInput::make('max_aliases')->numeric()->minValue(0)->default(200)->required(),
                        TextInput::make('domain_quota_mb')->label('Domain quota')->numeric()->minValue(1)->default(102400)->suffix('MB')->required(),
                        TextInput::make('default_quota_mb')->label('Default mailbox quota')->numeric()->minValue(1)->default(5120)->suffix('MB')->required(),
                        TextInput::make('max_quota_mb')->label('Max mailbox quota')->numeric()->minValue(1)->default(10240)->suffix('MB')->required()
                            ->gte('default_quota_mb'),
                    ]),
            ]);
    }
}
