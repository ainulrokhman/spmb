<?php

namespace App\Contracts;

use Spatie\Permission\Models\Permission;

interface PermissionServiceInterface
{
    /**
     * Store a newly created permission.
     *
     * @param array $data
     * @return Permission
     */
    public function createPermission(array $data): Permission;

    /**
     * Update the specified permission.
     *
     * @param Permission $permission
     * @param array $data
     * @return Permission
     */
    public function updatePermission(Permission $permission, array $data): Permission;

    /**
     * Delete the specified permission.
     *
     * @param Permission $permission
     * @return bool
     * @throws \Exception
     */
    public function deletePermission(Permission $permission): bool;

    /**
     * Check if a permission is a protected core system permission.
     *
     * @param Permission $permission
     * @return bool
     */
    public function isPermissionProtected(Permission $permission): bool;
}
