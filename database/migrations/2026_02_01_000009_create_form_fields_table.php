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
        Schema::create('form_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_unit_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('label');
            $table->string('type');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->string('section')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('help_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['school_unit_id', 'key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_fields');
    }
};
