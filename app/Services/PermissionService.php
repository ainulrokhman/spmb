<?php

namespace App\Services;

use App\Contracts\PermissionServiceInterface;
use Spatie\Permission\Models\Permission;

class PermissionService implements PermissionServiceInterface
{
    protected array $protectedPermissions = [
        'view-dashboard',
        'user-list', 'user-create', 'user-edit', 'user-delete',
        'role-list', 'role-create', 'role-edit', 'role-delete',
        'permission-list', 'permission-create', 'permission-edit', 'permission-delete',
        'manage-settings'
    ];

    /**
     * Store a newly created permission.
     */
    public function createPermission(array $data): Permission
    {
        return Permission::create([
            'name' => strtolower($data['name']),
            'guard_name' => 'web'
        ]);
    }

    /**
     * Update the specified permission.
     */
    public function updatePermission(Permission $permission, array $data): Permission
    {
        if ($this->isPermissionProtected($permission)) {
            throw new \LogicException('The core system permission "' . $permission->name . '" is protected and cannot be modified.');
        }

        $permission->update([
            'name' => strtolower($data['name'])
        ]);

        return $permission;
    }

    /**
     * Delete the specified permission.
     */
    public function deletePermission(Permission $permission): bool
    {
        if ($this->isPermissionProtected($permission)) {
            throw new \LogicException('Cannot delete core system permission "' . $permission->name . '".');
        }

        return (bool) $permission->delete();
    }

    /**
     * Check if a permission is a protected core system permission.
     */
    public function isPermissionProtected(Permission $permission): bool
    {
        return in_array($permission->name, $this->protectedPermissions);
    }
}
