<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One application is one child registered by a guardian. It walks
     * the strict step order: form fee first, then the form, then the
     * documents, then committee verification (Fase 3).
     */
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_unit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_wave_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_track_id')->constrained()->cascadeOnDelete();
            $table->foreignId('major_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('academic_year_id')->constrained()->cascadeOnDelete();

            // Core child identity (fixed columns by design).
            $table->string('child_name')->nullable();
            $table->string('birth_place')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('gender', 1)->nullable();
            $table->string('parent_name')->nullable();
            $table->string('parent_phone')->nullable();

            // Guardian relation and parent identities (structured JSON).
            $table->string('guardian_relation')->nullable();
            $table->json('father_data')->nullable();
            $table->json('mother_data')->nullable();
            $table->boolean('has_guardian')->default(false);
            $table->json('guardian_data')->nullable();

            // Dynamic form answers (JSON, keyed by form field key).
            $table->json('answers')->nullable();

            // Form fee snapshot and payment state.
            $table->unsignedBigInteger('form_fee_amount')->default(0);
            $table->string('form_fee_status')->default('unpaid');
            $table->string('form_fee_discount_type')->nullable();
            $table->unsignedBigInteger('form_fee_discount_value')->nullable();
            $table->string('form_fee_request_kind')->nullable();
            $table->string('form_fee_request_type')->nullable();
            $table->unsignedBigInteger('form_fee_request_value')->nullable();
            $table->text('form_fee_request_reason')->nullable();
            $table->string('form_fee_request_status')->nullable();
            $table->text('form_fee_decision_note')->nullable();
            $table->foreignId('form_fee_decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('form_fee_decided_at')->nullable();

            // Flow state.
            $table->string('status')->default('awaiting_form_fee');
            $table->string('registration_number')->nullable()->unique();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
