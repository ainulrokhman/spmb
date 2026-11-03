<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * A wave may announce results per admission track: when a
     * track's pivot announcement time is filled it overrides the
     * wave's global announcement time for that track's
     * applications; empty means "follow the global time".
     */
    public function up(): void
    {
        Schema::table('admission_wave_track', function (Blueprint $table) {
            $table->timestamp('announcement_at')->nullable()->after('admission_track_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admission_wave_track', function (Blueprint $table) {
            $table->dropColumn('announcement_at');
        });
    }
};
