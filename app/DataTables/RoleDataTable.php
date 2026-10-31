<?php

namespace App\DataTables;

use App\Contracts\RoleServiceInterface;
use App\Support\SystemAccess;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Yajra\DataTables\Facades\DataTables;

class RoleDataTable
{
    protected RoleServiceInterface $roleService;

    public function __construct(RoleServiceInterface $roleService)
    {
        $this->roleService = $roleService;
    }

    /**
     * Build the DataTable response.
     *
     * @return JsonResponse
     */
    public function make()
    {
        $roles = Role::select(['id', 'name', 'guard_name'])->withCount(['users', 'permissions'])->with('permissions');

        return DataTables::of($roles)
            ->addColumn('role_name', $this->roleNameColumn())
            ->editColumn('permissions_count', $this->permissionsCountColumn())
            ->editColumn('users_count', $this->usersCountColumn())
            ->addColumn('permissions_preview', $this->permissionsPreviewColumn())
            ->addColumn('actions', $this->actionsColumn())
            ->rawColumns(['role_name', 'permissions_count', 'users_count', 'permissions_preview', 'actions'])
            ->make(true);
    }

    /**
     * Build the `role_name` column renderer.
     */
    private function roleNameColumn(): \Closure
    {
        return function ($role) {
            return '<div class="d-flex align-items-center">
                    <div class="bg-primary bg-opacity-10 text-primary rounded-3 p-2 me-3 d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                        <i class="bi bi-shield-lock fs-5"></i>
                    </div>
                    <div>
                        <span class="fw-bold text-body d-block">'.e($role->name).'</span>
                        <span class="text-muted small">Guard: '.e($role->guard_name).'</span>
                    </div>
                </div>';
        };
    }

    /**
     * Build the `permissions_count` column renderer.
     */
    private function permissionsCountColumn(): \Closure
    {
        return function ($role) {
            return '<span class="badge bg-info bg-opacity-10 text-info fw-medium py-2 px-3 fs-7 border border-info border-opacity-20 rounded-pill">'.$role->permissions_count.' Permissions</span>';
        };
    }

    /**
     * Build the `users_count` column renderer.
     */
    private function usersCountColumn(): \Closure
    {
        return function ($role) {
            return '<div class="text-center"><span class="badge bg-secondary bg-opacity-10 text-secondary fw-bold py-2 px-3 fs-7 border border-secondary border-opacity-20 rounded-pill">'.$role->users_count.' Users</span></div>';
        };
    }

    /**
     * Build the `permissions_preview` column renderer.
     */
    private function permissionsPreviewColumn(): \Closure
    {
        return function ($role) {
            if ($role->name === SystemAccess::SUPER_ADMIN_ROLE) {
                return '<span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-10 rounded-pill py-1 px-2 text-xs">all-permissions (Bypass)</span>';
            }
            $allPerms = $role->permissions;
            $previewPerms = $allPerms->take(3);
            $remainingCount = $allPerms->count() - 3;

            $html = '<div class="d-flex flex-wrap gap-1" style="max-width: 320px;">';
            foreach ($previewPerms as $perm) {
                $html .= '<span class="badge bg-light text-secondary border rounded-pill py-1 px-2 text-xs">'.e($perm->name).'</span>';
            }
            if ($remainingCount > 0) {
                $html .= '<span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-10 rounded-pill py-1 px-2 text-xs fw-bold">+'.$remainingCount.' more</span>';
            }
            if ($previewPerms->isEmpty()) {
                $html .= '<span class="text-muted small italic">None</span>';
            }
            $html .= '</div>';

            return $html;
        };
    }

    /**
     * Build the `actions` column renderer.
     */
    private function actionsColumn(): \Closure
    {
        return function ($role) {
            $html = '<div class="d-flex justify-content-end gap-2">';
            if ($role->name === SystemAccess::SUPER_ADMIN_ROLE) {
                $html .= '<button class="btn btn-sm btn-light border" disabled title="SuperAdmin permissions are locked"><i class="bi bi-lock-fill text-muted me-1"></i> Locked</button>';
            } else {
                if (auth()->user()->can('role-edit')) {
                    $html .= '<a href="'.route('admin.roles.edit', $role->id).'" class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" title="Edit Role"><i class="bi bi-pencil-square text-primary"></i> Edit</a>';
                }
                if (auth()->user()->can('role-delete')) {
                    if ($this->roleService->isSystemRole($role)) {
                        $html .= '<button class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" disabled title="Cannot delete system role"><i class="bi bi-trash-fill text-muted"></i></button>';
                    } else {
                        $html .= '<form action="'.route('admin.roles.destroy', $role->id).'" method="POST" class="d-inline" onsubmit="return confirm(\'Are you sure you want to delete this role? Any assigned users will lose these permissions.\')">';
                        $html .= csrf_field().method_field('DELETE');
                        $html .= '<button type="submit" class="btn btn-sm btn-light border py-1.5 px-2.5 rounded-2" title="Delete Role"><i class="bi bi-trash text-danger"></i></button>';
                        $html .= '</form>';
                    }
                }
            }
            $html .= '</div>';

            return $html;
        };
    }
}
