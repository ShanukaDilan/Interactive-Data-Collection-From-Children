<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\Question;
use App\Models\User;

/**
 * Questions form a shared bank managed by researchers (admins pass via Gate::before).
 */
class QuestionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isResearcher($user);
    }

    public function view(User $user, Question $question): bool
    {
        return $this->isResearcher($user);
    }

    public function create(User $user): bool
    {
        return $this->isResearcher($user);
    }

    public function update(User $user, Question $question): bool
    {
        return $this->isResearcher($user);
    }

    public function delete(User $user, Question $question): bool
    {
        return $this->isResearcher($user);
    }

    private function isResearcher(User $user): bool
    {
        return $user->hasRole(Role::Researcher->value);
    }
}
