<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Shared guardian access: additional guardian accounts (e.g. a
     * parent account created from the application form) that may
     * view and continue this application alongside its owner.
     */
    public function up(): void
    {
        Schema::create('application_access', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('relation', 20);
            $table->timestamps();

            $table->unique(['application_id', 'user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('application_access');
    }
};
