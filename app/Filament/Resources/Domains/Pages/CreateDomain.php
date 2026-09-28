<?php

namespace App\Filament\Resources\Domains\Pages;

use App\Filament\Resources\Domains\DomainResource;
use App\Filament\Support\ProviderErrors;
use App\Models\Organization;
use App\Services\DomainService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateDomain extends CreateRecord
{
    protected static string $resource = DomainResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $organization = Organization::findOrFail($data['organization_id']);
        unset($data['organization_id']);

        return ProviderErrors::guard(fn () => app(DomainService::class)->create($organization, $data));
    }
}
