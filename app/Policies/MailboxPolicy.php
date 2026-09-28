<?php

namespace App\Policies;

use App\Models\Mailbox;
use App\Models\User;

class MailboxPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->isAdmin();
    }

    public function view(User $user, Mailbox $mailbox): bool
    {
        return $user->canManageOrganization($mailbox->organization_id)
            || $mailbox->users()->whereKey($user->id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->role->isAdmin();
    }

    public function update(User $user, Mailbox $mailbox): bool
    {
        return $user->canManageOrganization($mailbox->organization_id);
    }

    public function delete(User $user, Mailbox $mailbox): bool
    {
        return $user->canManageOrganization($mailbox->organization_id);
    }

    /** Portal-level access: read mail, change own settings. */
    public function use(User $user, Mailbox $mailbox): bool
    {
        return $mailbox->users()->whereKey($user->id)->exists()
            || strcasecmp($user->email, $mailbox->address) === 0;
    }
}
