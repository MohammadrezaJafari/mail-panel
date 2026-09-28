<?php

namespace App\Filament\Resources\Mailboxes\Pages;

use App\Filament\Resources\Mailboxes\MailboxResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMailbox extends ViewRecord
{
    protected static string $resource = MailboxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
