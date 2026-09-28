<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProviderResource extends Model
{
    protected $fillable = ['provider', 'resource_type', 'resource_id', 'external_id', 'meta', 'synced_at'];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'synced_at' => 'datetime',
        ];
    }

    public function resource(): MorphTo
    {
        return $this->morphTo();
    }
}
