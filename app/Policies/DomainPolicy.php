<?php

namespace App\Policies;

use App\Models\Domain;
use App\Models\User;

class DomainPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->isAdmin();
    }

    public function view(User $user, Domain $domain): bool
    {
        return $user->canManageOrganization($domain->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isOrganizationOwner();
    }

    public function update(User $user, Domain $domain): bool
    {
        return $user->canManageOrganization($domain->organization_id);
    }

    public function delete(User $user, Domain $domain): bool
    {
        return $user->isSuperAdmin() || ($user->isOrganizationOwner() && $user->organization_id === $domain->organization_id);
    }
}
