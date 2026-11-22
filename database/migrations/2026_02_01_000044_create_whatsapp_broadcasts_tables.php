<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * WhatsApp broadcast: an admin-composed message sent to many
 * guardians at once. The broadcast row keeps the target filters
 * and running tallies; every recipient gets its own row so the
 * progress survives restarts and a broadcast can be paused,
 * resumed, or cancelled mid-way (structure proven in daily use
 * by the legacy Al-Hikmah and psb-laravel blasters).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_broadcasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_unit_id')->constrained('school_units')->cascadeOnDelete();
            $table->foreignId('admission_wave_id')->nullable()->constrained('admission_waves')->nullOnDelete();
            $table->string('application_status')->nullable();
            $table->string('payment_filter')->nullable();
            $table->text('message');
            $table->unsignedSmallInteger('delay_min')->default(5);
            $table->unsignedSmallInteger('delay_max')->default(15);
            $table->string('status')->default('pending');
            $table->unsignedInteger('total_recipients')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_broadcast_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_broadcast_id')->constrained('whatsapp_broadcasts')->cascadeOnDelete();
            $table->foreignId('application_id')->constrained('applications')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('phone');
            $table->string('child_name');
            $table->string('status')->default('pending');
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_broadcast_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_broadcast_recipients');
        Schema::dropIfExists('whatsapp_broadcasts');
    }
};
