<?php

namespace App\Filament\Resources\Domains\RelationManagers;

use App\Filament\Resources\Mailboxes\MailboxResource;
use App\Filament\Resources\Mailboxes\Tables\MailboxesTable;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class MailboxesRelationManager extends RelationManager
{
    protected static string $relationship = 'mailboxes';

    public function table(Table $table): Table
    {
        return MailboxesTable::configure($table)
            ->headerActions([
                Action::make('create')
                    ->label('New mailbox')
                    ->icon('heroicon-o-plus')
                    ->url(fn () => MailboxResource::getUrl('create', ['domain' => $this->getOwnerRecord()->getKey()])),
            ]);
    }
}
