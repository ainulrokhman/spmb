<?php

namespace Tests\Feature;

use App\Contracts\InstallmentServiceInterface;
use App\Contracts\InvoiceServiceInterface;
use App\Models\AcademicYear;
use App\Models\AdmissionTrack;
use App\Models\AdmissionWave;
use App\Models\Application;
use App\Models\Fee;
use App\Models\FeeAmount;
use App\Models\FeeComponent;
use App\Models\Invoice;
use App\Models\InvoiceInstallment;
use App\Models\InvoiceItem;
use App\Models\SchoolUnit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Concerns\SeedsDemoUsers;
use Tests\TestCase;

/**
 * Fase 6: installment terms are detailed per fee component
 * (a term × component matrix). Each component's slices over
 * all terms must total its payable share — the invoice item
 * amount with the invoice discount spread pro rata. Terms
 * can be added during a revision; settled terms and terms
 * with a payment in flight are locked with their breakdowns.
 */
class InstallmentBreakdownTest extends TestCase
{
    use RefreshDatabase, SeedsDemoUsers;

    private User $guardian;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedDemoUsers();
        $this->guardian = User::factory()->create(['phone' => '081200006666']);
        $this->guardian->assignRole('Wali');
    }

    private function installments(): InstallmentServiceInterface
    {
        return app(InstallmentServiceInterface::class);
    }

    /**
     * A passed application on a fresh unit with two fixed
     * components (SPI Rp600.000 + SOP Rp400.000) and its
     * frozen invoice.
     *
     * @return array{application: Application, invoice: Invoice, spi: InvoiceItem, sop: InvoiceItem}
     */
    private function passedWithTwoComponents(string $unitCode): array
    {
        $year = AcademicYear::firstOrCreate(['name' => '2026/2027'], ['is_active' => true]);
        $unit = SchoolUnit::factory()->create(['name' => 'Unit Matriks '.$unitCode, 'code' => $unitCode]);
        $track = AdmissionTrack::factory()->create(['school_unit_id' => $unit->id]);
        $wave = AdmissionWave::factory()->create([
            'school_unit_id' => $unit->id,
            'academic_year_id' => $year->id,
            'announcement_at' => now()->subHour(),
        ]);
        $wave->admissionTracks()->attach($track);

        foreach ([['SPI', 'Sumbangan Pengembangan Institusi', 600000], ['SOP', 'Sumbangan Operasional Pendidikan', 400000]] as [$code, $name, $amount]) {
            $component = FeeComponent::factory()->create(['code' => $code.$unitCode, 'name' => $name]);
            $fee = Fee::factory()->create(['school_unit_id' => $unit->id, 'fee_component_id' => $component->id, 'mode' => 'fixed']);
            FeeAmount::factory()->create(['fee_id' => $fee->id, 'amount' => $amount]);
        }

        $application = Application::factory()->create([
            'user_id' => $this->guardian->id,
            'school_unit_id' => $unit->id,
            'admission_wave_id' => $wave->id,
            'admission_track_id' => $track->id,
            'academic_year_id' => $year->id,
            'status' => 'passed',
        ]);

        $invoice = app(InvoiceServiceInterface::class)->createInvoice($application, []);
        $this->assertSame(1000000, $invoice->total);

        $items = $invoice->items->keyBy('component_code');

        return [
            'application' => $application,
            'invoice' => $invoice,
            'spi' => $items['SPI'.$unitCode],
            'sop' => $items['SOP'.$unitCode],
        ];
    }

    /**
     * The breakdown of one term as an item-id => amount map.
     *
     * @return array<int, int>
     */
    private function breakdownOf(InvoiceInstallment $installment): array
    {
        $breakdown = $installment->fresh()->items
            ->mapWithKeys(fn ($row) => [$row->invoice_item_id => $row->amount])
            ->all();
        ksort($breakdown);

        return $breakdown;
    }

    /**
     * Canonicalize an expected breakdown the same way.
     *
     * @param  array<int, int>  $pairs
     * @return array<int, int>
     */
    private function sorted(array $pairs): array
    {
        ksort($pairs);

        return $pairs;
    }

    public function test_component_shares_spread_an_invoice_discount_pro_rata(): void
    {
        $invoice = Invoice::factory()->create(['total' => 300, 'status' => 'unpaid', 'discount_type' => 'nominal', 'discount_value' => 1]);
        $ids = collect([100, 100, 100])->map(fn ($amount) => InvoiceItem::factory()->create(['invoice_id' => $invoice->id, 'amount' => $amount])->id)->all();

        // Payable 299: floors are 99 each, the two leftover
        // rupiah go to the earliest items (equal remainders).
        $this->assertSame([$ids[0] => 100, $ids[1] => 100, $ids[2] => 99], $invoice->componentPayableShares());

        $invoice2 = Invoice::factory()->create(['total' => 1000000, 'status' => 'unpaid', 'discount_type' => 'percent', 'discount_value' => 10]);
        $big = InvoiceItem::factory()->create(['invoice_id' => $invoice2->id, 'amount' => 600000]);
        $small = InvoiceItem::factory()->create(['invoice_id' => $invoice2->id, 'amount' => 400000]);

        $this->assertSame([$big->id => 540000, $small->id => 360000], $invoice2->componentPayableShares());
    }

    public function test_approval_with_matrix_stores_the_breakdown(): void
    {
        ['invoice' => $invoice, 'spi' => $spi, 'sop' => $sop] = $this->passedWithTwoComponents('61');

        $this->installments()->requestInstallment($invoice, 'Minta cicilan');
        $this->installments()->decideInstallmentRequest($invoice->fresh(), true, [
            ['due_date' => now()->addDays(7)->toDateString(), 'items' => [$spi->id => 300000, $sop->id => 400000]],
            ['due_date' => now()->addDays(37)->toDateString(), 'items' => [$spi->id => 300000]],
        ], $this->superAdmin(), null);

        [$first, $second] = $invoice->fresh()->installments;
        $this->assertSame(700000, $first->amount);
        $this->assertSame(300000, $second->amount);
        $this->assertSame($this->sorted([$spi->id => 300000, $sop->id => 400000]), $this->breakdownOf($first));
        $this->assertSame([$spi->id => 300000], $this->breakdownOf($second));
    }

    public function test_matrix_component_mismatch_is_rejected(): void
    {
        ['invoice' => $invoice, 'spi' => $spi, 'sop' => $sop] = $this->passedWithTwoComponents('62');

        $this->installments()->requestInstallment($invoice, 'Minta cicilan');

        try {
            $this->installments()->decideInstallmentRequest($invoice->fresh(), true, [
                ['due_date' => now()->addDays(7)->toDateString(), 'items' => [$spi->id => 500000, $sop->id => 400000]],
                ['due_date' => now()->addDays(37)->toDateString(), 'items' => [$spi->id => 50000]],
            ], $this->superAdmin(), null);
            $this->fail('Component mismatch should have been rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('komponen', $e->getMessage());
            $this->assertStringContainsString('Rp 600.000', $e->getMessage());
        }

        $this->assertSame(0, $invoice->fresh()->installments()->count());
        $this->assertSame('pending', $invoice->fresh()->installment_request_status);
    }

    public function test_revision_adds_a_term_and_resequences_by_due_date(): void
    {
        ['invoice' => $invoice, 'spi' => $spi, 'sop' => $sop] = $this->passedWithTwoComponents('63');

        $this->installments()->requestInstallment($invoice, 'Minta cicilan');
        $this->installments()->decideInstallmentRequest($invoice->fresh(), true, [
            ['due_date' => now()->addDays(30)->toDateString(), 'items' => [$spi->id => 600000, $sop->id => 200000]],
            ['due_date' => now()->addDays(60)->toDateString(), 'items' => [$sop->id => 200000]],
        ], $this->superAdmin(), null);

        [$first, $second] = $invoice->fresh()->installments;

        // Add an earlier third term funded by shrinking the two
        // unpaid ones; sequences follow the due dates.
        $this->installments()->reviseSchedule($invoice->fresh(), [
            $first->id => ['due_date' => now()->addDays(30)->toDateString(), 'items' => [$spi->id => 400000, $sop->id => 200000]],
            $second->id => ['due_date' => now()->addDays(60)->toDateString(), 'items' => [$sop->id => 100000]],
        ], [
            ['due_date' => now()->addDays(10)->toDateString(), 'items' => [$spi->id => 200000, $sop->id => 100000]],
        ]);

        $terms = $invoice->fresh()->installments;
        $this->assertCount(3, $terms);
        $this->assertSame([300000, 600000, 100000], $terms->pluck('amount')->all());
        $this->assertSame([1, 2, 3], $terms->pluck('sequence')->all());
        $this->assertSame($this->sorted([$spi->id => 200000, $sop->id => 100000]), $this->breakdownOf($terms[0]));
        $this->assertSame($this->sorted([$spi->id => 400000, $sop->id => 200000]), $this->breakdownOf($terms[1]));
        $this->assertSame([$sop->id => 100000], $this->breakdownOf($terms[2]));
    }

    public function test_paid_and_in_flight_terms_are_locked_with_their_breakdowns(): void
    {
        ['invoice' => $invoice, 'spi' => $spi, 'sop' => $sop] = $this->passedWithTwoComponents('64');

        $this->installments()->requestInstallment($invoice, 'Minta cicilan');
        $this->installments()->decideInstallmentRequest($invoice->fresh(), true, [
            ['due_date' => now()->addDays(7)->toDateString(), 'items' => [$spi->id => 300000, $sop->id => 400000]],
            ['due_date' => now()->addDays(37)->toDateString(), 'items' => [$spi->id => 300000]],
        ], $this->superAdmin(), null);

        [$first, $second] = $invoice->fresh()->installments;

        // Settle the first term through the manual channel.
        $payments = app(\App\Services\PaymentService::class);
        $result = $payments->startInstallmentPayment($first->fresh(), 'manual');
        $payments->declareManualPaid($result['payment']);
        $payments->confirmPayment($result['payment']->fresh());
        $this->assertSame('paid', $first->fresh()->status);

        // The paid term may not appear in a revision at all.
        try {
            $this->installments()->reviseSchedule($invoice->fresh(), [
                $first->id => ['due_date' => now()->addDays(7)->toDateString(), 'items' => [$spi->id => 300000, $sop->id => 400000]],
                $second->id => ['due_date' => now()->addDays(37)->toDateString(), 'items' => [$spi->id => 300000]],
            ]);
            $this->fail('Paid term revision should have been rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('sudah lunas', $e->getMessage());
        }

        // The remaining term must still cover exactly what the
        // locked term did not: SPI 300.000 and no SOP at all.
        try {
            $this->installments()->reviseSchedule($invoice->fresh(), [
                $second->id => ['due_date' => now()->addDays(37)->toDateString(), 'items' => [$spi->id => 250000, $sop->id => 50000]],
            ]);
            $this->fail('Component mismatch against the locked share should have been rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('harus', $e->getMessage());
        }

        // A term with a payment in flight is locked as well.
        ['invoice' => $invoice2, 'spi' => $spi2, 'sop' => $sop2] = $this->passedWithTwoComponents('65');
        $this->installments()->requestInstallment($invoice2, 'Minta cicilan');
        $this->installments()->decideInstallmentRequest($invoice2->fresh(), true, [
            ['due_date' => now()->addDays(7)->toDateString(), 'items' => [$spi2->id => 600000, $sop2->id => 400000]],
        ], $this->superAdmin(), null);

        $only = $invoice2->fresh()->installments->first();
        $payments->startInstallmentPayment($only->fresh(), 'manual');
        $this->assertSame('awaiting_payment', $only->fresh()->status);

        try {
            $this->installments()->reviseSchedule($invoice2->fresh(), [
                $only->id => ['due_date' => now()->addDays(9)->toDateString(), 'items' => [$spi2->id => 600000, $sop2->id => 400000]],
            ]);
            $this->fail('In-flight term revision should have been rejected.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('menunggu pembayaran/konfirmasi', $e->getMessage());
        }
    }

    public function test_allocate_term_items_backfills_a_plan_without_breakdown(): void
    {
        ['invoice' => $invoice, 'spi' => $spi, 'sop' => $sop] = $this->passedWithTwoComponents('66');

        $first = InvoiceInstallment::factory()->create(['invoice_id' => $invoice->id, 'sequence' => 1, 'amount' => 700000, 'status' => 'unpaid']);
        $second = InvoiceInstallment::factory()->create(['invoice_id' => $invoice->id, 'sequence' => 2, 'amount' => 300000, 'status' => 'unpaid']);

        $this->installments()->allocateTermItems($invoice->fresh());

        // Greedy in invoice item order: the first term fills
        // the first item's share completely before touching
        // the next component.
        [$itemA, $itemB] = $invoice->items()->orderBy('id')->get()->all();
        $this->assertSame($this->sorted([$itemA->id => $itemA->amount, $itemB->id => 700000 - $itemA->amount]), $this->breakdownOf($first));
        $this->assertSame([$itemB->id => $itemB->amount - (700000 - $itemA->amount)], $this->breakdownOf($second));

        // Idempotent: a second run changes nothing.
        $this->installments()->allocateTermItems($invoice->fresh());
        $this->assertSame($this->sorted([$itemA->id => $itemA->amount, $itemB->id => 700000 - $itemA->amount]), $this->breakdownOf($first));
    }

    public function test_http_matrix_approval_and_guardian_breakdown_display(): void
    {
        ['application' => $application, 'invoice' => $invoice, 'spi' => $spi, 'sop' => $sop] = $this->passedWithTwoComponents('67');

        $this->actingAs($this->guardian)
            ->post(route('guardian.invoices.request-installment', $application), ['reason' => 'Dibayar mengikuti gaji bulanan'])
            ->assertSessionHas('success');

        $this->actingAs($this->superAdmin())
            ->post(route('admin.invoices.decide-installment', $invoice), [
                'decision' => 'approve',
                'installments' => [
                    ['due_date' => now()->addDays(7)->toDateString(), 'items' => [$spi->id => 300000, $sop->id => 400000]],
                    ['due_date' => now()->addDays(37)->toDateString(), 'items' => [$spi->id => 300000]],
                ],
            ])
            ->assertSessionHas('success');

        $this->assertCount(2, $invoice->fresh()->installments);

        // The guardian sees what each term pays for.
        $this->actingAs($this->guardian)
            ->get(route('guardian.invoices.show', $application))
            ->assertOk()
            ->assertSee('Sumbangan Pengembangan Institusi: Rp 300.000', false)
            ->assertSee('Sumbangan Operasional Pendidikan: Rp 400.000', false);

        // The committee revision form renders the matrix.
        $this->actingAs($this->superAdmin())
            ->get(route('admin.invoices.show', $invoice))
            ->assertOk()
            ->assertSee('Total per Komponen')
            ->assertSee('Tambah Termin');
    }
}
