<?php

namespace App\Contracts;

use Spatie\Permission\Models\Role;
use Illuminate\Support\Collection;

interface RoleServiceInterface
{
    /**
     * Store a newly created role.
     *
     * @param array $data
     * @return Role
     */
    public function createRole(array $data): Role;

    /**
     * Update the specified role.
     *
     * @param Role $role
     * @param array $data
     * @return Role
     */
    public function updateRole(Role $role, array $data): Role;

    /**
     * Delete the specified role.
     *
     * @param Role $role
     * @return bool
     * @throws \Exception
     */
    public function deleteRole(Role $role): bool;

    /**
     * Group permissions by logical categories.
     *
     * @param Collection $permissions
     * @return array
     */
    public function groupPermissions(Collection $permissions): array;

    /**
     * Check if a role is a protected system role.
     *
     * @param string|Role $role
     * @return bool
     */
    public function isSystemRole(string|Role $role): bool;
}
