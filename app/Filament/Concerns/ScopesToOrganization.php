<?php

namespace App\Filament\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Non-super-admins only ever see records of their own organization.
 */
trait ScopesToOrganization
{
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user && ! $user->isSuperAdmin()) {
            $query->where(static::organizationColumn(), $user->organization_id);
        }

        return $query;
    }

    protected static function organizationColumn(): string
    {
        return 'organization_id';
    }
}
