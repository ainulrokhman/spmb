<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The moment a wave's results (Lulus / Tidak Lulus) become
     * visible to guardians (Bab 5). Null = not yet announced.
     */
    public function up(): void
    {
        Schema::table('admission_waves', function (Blueprint $table) {
            $table->timestamp('announcement_at')->nullable()->after('closes_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_waves', function (Blueprint $table) {
            $table->dropColumn('announcement_at');
        });
    }
};
