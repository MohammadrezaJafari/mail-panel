<?php

namespace App\Models;

use App\Models\Concerns\HasProviderResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Alias extends Model
{
    use HasFactory, HasProviderResource;

    protected $fillable = [
        'organization_id', 'domain_id', 'mailbox_id', 'address', 'goto', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'goto' => 'array',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Alias $alias) {
            $alias->address = strtolower(trim($alias->address));
            $alias->goto = array_values(array_unique(array_map(fn ($g) => strtolower(trim($g)), $alias->goto ?? [])));
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(Domain::class);
    }

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(Mailbox::class);
    }
}
