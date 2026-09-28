<?php

namespace App\Filament\Resources\Organizations\Schemas;

use App\Models\Organization;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrganizationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns(4)->schema([
                    TextEntry::make('name'),
                    TextEntry::make('slug'),
                    TextEntry::make('status')->badge()->color(fn ($state) => $state === 'active' ? 'success' : 'danger'),
                    TextEntry::make('created_at')->dateTime(),
                ]),
                Section::make('Usage')->columns(4)->schema([
                    TextEntry::make('domains_count')->label('Domains')->state(fn (Organization $r) => $r->domains()->count()),
                    TextEntry::make('mailboxes_count')->label('Mailboxes')->state(fn (Organization $r) => $r->mailboxes()->count().($r->max_mailboxes ? " / {$r->max_mailboxes}" : '')),
                    TextEntry::make('aliases_count')->label('Aliases')->state(fn (Organization $r) => $r->aliases()->count()),
                    TextEntry::make('storage')->label('Storage used')->state(fn (Organization $r) => number_format($r->mailboxes()->sum('used_bytes') / 1048576, 1).' MB'.($r->storage_limit_mb ? " / {$r->storage_limit_mb} MB" : '')),
                ]),
            ]);
    }
}
