<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public function log(string $action, ?Model $subject = null, array $payload = [], ?User $actor = null, ?int $organizationId = null): AuditLog
    {
        $actor ??= Auth::user();
        $request = app()->bound('request') ? request() : null;

        return AuditLog::create([
            'organization_id' => $organizationId ?? $subject?->getAttribute('organization_id') ?? $actor?->organization_id,
            'actor_id' => $actor?->getKey(),
            'action' => $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subject?->getAttribute('address') ?? $subject?->getAttribute('name'),
            'payload' => $payload ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
        ]);
    }
}
