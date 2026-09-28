<?php

namespace App\Filament\Resources\Domains\Pages;

use App\Filament\Resources\Domains\DomainResource;
use App\Filament\Support\ProviderErrors;
use App\Models\Domain;
use App\Services\DomainService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewDomain extends ViewRecord
{
    protected static string $resource = DomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('verifyDns')
                ->label('Verify DNS')
                ->icon('heroicon-o-shield-check')
                ->action(function (Domain $record) {
                    ProviderErrors::guard(fn () => app(DomainService::class)->verifyDns($record));
                    Notification::make()->title('DNS records checked')->success()->send();
                    $this->refreshFormData(['dns_status', 'dns_checked_at']);
                }),
            EditAction::make(),
        ];
    }
}
