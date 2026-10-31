<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Contracts\RoleServiceInterface;
use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\DataTables\RoleDataTable;
use App\Support\SystemAccess;
use App\Http\Controllers\Admin\Concerns\HandlesServiceExceptions;

class RoleController extends Controller
{
    use HandlesServiceExceptions;

    protected RoleServiceInterface $roleService;

    /**
     * RoleController constructor.
     */
    public function __construct(RoleServiceInterface $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request, RoleDataTable $roleDataTable)
    {
        Gate::authorize('role-list');

        if ($request->ajax()) {
            return $roleDataTable->make();
        }

        $roles = collect();
        return view('admin.roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('role-create');

        $permissions = Permission::all();
        $groupedPermissions = $this->roleService->groupPermissions($permissions);

        return view('admin.roles.create', compact('groupedPermissions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreRoleRequest $request)
    {
        Gate::authorize('role-create');

        $role = $this->roleService->createRole($request->validated());

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role "' . $role->name . '" created successfully.');
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role)
    {
        Gate::authorize('role-edit');

        // Prevent editing SuperAdmin to avoid lockouts
        if ($role->name === SystemAccess::SUPER_ADMIN_ROLE) {
            return redirect()->route('admin.roles.index')
                ->with('error', 'The SuperAdmin role permissions are locked and cannot be modified.');
        }

        $permissions = Permission::all();
        $rolePermissions = $role->permissions->pluck('id')->toArray();
        $groupedPermissions = $this->roleService->groupPermissions($permissions);

        return view('admin.roles.edit', compact('role', 'groupedPermissions', 'rolePermissions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateRoleRequest $request, Role $role)
    {
        Gate::authorize('role-edit');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->roleService->updateRole($role, $request->validated()), 'admin.roles.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role "' . $role->name . '" updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        Gate::authorize('role-delete');

        if ($errorRedirect = $this->runServiceAction(fn () => $this->roleService->deleteRole($role), 'admin.roles.index')) {
            return $errorRedirect;
        }

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role deleted successfully.');
    }
}
