<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * WhatsApp sender devices: at most one device per school
     * unit, plus one foundation-level device (school_unit_id
     * null) that acts as the shared sender / fallback for units
     * without their own number. A device talks to one gateway
     * provider: GOWA (self-hosted, identified by its device id)
     * or Fonnte (cloud, identified by its device token, stored
     * encrypted). The "one foundation device" rule cannot be a
     * unique index (NULLs compare distinct), so it is enforced
     * by the device management controller.
     */
    public function up(): void
    {
        Schema::create('whatsapp_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_unit_id')->nullable()->unique()->constrained('school_units')->cascadeOnDelete();
            $table->string('provider', 20);
            $table->string('device_id')->nullable();
            $table->text('token')->nullable();
            $table->string('display_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('status', 20)->default('unknown');
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_devices');
    }
};
