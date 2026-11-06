<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Participant;
use App\Models\Study;
use App\Models\User;

/**
 * Anyone assigned to a study may add/edit its participants; only researchers may delete them.
 */
class ParticipantPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Participant $participant): bool
    {
        return $user->canAccessStudy($participant->study);
    }

    public function create(User $user, Study $study): bool
    {
        return $user->canAccessStudy($study);
    }

    public function update(User $user, Participant $participant): bool
    {
        return $user->canAccessStudy($participant->study);
    }

    public function delete(User $user, Participant $participant): bool
    {
        return $user->hasRole(Role::Researcher->value) && $user->canAccessStudy($participant->study);
    }
}
