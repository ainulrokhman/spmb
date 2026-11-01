<?php

namespace App\Http\Controllers\Admin;

use App\Contracts\ReportServiceInterface;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ReportServiceInterface $reports,
    ) {}

    /**
     * Display the SPMB overview dashboard: applicant summary for
     * the active academic year, per-unit reception totals, and the
     * system counts. Committee members only see their own units.
     */
    public function index(Request $request)
    {
        Gate::authorize('view-dashboard');

        $usersCount = User::count();
        $rolesCount = Role::count();
        $permissionsCount = Permission::count();

        // Fetch the 5 most recent registrations
        $recentUsers = User::orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $user = $request->user();
        $unitIds = $user->hasGlobalUnitAccess() ? null : $user->accessibleUnitIds();
        $summary = $this->reports->dashboardSummary($unitIds);

        return view('admin.dashboard', compact(
            'usersCount',
            'rolesCount',
            'permissionsCount',
            'recentUsers',
            'summary'
        ));
    }
}
