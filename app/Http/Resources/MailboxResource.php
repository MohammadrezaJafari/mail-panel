<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MailboxResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'address' => $this->address,
            'name' => $this->name,
            'domain' => $this->domain?->name,
            'status' => $this->status?->value,
            'is_shared' => $this->is_shared,
            'quota_mb' => $this->quota_mb,
            'quota_bytes' => $this->quotaBytes(),
            'used_bytes' => $this->used_bytes,
            'usage_percent' => $this->usagePercent(),
            'message_count' => $this->message_count,
            'usage_synced_at' => $this->usage_synced_at?->toIso8601String(),
            'aliases' => $this->whenLoaded('aliases', fn () => $this->aliases->map(fn ($a) => ['id' => $a->id, 'address' => $a->address, 'is_active' => $a->is_active])),
            'forwarding_to' => $this->forwarding_to ?? [],
            'forwarding_keep_copy' => $this->forwarding_keep_copy,
            'auto_reply_enabled' => $this->auto_reply_enabled,
            'auto_reply_subject' => $this->auto_reply_subject,
            'auto_reply_body' => $this->auto_reply_body,
            'auto_reply_starts_at' => $this->auto_reply_starts_at?->toIso8601String(),
            'auto_reply_ends_at' => $this->auto_reply_ends_at?->toIso8601String(),
            'signature' => $this->signature,
        ];
    }
}
