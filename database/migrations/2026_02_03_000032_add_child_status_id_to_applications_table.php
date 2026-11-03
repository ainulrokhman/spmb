<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every application carries one child status (Status
     * Anak) chosen by the guardian at biodata time and
     * verified by the committee. Null means the default
     * (Reguler) treatment.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('child_status_id')
                ->nullable()
                ->after('guardian_relation')
                ->constrained('child_statuses')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('child_status_id');
        });
    }
};
