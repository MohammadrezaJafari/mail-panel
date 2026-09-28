<?php

namespace App\Filament\Resources\Mailboxes\Schemas;

use App\Models\Domain;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class MailboxForm
{
    public static function configure(Schema $schema): Schema
    {
        $user = Auth::user();

        return $schema
            ->components([
                Section::make('Mailbox')
                    ->columns(2)
                    ->schema([
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
                            ->label('Address')
                            ->required()
                            ->regex('/^[a-z0-9._+-]+$/i')
                            ->maxLength(64)
                            ->suffix(fn (Get $get) => '@'.(Domain::find($get('domain_id'))?->name ?? '…'))
                            ->disabledOn('edit')
                            ->dehydrated(),
                        TextInput::make('name')
                            ->label('Display name')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('quota_mb')
                            ->label('Quota')
                            ->numeric()
                            ->minValue(1)
                            ->suffix('MB')
                            ->default(fn (Get $get) => Domain::find($get('domain_id'))?->default_quota_mb ?? 5120)
                            ->required()
                            ->visibleOn('create'),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(10)
                            ->confirmed()
                            ->default(fn () => Str::password(16, symbols: false))
                            ->helperText('Sent to the mail server; never stored in plain text here.')
                            ->dehydrated()
                            ->visibleOn('create'),
                        TextInput::make('password_confirmation')
                            ->password()
                            ->revealable()
                            ->required()
                            ->dehydrated(false)
                            ->visibleOn('create'),
                        Toggle::make('is_shared')
                            ->label('Shared mailbox')
                            ->helperText('Shared mailboxes (support@, sales@) have no personal portal login; members are granted access.')
                            ->disabledOn('edit'),
                        Toggle::make('create_user')
                            ->label('Create portal login')
                            ->default(true)
                            ->dehydrated()
                            ->visible(fn (Get $get) => ! $get('is_shared'))
                            ->visibleOn('create'),
                    ]),
                Section::make('Forwarding')
                    ->columns(2)
                    ->schema([
                        TagsInput::make('forwarding_to')
                            ->label('Forward to')
                            ->placeholder('Add e-mail address')
                            ->nestedRecursiveRules(['email']),
                        Toggle::make('forwarding_keep_copy')->label('Keep a copy')->default(true),
                    ])
                    ->visibleOn('edit'),
                Section::make('Auto reply')
                    ->columns(2)
                    ->schema([
                        Toggle::make('auto_reply_enabled')->label('Enabled')->live(),
                        TextInput::make('auto_reply_subject')->label('Subject')->maxLength(255),
                        DateTimePicker::make('auto_reply_starts_at')->label('Starts'),
                        DateTimePicker::make('auto_reply_ends_at')->label('Ends')->after('auto_reply_starts_at'),
                        Textarea::make('auto_reply_body')->label('Message')->rows(4)->columnSpanFull()
                            ->required(fn (Get $get) => (bool) $get('auto_reply_enabled')),
                    ])
                    ->visibleOn('edit'),
                Section::make('Signature')
                    ->schema([
                        Textarea::make('signature')->hiddenLabel()->rows(3),
                    ])
                    ->collapsed()
                    ->visibleOn('edit'),
            ]);
    }
}
