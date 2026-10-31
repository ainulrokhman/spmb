<?php

namespace App\Providers;

use App\Support\SystemAccess;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(\App\Contracts\UserServiceInterface::class, \App\Services\UserService::class);
        $this->app->bind(\App\Contracts\RoleServiceInterface::class, \App\Services\RoleService::class);
        $this->app->bind(\App\Contracts\PermissionServiceInterface::class, \App\Services\PermissionService::class);
        $this->app->bind(\App\Contracts\ProfileServiceInterface::class, \App\Services\ProfileService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Implicitly grant "SuperAdmin" role all permissions
        Gate::before(function ($user, $ability) {
            return $user->hasRole(SystemAccess::SUPER_ADMIN_ROLE) ? true : null;
        });
    }
}
