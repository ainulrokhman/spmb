<?php

namespace App\Providers;

use App\Contracts\AcademicYearServiceInterface;
use App\Contracts\AdmissionTrackServiceInterface;
use App\Contracts\MajorServiceInterface;
use App\Contracts\PermissionServiceInterface;
use App\Contracts\ProfileServiceInterface;
use App\Contracts\RoleServiceInterface;
use App\Contracts\SchoolUnitServiceInterface;
use App\Contracts\UserServiceInterface;
use App\Services\AcademicYearService;
use App\Services\AdmissionTrackService;
use App\Services\MajorService;
use App\Services\PermissionService;
use App\Services\ProfileService;
use App\Services\RoleService;
use App\Services\SchoolUnitService;
use App\Services\UserService;
use App\Support\SystemAccess;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserServiceInterface::class, UserService::class);
        $this->app->bind(RoleServiceInterface::class, RoleService::class);
        $this->app->bind(PermissionServiceInterface::class, PermissionService::class);
        $this->app->bind(ProfileServiceInterface::class, ProfileService::class);
        $this->app->bind(AcademicYearServiceInterface::class, AcademicYearService::class);
        $this->app->bind(SchoolUnitServiceInterface::class, SchoolUnitService::class);
        $this->app->bind(AdmissionTrackServiceInterface::class, AdmissionTrackService::class);
        $this->app->bind(MajorServiceInterface::class, MajorService::class);
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
