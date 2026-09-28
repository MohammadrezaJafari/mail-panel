<?php

namespace App\Policies;

use App\Models\Alias;
use App\Models\User;

class AliasPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->role->isAdmin();
    }

    public function view(User $user, Alias $alias): bool
    {
        return $user->canManageOrganization($alias->organization_id);
    }

    public function create(User $user): bool
    {
        return $user->role->isAdmin();
    }

    public function update(User $user, Alias $alias): bool
    {
        return $user->canManageOrganization($alias->organization_id);
    }

    public function delete(User $user, Alias $alias): bool
    {
        return $user->canManageOrganization($alias->organization_id);
    }
}
