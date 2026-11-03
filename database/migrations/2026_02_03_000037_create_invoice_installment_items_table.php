<?php

use App\Contracts\InstallmentServiceInterface;
use App\Models\Invoice;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Installment terms gain a per-component breakdown: how
     * much of each term pays which invoice component. Existing
     * plans predate the breakdown, so each existing term is
     * filled greedily per component (the same deterministic
     * allocation the installment service applies to
     * amount-only schedules).
     */
    public function up(): void
    {
        Schema::create('invoice_installment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_installment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('invoice_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->timestamps();

            $table->unique(['invoice_installment_id', 'invoice_item_id']);
        });

        Invoice::query()
            ->whereHas('installments')
            ->chunkById(100, function ($invoices) {
                $service = app(InstallmentServiceInterface::class);

                foreach ($invoices as $invoice) {
                    $service->allocateTermItems($invoice);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_installment_items');
    }
};
