<?php

namespace App\Enums;

enum Role: string
{
    /** Full access: users, all studies, exports, deletes. */
    case Admin = 'admin';

    /** Owns studies: questions, participants, analytics, exports for own studies. */
    case Researcher = 'researcher';

    /** Runs quiz sessions and manages participants in assigned studies. */
    case Teacher = 'teacher';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
