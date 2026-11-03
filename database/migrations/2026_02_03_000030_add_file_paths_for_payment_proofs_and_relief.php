<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * File attachments for the payment/relief workflow (Fase 5):
     * a transfer proof the guardian uploads when declaring a
     * manual payment, and an optional supporting document on
     * relief requests. Files live on the private disk; only the
     * path is stored here.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('proof_path')->nullable()->after('reference');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->string('form_fee_request_file_path')->nullable()->after('form_fee_request_reason');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('request_file_path')->nullable()->after('request_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('proof_path');
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('form_fee_request_file_path');
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('request_file_path');
        });
    }
};
