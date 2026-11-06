<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin->value);
    }

    /**
     * Studies this user is assigned to (as researcher or teacher).
     *
     * @return BelongsToMany<Study, $this>
     */
    public function studies(): BelongsToMany
    {
        return $this->belongsToMany(Study::class);
    }

    /** @return HasMany<Study, $this> */
    public function ownedStudies(): HasMany
    {
        return $this->hasMany(Study::class, 'owner_id');
    }

    /** @return HasMany<Device, $this> */
    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    /**
     * Studies this user may access: all for admins, otherwise owned or assigned.
     *
     * @return Builder<Study>
     */
    public function accessibleStudies(): Builder
    {
        if ($this->isAdmin()) {
            return Study::query();
        }

        return Study::query()->where(function (Builder $query): void {
            $query->where('owner_id', $this->id)
                ->orWhereHas('members', fn (Builder $members) => $members->whereKey($this->id));
        });
    }

    public function canAccessStudy(Study $study): bool
    {
        return $this->accessibleStudies()->whereKey($study->id)->exists();
    }
}
