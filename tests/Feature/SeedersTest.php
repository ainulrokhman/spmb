<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\DocumentType;
use App\Models\FeeComponent;
use App\Models\Program;
use App\Models\SchoolUnit;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Env;
use RuntimeException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SeedersTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_seed_path_creates_no_demo_accounts(): void
    {
        $this->seed(DatabaseSeeder::class);

        // Structure and master pools are ready…
        $this->assertSame(59, Permission::count());
        $this->assertSame(4, Role::count());
        $this->assertSame(6, DocumentType::count());
        $this->assertSame(2, Program::count());
        $this->assertSame(4, FeeComponent::count());
        $this->assertTrue(Setting::flag('payment_manual_enabled'));

        // …but without SUPER_ADMIN_PASSWORD there is no account at
        // all — production never gains a guessable default login.
        $this->assertSame(0, User::count());
        $this->assertSame(0, SchoolUnit::count());
        $this->assertSame(0, Application::count());
    }

    public function test_initial_admin_is_created_from_env_configuration(): void
    {
        Env::getRepository()->set('SUPER_ADMIN_PASSWORD', 'kata-sandi-prod');
        Env::getRepository()->set('SUPER_ADMIN_NAME', 'Pengelola Utama');

        try {
            $this->seed(DatabaseSeeder::class);
        } finally {
            Env::getRepository()->clear('SUPER_ADMIN_PASSWORD');
            Env::getRepository()->clear('SUPER_ADMIN_NAME');
        }

        $admin = User::where('email', 'superadmin@example.com')->first();
        $this->assertNotNull($admin);
        $this->assertSame('Pengelola Utama', $admin->name);
        $this->assertTrue($admin->hasRole('SuperAdmin'));
    }

    public function test_demo_seeder_builds_a_complete_dataset_idempotently(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertSame(3, SchoolUnit::count());
        $this->assertSame(4, Application::count());
        $this->assertSame(3, User::whereNotNull('phone')->count());

        $submitted = Application::whereNotNull('registration_number')->first();
        $this->assertNotNull($submitted);
        $this->assertSame('1126001', $submitted->registration_number);

        $this->assertSame(1, Application::where('form_fee_request_status', 'pending')->count());
        $this->assertSame(1, Application::where('form_fee_status', 'paid')->where('status', 'filling_form')->count());

        $counts = [
            User::count(),
            SchoolUnit::count(),
            Application::count(),
        ];

        // Re-running must not duplicate anything.
        $this->seed(DemoSeeder::class);

        $this->assertSame($counts, [
            User::count(),
            SchoolUnit::count(),
            Application::count(),
        ]);
    }

    public function test_demo_seeder_refuses_to_run_in_production(): void
    {
        config(['app.env' => 'production']);

        $this->expectException(RuntimeException::class);

        $this->seed(DemoSeeder::class);
    }
}
