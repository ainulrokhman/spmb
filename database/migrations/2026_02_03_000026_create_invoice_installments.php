<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Installment plans for re-registration invoices: the guardian
     * requests installments with a reason, the committee approves
     * and details the terms (amount + due date per term). Each term
     * is paid as its own polymorphic payment.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('installment_request_reason', 500)->nullable();
            $table->string('installment_request_status', 20)->nullable();
            $table->string('installment_decision_note', 500)->nullable();
            $table->foreignId('installment_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('installment_decided_at')->nullable();
        });

        Schema::create('invoice_installments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence');
            $table->unsignedBigInteger('amount');
            $table->date('due_date');
            $table->string('status', 30)->default('unpaid');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->unique(['invoice_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_installments');

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('installment_decided_by');
            $table->dropColumn([
                'installment_request_reason',
                'installment_request_status',
                'installment_decision_note',
                'installment_decided_at',
            ]);
        });
    }
};
