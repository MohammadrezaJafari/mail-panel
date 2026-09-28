<?php

namespace App\Filament\Resources\Mailboxes\Schemas;

use App\Models\Mailbox;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class MailboxInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()->columns(4)->schema([
                    TextEntry::make('address')->copyable()->weight('bold'),
                    TextEntry::make('name'),
                    TextEntry::make('status')->badge(),
                    IconEntry::make('is_shared')->label('Shared')->boolean(),
                    TextEntry::make('domain.name')->label('Domain'),
                    TextEntry::make('organization.name')->label('Organization'),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('usage_synced_at')->label('Usage synced')->since()->placeholder('never'),
                ]),
                Section::make('Storage')->columns(3)->schema([
                    TextEntry::make('quota_mb')->label('Quota')->state(fn (Mailbox $r) => Number::fileSize($r->quotaBytes())),
                    TextEntry::make('used_bytes')->label('Used')->state(fn (Mailbox $r) => Number::fileSize($r->used_bytes).' ('.$r->usagePercent().'%)')
                        ->color(fn (Mailbox $r) => $r->usagePercent() > 90 ? 'danger' : ($r->usagePercent() > 75 ? 'warning' : null)),
                    TextEntry::make('message_count')->label('Messages')->numeric(),
                ]),
                Section::make('Rules')->columns(2)->schema([
                    TextEntry::make('forwarding_to')->label('Forwarding')->listWithLineBreaks()->placeholder('none')
                        ->helperText(fn (Mailbox $r) => $r->forwarding_to ? ($r->forwarding_keep_copy ? 'A copy is kept in the mailbox.' : 'Messages are not kept.') : null),
                    TextEntry::make('auto_reply')->label('Auto reply')->state(fn (Mailbox $r) => $r->auto_reply_enabled
                        ? ($r->auto_reply_subject ?: 'Enabled').($r->auto_reply_starts_at ? ' ('.$r->auto_reply_starts_at->toDateString().' → '.($r->auto_reply_ends_at?->toDateString() ?? '∞').')' : '')
                        : 'Off'),
                ]),
            ]);
    }
}
