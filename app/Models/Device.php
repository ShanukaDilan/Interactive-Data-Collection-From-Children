<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A classroom tablet/phone registered by a teacher for offline quiz use.
 * It syncs with its own Sanctum token, so it keeps working when the browser session expires.
 */
#[Fillable(['user_id', 'personal_access_token_id', 'name', 'user_agent', 'last_synced_at', 'last_seen_at', 'revoked_at'])]
#[Hidden(['personal_access_token_id'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    /** Sanctum ability granted to device tokens: read studies and sync answers, nothing else. */
    public const ABILITY = 'quiz:device';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    /**
     * Register a device for a user and return it with the plain token (shown to the device once).
     *
     * @return array{0: Device, 1: string}
     */
    public static function register(User $user, string $name, ?string $userAgent = null): array
    {
        return DB::transaction(function () use ($user, $name, $userAgent): array {
            $device = static::create([
                'user_id' => $user->id,
                'name' => Str::limit(trim($name), 80, ''),
                'user_agent' => Str::limit((string) $userAgent, 250, ''),
                'last_seen_at' => now(),
            ]);

            $token = $user->createToken("device:{$device->id}", [self::ABILITY]);
            $device->update(['personal_access_token_id' => $token->accessToken->id]);

            return [$device, $token->plainTextToken];
        });
    }

    /**
     * The active (not revoked) device that owns the given token, if any.
     */
    public static function forToken(mixed $token): ?self
    {
        if (! $token instanceof PersonalAccessToken) {
            return null;
        }

        return static::query()->active()->where('personal_access_token_id', $token->id)->first();
    }

    /**
     * Revoke the device: its token is deleted immediately, so it can no longer read or sync.
     */
    public function revoke(): void
    {
        DB::transaction(function (): void {
            if ($this->personal_access_token_id !== null) {
                PersonalAccessToken::whereKey($this->personal_access_token_id)->delete();
            }
            $this->update(['revoked_at' => now()]);
        });
    }

    /**
     * @param  Builder<Device>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('revoked_at');
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<QuizSession, $this> */
    public function quizSessions(): HasMany
    {
        return $this->hasMany(QuizSession::class);
    }
}
