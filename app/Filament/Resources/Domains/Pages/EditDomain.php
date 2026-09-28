<?php

namespace App\Filament\Resources\Domains\Pages;

use App\Filament\Resources\Domains\DomainResource;
use App\Filament\Support\ProviderErrors;
use App\Services\DomainService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditDomain extends EditRecord
{
    protected static string $resource = DomainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()->using(fn ($record) => ProviderErrors::guard(fn () => app(DomainService::class)->delete($record))),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        unset($data['organization_id'], $data['name']);

        return ProviderErrors::guard(fn () => app(DomainService::class)->update($record, $data));
    }
}
