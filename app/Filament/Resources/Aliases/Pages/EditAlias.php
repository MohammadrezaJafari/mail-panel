<?php

namespace App\Filament\Resources\Aliases\Pages;

use App\Filament\Resources\Aliases\AliasResource;
use App\Filament\Support\ProviderErrors;
use App\Services\AliasService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAlias extends EditRecord
{
    protected static string $resource = AliasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->using(fn ($record) => ProviderErrors::guard(fn () => app(AliasService::class)->delete($record))),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return ProviderErrors::guard(fn () => app(AliasService::class)->update($record, [
            'goto' => $data['goto'],
            'is_active' => $data['is_active'] ?? true,
        ]));
    }
}
