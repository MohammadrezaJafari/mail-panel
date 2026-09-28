<?php

namespace App\Models\Concerns;

use App\Models\ProviderResource;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasProviderResource
{
    public function providerResource(): MorphOne
    {
        return $this->morphOne(ProviderResource::class, 'resource');
    }

    public function externalId(?string $provider = null): ?string
    {
        $provider ??= config('mailprovider.default');

        return $this->providerResource()->where('provider', $provider)->value('external_id');
    }

    public function rememberExternalId(string $externalId, array $meta = [], ?string $provider = null): ProviderResource
    {
        $provider ??= config('mailprovider.default');

        return $this->providerResource()->updateOrCreate(
            ['provider' => $provider],
            ['external_id' => $externalId, 'meta' => $meta, 'synced_at' => now()],
        );
    }
}
