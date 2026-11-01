<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * This is the PRODUCTION-SAFE path: roles and permissions, the
     * shared master pools, initial payment settings, and — only when
     * configured in .env — the first Super Admin account. Demo data
     * lives in DemoSeeder and is never called from here.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            SpmbMasterSeeder::class,
            InitialAdminSeeder::class,
        ]);
    }
}
