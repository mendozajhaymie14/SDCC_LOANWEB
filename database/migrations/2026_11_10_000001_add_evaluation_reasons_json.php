<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds evaluation_reasons JSON column to loan_applications and applications
     * so the full reasons array (not just risk_factors/details) is persisted
     * at submission time and available to the modal/document-check flow.
     */
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_applications', 'evaluation_reasons')) {
                $table->json('evaluation_reasons')->nullable()->after('risk_factors');
            }
        });

        Schema::table('applications', function (Blueprint $table) {
            if (!Schema::hasColumn('applications', 'evaluation_reasons')) {
                $table->json('evaluation_reasons')->nullable()->after('risk_factors');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            if (Schema::hasColumn('loan_applications', 'evaluation_reasons')) {
                $table->dropColumn('evaluation_reasons');
            }
        });

        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'evaluation_reasons')) {
                $table->dropColumn('evaluation_reasons');
            }
        });
    }
};