<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('admission_wave_track', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_wave_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_track_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['admission_wave_id', 'admission_track_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_wave_track');
    }
};
