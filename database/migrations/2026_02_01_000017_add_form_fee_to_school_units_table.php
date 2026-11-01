<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The form fee (biaya formulir) is set per school unit; 0 means the
     * unit charges no form fee.
     */
    public function up(): void
    {
        Schema::table('school_units', function (Blueprint $table) {
            $table->unsignedBigInteger('form_fee')->default(0)->after('program_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_units', function (Blueprint $table) {
            $table->dropColumn('form_fee');
        });
    }
};
