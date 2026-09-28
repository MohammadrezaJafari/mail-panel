<?php

namespace App\Models;

use App\Enums\DomainStatus;
use App\Models\Concerns\HasProviderResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Domain extends Model
{
    use HasFactory, HasProviderResource;

    protected $attributes = [
        'status' => 'pending',
        'max_mailboxes' => 50,
        'max_aliases' => 200,
        'default_quota_mb' => 5120,
        'max_quota_mb' => 10240,
        'domain_quota_mb' => 102400,
    ];

    protected $fillable = [
        'organization_id', 'name', 'description', 'status', 'max_mailboxes', 'max_aliases',
        'default_quota_mb', 'max_quota_mb', 'domain_quota_mb', 'dns_status', 'dns_checked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DomainStatus::class,
            'dns_status' => 'array',
            'dns_checked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(fn (Domain $domain) => $domain->name = strtolower(trim($domain->name)));
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function mailboxes(): HasMany
    {
        return $this->hasMany(Mailbox::class);
    }

    public function aliases(): HasMany
    {
        return $this->hasMany(Alias::class);
    }

    public function isActive(): bool
    {
        return $this->status === DomainStatus::Active;
    }
}
