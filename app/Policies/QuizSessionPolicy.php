<?php

namespace App\Policies;

use App\Models\QuizSession;
use App\Models\User;

/**
 * Sessions are visible to anyone with access to their study (admins pass via Gate::before).
 */
class QuizSessionPolicy
{
    public function view(User $user, QuizSession $quizSession): bool
    {
        return $user->canAccessStudy($quizSession->study);
    }
}
