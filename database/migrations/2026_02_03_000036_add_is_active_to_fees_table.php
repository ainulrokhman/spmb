<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A per-unit fee mapping can be switched off without
     * deleting it: a disabled component keeps its mode and
     * amounts, but is left out of re-registration invoices
     * until it is switched back on.
     */
    public function up(): void
    {
        Schema::table('fees', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('min_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fees', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
