<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fee amounts gain a child-status dimension: an amount
     * may be restricted to one child status (Yatim, Anak
     * Guru, ...) so re-registration fees differ by the
     * applicant's verified status. Null means all statuses.
     */
    public function up(): void
    {
        Schema::table('fee_amounts', function (Blueprint $table) {
            $table->foreignId('child_status_id')
                ->nullable()
                ->after('admission_track_id')
                ->constrained('child_statuses')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_amounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('child_status_id');
        });
    }
};
