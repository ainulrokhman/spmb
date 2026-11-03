<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Selection support columns: units opt in to points-based
     * selection, waves may carry a minimum passing score, and
     * every application stores its score snapshot (auto
     * breakdown, manual entries, committee adjustment) plus
     * the denormalized total for ranking lists.
     */
    public function up(): void
    {
        Schema::table('school_units', function (Blueprint $table) {
            $table->boolean('selection_enabled')->default(false)->after('form_fee');
        });

        Schema::table('admission_waves', function (Blueprint $table) {
            $table->unsignedInteger('passing_score')->nullable()->after('announcement_at');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->json('selection_scores')->nullable()->after('answers');
            $table->integer('selection_total')->nullable()->after('selection_scores');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('school_units', function (Blueprint $table) {
            $table->dropColumn('selection_enabled');
        });

        Schema::table('admission_waves', function (Blueprint $table) {
            $table->dropColumn('passing_score');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn(['selection_scores', 'selection_total']);
        });
    }
};
