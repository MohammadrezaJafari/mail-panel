<?php

namespace App\Filament\Resources\Mailboxes\Pages;

use App\Filament\Resources\Mailboxes\MailboxResource;
use App\Filament\Support\ProviderErrors;
use App\Services\MailboxService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMailbox extends EditRecord
{
    protected static string $resource = MailboxResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()->using(fn ($record) => ProviderErrors::guard(fn () => app(MailboxService::class)->delete($record))),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        unset($data['domain_id'], $data['local_part'], $data['is_shared']);

        $ruleKeys = ['forwarding_to', 'forwarding_keep_copy', 'auto_reply_enabled', 'auto_reply_subject', 'auto_reply_body', 'auto_reply_starts_at', 'auto_reply_ends_at', 'signature'];
        $rules = array_intersect_key($data, array_flip($ruleKeys));
        $basic = array_diff_key($data, $rules);

        return ProviderErrors::guard(function () use ($record, $basic, $rules) {
            $service = app(MailboxService::class);
            $record = $service->update($record, $basic);

            return $service->updateRules($record, $rules);
        });
    }
}
