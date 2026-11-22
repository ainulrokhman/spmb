<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiered quotas and the transfer (pindahan) track:
 * - majors gain a total quota plus optional per-gender quotas
 *   (a programme like Tahfidz can cap boys and girls apart);
 * - unit_quotas caps a whole school unit per academic year,
 *   across all of its waves;
 * - admission tracks can be marked as transfer tracks, and
 *   applications on them carry the target grade (kelas tujuan)
 *   the child transfers into.
 * All quota columns are nullable: null means unlimited.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('majors', function (Blueprint $table) {
            $table->unsignedInteger('quota')->nullable();
            $table->unsignedInteger('quota_putra')->nullable();
            $table->unsignedInteger('quota_putri')->nullable();
        });

        Schema::create('unit_quotas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_unit_id')->constrained('school_units')->cascadeOnDelete();
            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();
            $table->unsignedInteger('quota');
            $table->timestamps();

            $table->unique(['school_unit_id', 'academic_year_id']);
        });

        Schema::table('admission_tracks', function (Blueprint $table) {
            $table->boolean('is_transfer')->default(false);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->unsignedSmallInteger('target_grade')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('target_grade');
        });

        Schema::table('admission_tracks', function (Blueprint $table) {
            $table->dropColumn('is_transfer');
        });

        Schema::dropIfExists('unit_quotas');

        Schema::table('majors', function (Blueprint $table) {
            $table->dropColumn(['quota', 'quota_putra', 'quota_putri']);
        });
    }
};
