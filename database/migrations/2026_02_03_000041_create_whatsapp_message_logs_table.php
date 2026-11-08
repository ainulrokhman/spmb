<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per WhatsApp send attempt: the recipient, the
     * context unit, the device that actually sent (which may be
     * the foundation fallback), the rendered message, and the
     * outcome (sent / failed / skipped). OTP sends never store
     * their message body (the code must not be readable from a
     * log); the OTP flow writes a masked placeholder instead.
     * Rows survive device / unit removal (nullOnDelete) so the
     * report stays a faithful history.
     */
    public function up(): void
    {
        Schema::create('whatsapp_message_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_device_id')->nullable()->constrained('whatsapp_devices')->nullOnDelete();
            $table->foreignId('school_unit_id')->nullable()->constrained('school_units')->nullOnDelete();
            $table->string('recipient_phone');
            $table->string('recipient_name')->nullable();
            $table->string('type', 30);
            $table->text('message')->nullable();
            $table->string('status', 20);
            $table->text('error')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['school_unit_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_logs');
    }
};
