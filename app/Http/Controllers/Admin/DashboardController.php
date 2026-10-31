<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    /**
     * Display the overview dashboard.
     */
    public function index()
    {
        Gate::authorize('view-dashboard');

        $usersCount = User::count();
        $rolesCount = Role::count();
        $permissionsCount = Permission::count();

        // Fetch the 5 most recent registrations
        $recentUsers = User::orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        return view('admin.dashboard', compact(
            'usersCount',
            'rolesCount',
            'permissionsCount',
            'recentUsers'
        ));
    }
}
