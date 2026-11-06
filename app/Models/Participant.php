<?php

namespace App\Models;

use Database\Factories\ParticipantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A child taking part in a study, identified by a pseudonymous code.
 * The optional display name is encrypted at rest and never exported by default.
 */
#[Fillable(['study_id', 'code', 'age', 'display_name', 'consent_recorded_at', 'consent_by', 'notes'])]
#[Hidden(['display_name'])]
class Participant extends Model
{
    /** @use HasFactory<ParticipantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'age' => 'integer',
            'display_name' => 'encrypted',
            'consent_recorded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Participant $participant): void {
            if (blank($participant->code)) {
                $participant->code = static::generateCode($participant->study_id);
            }
        });
    }

    /**
     * Generate a short, unambiguous code unique within the study, e.g. "P-7K3D".
     */
    public static function generateCode(int $studyId): string
    {
        do {
            $code = 'P-'.Str::upper(Str::random(4));
            $code = strtr($code, ['0' => '7', 'O' => 'K', '1' => '3', 'I' => 'D', 'L' => 'M']);
        } while (static::where('study_id', $studyId)->where('code', $code)->exists());

        return $code;
    }

    /** @return BelongsTo<Study, $this> */
    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }

    /** @return BelongsTo<User, $this> */
    public function consentRecordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consent_by');
    }

    /** @return HasMany<QuizSession, $this> */
    public function quizSessions(): HasMany
    {
        return $this->hasMany(QuizSession::class);
    }
}
