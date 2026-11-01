<?php

namespace App\Providers;

use App\Contracts\AcademicYearServiceInterface;
use App\Contracts\AdmissionTrackServiceInterface;
use App\Contracts\AdmissionWaveServiceInterface;
use App\Contracts\DocumentRequirementServiceInterface;
use App\Contracts\DocumentTypeServiceInterface;
use App\Contracts\FeeAmountServiceInterface;
use App\Contracts\FeeComponentServiceInterface;
use App\Contracts\ProgramServiceInterface;
use App\Contracts\ApplicationServiceInterface;
use App\Contracts\InvoiceServiceInterface;
use App\Contracts\ReportServiceInterface;
use App\Contracts\FeeServiceInterface;
use App\Contracts\FormFieldServiceInterface;
use App\Contracts\MajorServiceInterface;
use App\Contracts\PermissionServiceInterface;
use App\Contracts\ProfileServiceInterface;
use App\Contracts\RoleServiceInterface;
use App\Contracts\SchoolUnitServiceInterface;
use App\Contracts\UserServiceInterface;
use App\Services\AcademicYearService;
use App\Services\AdmissionTrackService;
use App\Services\AdmissionWaveService;
use App\Services\DocumentRequirementService;
use App\Services\DocumentTypeService;
use App\Services\FeeAmountService;
use App\Services\FeeComponentService;
use App\Services\ProgramService;
use App\Services\ApplicationService;
use App\Services\InvoiceService;
use App\Services\ReportService;
use App\Services\FeeService;
use App\Services\FormFieldService;
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
        $this->app->bind(AdmissionWaveServiceInterface::class, AdmissionWaveService::class);
        $this->app->bind(DocumentTypeServiceInterface::class, DocumentTypeService::class);
        $this->app->bind(DocumentRequirementServiceInterface::class, DocumentRequirementService::class);
        $this->app->bind(FormFieldServiceInterface::class, FormFieldService::class);
        $this->app->bind(FeeComponentServiceInterface::class, FeeComponentService::class);
        $this->app->bind(ProgramServiceInterface::class, ProgramService::class);
        $this->app->bind(FeeServiceInterface::class, FeeService::class);
        $this->app->bind(ApplicationServiceInterface::class, ApplicationService::class);
        $this->app->bind(InvoiceServiceInterface::class, InvoiceService::class);
        $this->app->bind(ReportServiceInterface::class, ReportService::class);
        $this->app->bind(FeeAmountServiceInterface::class, FeeAmountService::class);
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
