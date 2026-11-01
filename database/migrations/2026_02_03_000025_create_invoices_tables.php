<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Re-registration invoices (Bab 6.4): a frozen snapshot of the
     * unit's fee mapping per application, paid through the same
     * payment channels as the form fee and discountable with the
     * same mechanism.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('total')->default(0);
            $table->string('status')->default('unpaid');
            $table->string('discount_type')->nullable();
            $table->unsignedBigInteger('discount_value')->nullable();
            $table->string('request_kind')->nullable();
            $table->string('request_type')->nullable();
            $table->unsignedBigInteger('request_value')->nullable();
            $table->text('request_reason')->nullable();
            $table->string('request_status')->nullable();
            $table->text('decision_note')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_id')->nullable()->constrained()->nullOnDelete();
            $table->string('component_code');
            $table->string('component_name');
            $table->string('mode');
            $table->unsignedBigInteger('amount');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
        Schema::dropIfExists('invoices');
    }
};
