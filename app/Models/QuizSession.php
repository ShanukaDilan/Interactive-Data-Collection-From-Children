<?php

namespace App\Models;

use App\Enums\QuizMode;
use Database\Factories\QuizSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'uuid', 'study_id', 'participant_id', 'legacy_session_id', 'legacy_child_id',
    'child_age', 'quiz_mode', 'conducted_by', 'device_id', 'started_at', 'completed_at',
])]
class QuizSession extends Model
{
    /** @use HasFactory<QuizSessionFactory> */
    use HasFactory;

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'quiz_mode' => QuizMode::class,
            'child_age' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (QuizSession $session): void {
            $session->uuid ??= (string) Str::uuid();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** @return BelongsTo<Study, $this> */
    public function study(): BelongsTo
    {
        return $this->belongsTo(Study::class);
    }

    /** @return BelongsTo<Participant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function conductor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conducted_by');
    }

    /** @return BelongsTo<Device, $this> */
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /** @return HasMany<QuizResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(QuizResponse::class)->orderBy('position');
    }

    /**
     * Identifier shown in exports: participant code, else the legacy free-text child id.
     */
    public function childIdentifier(): string
    {
        return $this->participant?->code ?? $this->legacy_child_id ?? '';
    }
}
