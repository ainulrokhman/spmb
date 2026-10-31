<?php

namespace App\Support;

/**
 * Single source of truth for the built-in account and system roles.
 *
 * The Super Admin seed account and the system roles are protected by
 * checks spread across services, controllers, DataTables, and views.
 * They all read from here so a rename only ever happens in one place.
 */
final class SystemAccess
{
    /**
     * The email of the protected Super Admin seed account.
     */
    public const SUPER_ADMIN_EMAIL = 'superadmin@example.com';

    /**
     * The role that bypasses every permission check via Gate::before.
     */
    public const SUPER_ADMIN_ROLE = 'SuperAdmin';

    /**
     * The other built-in roles created by the seeder.
     */
    public const ADMIN_ROLE = 'Admin';

    public const USER_ROLE = 'User';

    /**
     * Every role the application treats as a protected system role.
     *
     * @var list<string>
     */
    public const SYSTEM_ROLES = [
        self::SUPER_ADMIN_ROLE,
        self::ADMIN_ROLE,
        self::USER_ROLE,
    ];

    /**
     * Determine whether a role name belongs to a protected system role.
     */
    public static function isSystemRole(string $roleName): bool
    {
        return in_array($roleName, self::SYSTEM_ROLES, true);
    }
}
