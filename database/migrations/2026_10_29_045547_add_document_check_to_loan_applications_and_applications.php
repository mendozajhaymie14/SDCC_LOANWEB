<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The document_check column holds the advisory document-scan state:
     *   passed | needs_review | manual_review | unavailable
     *
     * It is advisory only — recommended_amount, risk_level and risk_score
     * always come from LoanEvaluationService (rule-based).
     */
    public function up()
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('loan_applications', 'document_check')) {
                $table->string('document_check', 20)
                    ->nullable()
                    ->after('document_processing_status');
            }
        });

        Schema::table('applications', function (Blueprint $table) {
            if (! Schema::hasColumn('applications', 'document_check')) {
                $table->string('document_check', 20)
                    ->nullable()
                    ->after('ai_score');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            if (Schema::hasColumn('loan_applications', 'document_check')) {
                $table->dropColumn('document_check');
            }
        });

        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'document_check')) {
                $table->dropColumn('document_check');
            }
        });
    }
};