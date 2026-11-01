<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Assigns committee members (panitia) to the school units they serve.
     * Users with a global role (SuperAdmin, Admin) ignore this pivot and
     * access every unit.
     */
    public function up(): void
    {
        Schema::create('user_school_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('school_unit_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'school_unit_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_school_units');
    }
};
