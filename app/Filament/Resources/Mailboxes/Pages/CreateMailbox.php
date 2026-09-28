<?php

namespace App\Filament\Resources\Mailboxes\Pages;

use App\Filament\Resources\Mailboxes\MailboxResource;
use App\Filament\Support\ProviderErrors;
use App\Models\Domain;
use App\Services\MailboxService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMailbox extends CreateRecord
{
    protected static string $resource = MailboxResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $domain = Domain::findOrFail($data['domain_id']);
        $password = $data['password'];
        $createUser = (bool) ($data['create_user'] ?? true);

        unset($data['domain_id'], $data['password'], $data['password_confirmation'], $data['create_user']);

        return ProviderErrors::guard(fn () => app(MailboxService::class)->create($domain, $data, $password, $createUser));
    }
}
