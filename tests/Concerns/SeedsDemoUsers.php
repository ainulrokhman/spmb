<?php

namespace Tests\Concerns;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

trait SeedsDemoUsers
{
    /**
     * Seed the default roles, permissions, and demo users.
     */
    protected function seedDemoUsers(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * The seeded user holding the Admin role.
     */
    protected function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    /**
     * The seeded user holding the SuperAdmin role.
     */
    protected function superAdmin(): User
    {
        return User::where('email', 'superadmin@example.com')->firstOrFail();
    }

    /**
     * The seeded user holding only the plain User role.
     */
    protected function regularUser(): User
    {
        return User::where('email', 'user@example.com')->firstOrFail();
    }
}
