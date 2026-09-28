<?php

namespace App\Models;

use App\Enums\MailboxStatus;
use App\Models\Concerns\HasProviderResource;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mailbox extends Model
{
    use HasFactory, HasProviderResource, SoftDeletes;

    protected $attributes = [
        'status' => 'active',
        'is_shared' => false,
        'quota_mb' => 5120,
        'used_bytes' => 0,
        'message_count' => 0,
        'forwarding_keep_copy' => true,
        'auto_reply_enabled' => false,
    ];

    protected $fillable = [
        'organization_id', 'domain_id', 'local_part', 'address', 'name', 'status', 'is_shared',
        'quota_mb', 'used_bytes', 'message_count', 'forwarding_to', 'forwarding_keep_copy',
        'auto_reply_enabled', 'auto_reply_subject', 'auto_reply_body', 'auto_reply_starts_at',
        'auto_reply_ends_at', 'signature', 'rules', 'usage_synced_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MailboxStatus::class,
            'is_shared' => 'boolean',
            'forwarding_to' => 'array',
            'forwarding_keep_copy' => 'boolean',
            'auto_reply_enabled' => 'boolean',
            'auto_reply_starts_at' => 'datetime',
            'auto_reply_ends_at' => 'datetime',
            'usage_synced_at' => 'datetime',
            'rules' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Mailbox $mailbox) {
            $mailbox->local_part = strtolower(trim($mailbox->local_part));
            if ($mailbox->domain_id && ! $mailbox->address) {
                $mailbox->address = $mailbox->local_part.'@'.$mailbox->domain->name;
            }
            $mailbox->address = strtolower($mailbox->address);
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

    public function aliases(): HasMany
    {
        return $this->hasMany(Alias::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(MailboxMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mailbox_members')->withPivot('role')->withTimestamps();
    }

    public function isActive(): bool
    {
        return $this->status === MailboxStatus::Active;
    }

    public function quotaBytes(): int
    {
        return (int) $this->quota_mb * 1024 * 1024;
    }

    public function usagePercent(): float
    {
        $quota = $this->quotaBytes();

        return $quota > 0 ? round(min(100, $this->used_bytes / $quota * 100), 1) : 0.0;
    }
}
