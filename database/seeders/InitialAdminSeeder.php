<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\SystemAccess;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the first Super Admin account for a fresh installation
 * from .env values (SUPER_ADMIN_NAME, SUPER_ADMIN_EMAIL,
 * SUPER_ADMIN_PASSWORD). When the email or password is not
 * configured the seeder skips account creation with a warning, so a
 * production database never gains a guessable default account.
 */
class InitialAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $email = env('SUPER_ADMIN_EMAIL', SystemAccess::SUPER_ADMIN_EMAIL);
        $password = env('SUPER_ADMIN_PASSWORD');

        if (empty($password)) {
            $this->command?->warn('InitialAdminSeeder: SUPER_ADMIN_PASSWORD belum diatur di .env — akun Super Admin awal tidak dibuat.');

            return;
        }

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Super Admin'),
                'password' => Hash::make($password),
            ]
        );
        $user->assignRole(SystemAccess::SUPER_ADMIN_ROLE);

        $this->command?->info("InitialAdminSeeder: akun Super Admin awal siap ({$email}).");
    }
}
