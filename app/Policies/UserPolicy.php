<?php

namespace App\Policies;

use App\Models\User;

/**
 * User management is admin-only (admins pass via Gate::before); this denies everyone else.
 * Admins are additionally prevented from deleting themselves in the component.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, User $model): bool
    {
        return $user->is($model);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $model): bool
    {
        return false;
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }
}
