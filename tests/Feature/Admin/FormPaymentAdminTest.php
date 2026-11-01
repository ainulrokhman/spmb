<?php

namespace Tests\Feature\Admin;

use App\Models\Application;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class FormPaymentAdminTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
    }

    /**
     * A committee member with the form payment permissions, assigned
     * to one unit only.
     */
    private function committeeMember(SchoolUnit $unit): User
    {
        $role = Role::firstOrCreate(['name' => 'PanitiaUji']);
        $role->syncPermissions(['form-payment-list', 'form-payment-decide']);

        $user = User::factory()->create();
        $user->assignRole($role);
        $user->schoolUnits()->attach($unit);

        return $user;
    }

    public function test_scoped_committee_only_sees_own_unit_requests(): void
    {
        $unitA = SchoolUnit::factory()->create(['name' => 'Unit Alpha']);
        $unitB = SchoolUnit::factory()->create(['name' => 'Unit Beta']);

        foreach ([$unitA, $unitB] as $unit) {
            Application::factory()->awaitingFee()->create([
                'school_unit_id' => $unit->id,
                'form_fee_request_kind' => 'waiver',
                'form_fee_request_reason' => 'Anak yatim',
                'form_fee_request_status' => 'pending',
            ]);
        }

        $response = $this->actingAs($this->committeeMember($unitA))
            ->get(route('admin.form-payments.index'));

        $response->assertOk();
        $response->assertSee('Unit Alpha');
        $response->assertDontSee('Unit Beta');
    }

    public function test_scoped_committee_cannot_decide_other_units_requests(): void
    {
        $unitA = SchoolUnit::factory()->create();
        $unitB = SchoolUnit::factory()->create();

        $foreign = Application::factory()->awaitingFee()->create([
            'school_unit_id' => $unitB->id,
            'form_fee_request_kind' => 'waiver',
            'form_fee_request_status' => 'pending',
        ]);

        $this->actingAs($this->committeeMember($unitA))
            ->post(route('admin.form-payments.decide', $foreign), ['decision' => 'approve'])
            ->assertForbidden();

        $this->assertSame('pending', $foreign->fresh()->form_fee_request_status);
    }

    public function test_admin_can_update_payment_channel_settings(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.settings.edit'))
            ->assertOk()
            ->assertSee('Kanal Pembayaran');

        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), [
                'payment_manual_enabled' => '1',
                'payment_manual_instructions' => 'Transfer ke BSI 123 a.n. Yayasan',
                'payment_midtrans_enabled' => '1',
            ])
            ->assertRedirect(route('admin.settings.edit'));

        $this->assertSame('Transfer ke BSI 123 a.n. Yayasan', \App\Models\Setting::get('payment_manual_instructions'));
        $this->assertTrue(\App\Models\Setting::flag('payment_midtrans_enabled'));
    }

    public function test_user_without_permission_cannot_open_form_payments(): void
    {
        $user = User::factory()->create();
        $user->assignRole('User');

        $this->actingAs($user)
            ->get(route('admin.form-payments.index'))
            ->assertForbidden();
    }
}
