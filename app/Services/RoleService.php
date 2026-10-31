<?php

namespace App\Services;

use App\Contracts\RoleServiceInterface;
use App\Support\SystemAccess;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Support\Collection;

class RoleService implements RoleServiceInterface
{
    /**
     * Store a newly created role.
     */
    public function createRole(array $data): Role
    {
        $role = Role::create([
            'name' => $data['name'],
            'guard_name' => 'web'
        ]);

        if (!empty($data['permissions'])) {
            $permissions = Permission::whereIn('id', $data['permissions'])->get();
            $role->syncPermissions($permissions);
        }

        return $role;
    }

    /**
     * Update the specified role.
     */
    public function updateRole(Role $role, array $data): Role
    {
        if ($role->name === SystemAccess::SUPER_ADMIN_ROLE) {
            throw new \LogicException('The SuperAdmin role is protected and cannot be modified.');
        }

        // Don't allow changing the name of Admin and User system roles (to prevent breakage)
        $isSystemRole = in_array($role->name, [SystemAccess::ADMIN_ROLE, SystemAccess::USER_ROLE]);
        if ($isSystemRole && ($data['name'] ?? '') !== $role->name) {
            throw new \InvalidArgumentException('Cannot change the name of the system role "' . $role->name . '".');
        }

        $role->update([
            'name' => $data['name']
        ]);

        $permissions = Permission::whereIn('id', $data['permissions'] ?? [])->get();
        $role->syncPermissions($permissions);

        return $role;
    }

    /**
     * Delete the specified role.
     */
    public function deleteRole(Role $role): bool
    {
        // System roles cannot be deleted
        if ($this->isSystemRole($role)) {
            throw new \LogicException('Cannot delete system role "' . $role->name . '".');
        }

        return (bool) $role->delete();
    }

    /**
     * Group permissions by suffix/prefix for logical categorization.
     */
    public function groupPermissions(Collection $permissions): array
    {
        $grouped = [];
        foreach ($permissions as $permission) {
            $parts = explode('-', $permission->name);
            if (count($parts) > 1) {
                $prefix = $parts[0];
                if ($prefix === 'view' || $prefix === 'manage') {
                    $category = ucfirst($parts[1]); // e.g. Dashboard, Settings
                } else {
                    $category = ucfirst($prefix); // e.g. User, Role, Permission
                }
            } else {
                $category = 'General';
            }
            $grouped[$category][] = $permission;
        }
        return $grouped;
    }

    /**
     * Check if a role is a protected system role.
     */
    public function isSystemRole(string|Role $role): bool
    {
        $roleName = $role instanceof Role ? $role->name : $role;
        return SystemAccess::isSystemRole($roleName);
    }
}
