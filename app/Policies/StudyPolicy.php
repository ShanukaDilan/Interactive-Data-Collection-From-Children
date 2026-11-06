<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Study;
use App\Models\User;

/**
 * Admins pass via Gate::before. Researchers manage studies they own or are members of;
 * teachers can only view studies they are assigned to.
 */
class StudyPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Study $study): bool
    {
        return $user->canAccessStudy($study);
    }

    public function create(User $user): bool
    {
        return $user->hasRole(Role::Researcher->value);
    }

    public function update(User $user, Study $study): bool
    {
        return $user->hasRole(Role::Researcher->value) && $user->canAccessStudy($study);
    }

    public function delete(User $user, Study $study): bool
    {
        return $study->owner_id === $user->id;
    }
}
