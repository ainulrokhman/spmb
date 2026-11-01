<?php

namespace Tests\Feature\Guardian;

use App\Models\Application;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

class MidtransWebhookTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private Application $application;

    private Payment $payment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();

        config([
            'midtrans.server_key' => 'test-server-key',
            'midtrans.client_key' => 'test-client-key',
        ]);
        Setting::set('payment_midtrans_enabled', '1');

        $guardian = User::factory()->create(['phone' => '081234567890']);
        $guardian->assignRole('Wali');

        $this->application = Application::factory()->awaitingFee(75000)->create(['user_id' => $guardian->id]);
    }

    /**
     * Start a Midtrans payment through the guardian endpoint with
     * the Snap API faked.
     */
    private function startMidtransPayment(): Payment
    {
        Http::fake([
            'app.sandbox.midtrans.com/*' => Http::response(['token' => 'snap-token-123', 'redirect_url' => 'https://example.test/pay'], 201),
        ]);

        $guardian = $this->application->user;

        $this->actingAs($guardian)
            ->post(route('guardian.applications.pay', $this->application), ['channel' => 'midtrans'])
            ->assertRedirect(route('guardian.applications.payment', $this->application));

        return $this->application->payments()->firstOrFail();
    }

    /**
     * Build a notification payload with a valid signature.
     */
    private function notification(Payment $payment, string $status): array
    {
        $payload = [
            'order_id' => $payment->reference,
            'status_code' => '200',
            'gross_amount' => $payment->amount.'.00',
            'transaction_status' => $status,
        ];
        $payload['signature_key'] = hash('sha512', $payload['order_id'].$payload['status_code'].$payload['gross_amount'].'test-server-key');

        return $payload;
    }

    public function test_settlement_notification_settles_the_fee(): void
    {
        $payment = $this->startMidtransPayment();
        $this->assertSame('snap-token-123', $payment->snap_token);

        $response = $this->postJson(route('payments.midtrans.notification'), $this->notification($payment, 'settlement'));

        $response->assertOk();
        $this->assertSame('paid', $payment->fresh()->status);
        $this->application->refresh();
        $this->assertSame('paid', $this->application->form_fee_status);
        $this->assertSame('filling_form', $this->application->status);
    }

    public function test_notification_with_invalid_signature_is_rejected(): void
    {
        $payment = $this->startMidtransPayment();

        $payload = $this->notification($payment, 'settlement');
        $payload['signature_key'] = 'invalid';

        $response = $this->postJson(route('payments.midtrans.notification'), $payload);

        $response->assertStatus(400);
        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertSame('awaiting_form_fee', $this->application->fresh()->status);
    }

    public function test_expire_notification_frees_the_application_to_pay_again(): void
    {
        $payment = $this->startMidtransPayment();

        $response = $this->postJson(route('payments.midtrans.notification'), $this->notification($payment, 'expire'));

        $response->assertOk();
        $this->assertSame('expired', $payment->fresh()->status);
        $this->assertSame('unpaid', $this->application->fresh()->form_fee_status);
    }
}
