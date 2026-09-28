<?php

namespace App\Filament\Resources\AuditLogs\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AuditLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns(3)->schema([
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('action')->badge(),
                    TextEntry::make('actor.email')->label('Actor')->placeholder('system'),
                    TextEntry::make('subject_label')->label('Subject')->placeholder('—'),
                    TextEntry::make('subject_type')->label('Subject type')->formatStateUsing(fn ($state) => $state ? class_basename($state) : '—'),
                    TextEntry::make('organization.name')->label('Organization')->placeholder('—'),
                    TextEntry::make('ip_address')->label('IP')->placeholder('—'),
                    TextEntry::make('user_agent')->placeholder('—')->columnSpan(2),
                ]),
                Section::make('Payload')->schema([
                    KeyValueEntry::make('payload')->hiddenLabel(),
                ])->visible(fn ($record) => filled($record->payload)),
            ]);
    }
}
