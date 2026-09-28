<?php

namespace App\Filament\Resources\Aliases\Pages;

use App\Filament\Resources\Aliases\AliasResource;
use App\Filament\Support\ProviderErrors;
use App\Models\Domain;
use App\Services\AliasService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAlias extends CreateRecord
{
    protected static string $resource = AliasResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $domain = Domain::findOrFail($data['domain_id']);

        return ProviderErrors::guard(fn () => app(AliasService::class)->create($domain, [
            'address' => strtolower($data['local_part']).'@'.$domain->name,
            'goto' => $data['goto'],
            'is_active' => $data['is_active'] ?? true,
        ]));
    }
}
