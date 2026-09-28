<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isOrganizationOwner();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isSuperAdmin() || $user->organization_id === $model->organization_id;
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isOrganizationOwner();
    }

    public function update(User $user, User $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return $user->isOrganizationOwner() && $user->organization_id === $model->organization_id && ! $model->isSuperAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        return $user->id !== $model->id && $this->update($user, $model);
    }
}
