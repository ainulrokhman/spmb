<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Programs used to be a hardcoded list of names on the school unit
     * itself; they are master data now. Existing units are mapped to a
     * program row carrying their current program name.
     */
    public function up(): void
    {
        Schema::table('school_units', function (Blueprint $table) {
            $table->foreignId('program_id')->nullable()->after('level')->constrained('programs');
        });

        foreach (DB::table('school_units')->pluck('program', 'id') as $unitId => $programName) {
            $programId = DB::table('programs')->where('name', $programName)->value('id');

            if ($programId === null) {
                $programId = DB::table('programs')->insertGetId([
                    'name' => $programName,
                    'description' => null,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('school_units')->where('id', $unitId)->update(['program_id' => $programId]);
        }

        Schema::table('school_units', function (Blueprint $table) {
            $table->dropColumn('program');
            $table->unsignedBigInteger('program_id')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_units', function (Blueprint $table) {
            $table->string('program')->nullable()->after('level');
        });

        foreach (DB::table('school_units')->join('programs', 'programs.id', '=', 'school_units.program_id')->select('school_units.id', 'programs.name')->get() as $unit) {
            DB::table('school_units')->where('id', $unit->id)->update(['program' => $unit->name]);
        }

        Schema::table('school_units', function (Blueprint $table) {
            $table->dropConstrainedForeignId('program_id');
            $table->string('program')->nullable(false)->change();
        });
    }
};
