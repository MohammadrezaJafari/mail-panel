<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'organization_id', 'name', 'email', 'password', 'role', 'is_active', 'mail_password',
        'last_login_at', 'last_login_ip',
    ];

    protected $hidden = ['password', 'remember_token', 'mail_password'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'mail_password' => 'encrypted',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function mailboxes(): BelongsToMany
    {
        return $this->belongsToMany(Mailbox::class, 'mailbox_members')->withPivot('role')->withTimestamps();
    }

    /**
     * The user's own (non-shared) mailbox, matched on the login e-mail first.
     */
    public function primaryMailbox(): ?Mailbox
    {
        return $this->mailboxes()->where('address', strtolower($this->email))->first()
            ?? $this->mailboxes()->where('is_shared', false)->orderBy('mailbox_members.created_at')->first()
            ?? Mailbox::query()->where('address', strtolower($this->email))->first();
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isOrganizationOwner(): bool
    {
        return $this->role === UserRole::OrganizationOwner;
    }

    public function isMailAdmin(): bool
    {
        return $this->role === UserRole::MailAdmin;
    }

    public function canManageOrganization(?int $organizationId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $organizationId !== null
            && $this->organization_id === $organizationId
            && in_array($this->role, [UserRole::OrganizationOwner, UserRole::MailAdmin], true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active && $this->role->isAdmin();
    }
}
