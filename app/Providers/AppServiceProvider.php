<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            return $user->hasRole(Role::Admin->value) ? true : null;
        });

        /** Question quality and anonymous peer comparisons are for researchers, not teachers/parents. */
        Gate::define('view-research-analytics', fn (User $user): bool => $user->hasRole(Role::Researcher->value));
    }
}
