<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Child statuses (Status Anak) are a shared foundation
     * master: each application carries one status (Reguler,
     * Yatim, Anak Guru, ...) that later drives selection
     * points and fee differentiation.
     */
    public function up(): void
    {
        Schema::create('child_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('child_statuses');
    }
};
