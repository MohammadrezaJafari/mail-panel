<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserRole: string implements HasLabel
{
    case SuperAdmin = 'super_admin';
    case OrganizationOwner = 'organization_owner';
    case MailAdmin = 'mail_admin';
    case User = 'user';

    public function getLabel(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::OrganizationOwner => 'Organization Owner',
            self::MailAdmin => 'Mail Admin',
            self::User => 'User',
        };
    }

    public function isAdmin(): bool
    {
        return $this !== self::User;
    }
}
