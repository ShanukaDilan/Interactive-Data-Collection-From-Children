<?php

namespace App\Models;

use Database\Factories\ReportLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A private link a teacher shares with a parent to view one child's report.
 * The plain token exists only in the URL given to the parent; the database keeps its hash.
 */
#[Fillable(['participant_id', 'token_hash', 'created_by', 'expires_at', 'revoked_at', 'last_viewed_at', 'view_count'])]
#[Hidden(['token_hash'])]
class ReportLink extends Model
{
    /** @use HasFactory<ReportLinkFactory> */
    use HasFactory;

    public const TOKEN_LENGTH = 40;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'last_viewed_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }

    /**
     * Create a link and return it together with the plain token (shown to the teacher once).
     *
     * @return array{0: ReportLink, 1: string}
     */
    public static function issue(Participant $participant, User $creator, int $days): array
    {
        $token = Str::random(self::TOKEN_LENGTH);

        $link = static::create([
            'participant_id' => $participant->id,
            'token_hash' => self::hash($token),
            'created_by' => $creator->id,
            'expires_at' => now()->addDays($days),
        ]);

        return [$link, $token];
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findValid(string $token): ?self
    {
        if (strlen($token) !== self::TOKEN_LENGTH) {
            return null;
        }

        return static::query()->active()->where('token_hash', self::hash($token))->first();
    }

    /**
     * @param  Builder<ReportLink>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null && $this->expires_at->isFuture();
    }

    /** @return BelongsTo<Participant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(Participant::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
