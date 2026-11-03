<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Selection rules (Aturan Seleksi) are configured per
     * school unit: each rule knows how to award points from
     * application data (age brackets, answer mappings, origin
     * school, declared siblings, child status) or, for the
     * 'manual' kind, the maximum points the committee may
     * enter by hand. The kind-specific settings live in the
     * config JSON.
     */
    public function up(): void
    {
        Schema::create('selection_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_unit_id')->constrained()->cascadeOnDelete();
            $table->string('kind');
            $table->string('name');
            $table->json('config')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('selection_rules');
    }
};
