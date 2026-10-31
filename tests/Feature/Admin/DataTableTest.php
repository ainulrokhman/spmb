<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DataTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed default roles, permissions, and demo users
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    /**
     * Call an index route the way Yajra DataTables does from the browser.
     */
    private function datatableJson(string $routeName, int $length = 10): TestResponse
    {
        return $this->actingAs($this->admin())
            ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->get(route($routeName, ['draw' => 1, 'start' => 0, 'length' => $length]));
    }

    /**
     * Assert the shared Yajra DataTables JSON contract and return the payload.
     */
    private function assertDataTableShape(TestResponse $response): array
    {
        $response->assertOk();
        $response->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);

        return $response->json();
    }

    /**
     * Scenarios for the resource DataTables backed by seeded rows:
     * route name, page length, expected total, expected first-row
     * columns, and a string that must appear in the payload.
     *
     * @return array<string, array{string, int, int, list<string>, string}>
     */
    public static function seededDataTableProvider(): array
    {
        return [
            'users' => [
                'admin.users.index', 10, 3,
                ['user_details', 'assigned_roles', 'actions'],
                'superadmin@example.com',
            ],
            'roles' => [
                'admin.roles.index', 10, 3,
                ['role_name', 'permissions_preview', 'actions'],
                'SuperAdmin',
            ],
            // Permissions ask for one full page: the default page size
            // of 10 would cut the list before every seeded permission.
            'permissions' => [
                'admin.permissions.index', 25, 15,
                ['permission_name', 'roles_using_this', 'actions'],
                'user-list',
            ],
        ];
    }

    /**
     * Test a resource DataTable returns its seeded rows as JSON.
     *
     * @param  list<string>  $expectedColumns
     */
    #[DataProvider('seededDataTableProvider')]
    public function test_datatable_returns_seeded_rows(string $routeName, int $length, int $expectedTotal, array $expectedColumns, string $expectedContent): void
    {
        $data = $this->assertDataTableShape($this->datatableJson($routeName, $length));

        $this->assertSame($expectedTotal, $data['recordsTotal']);
        foreach ($expectedColumns as $column) {
            $this->assertArrayHasKey($column, $data['data'][0]);
        }
        $this->assertStringContainsString($expectedContent, json_encode($data['data']));
    }

    /**
     * Test the activity logs DataTable responds with the expected JSON shape.
     */
    public function test_activity_logs_datatable_returns_expected_structure(): void
    {
        $data = $this->assertDataTableShape($this->datatableJson('admin.activity-logs.index'));

        $this->assertIsArray($data['data']);

        // Seeding creates activity entries; when rows exist they must
        // carry the log columns the table renders.
        if ($data['recordsTotal'] > 0) {
            $this->assertArrayHasKey('actor', $data['data'][0]);
            $this->assertArrayHasKey('action', $data['data'][0]);
            $this->assertArrayHasKey('actions', $data['data'][0]);
        }
    }
}
