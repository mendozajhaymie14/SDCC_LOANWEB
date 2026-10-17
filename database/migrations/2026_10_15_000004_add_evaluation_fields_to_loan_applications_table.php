<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            // For DTI calculation in CreditScoringService::evaluateCapacity()
            if (!Schema::hasColumn('loan_applications', 'existing_monthly_obligations')) {
                $table->decimal('existing_monthly_obligations', 12, 2)
                    ->default(0)
                    ->after('monthly_income')
                    ->comment('Existing monthly debt obligations for DTI calculation');
            }

            // Purpose of loan dropdown (Educational, Business, Personal, Emergency)
            if (!Schema::hasColumn('loan_applications', 'purpose_of_loan')) {
                $table->string('purpose_of_loan', 50)
                    ->nullable()
                    ->after('loan_type')
                    ->comment('Purpose category: Educational, Business, Personal, Emergency');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn(['existing_monthly_obligations', 'purpose_of_loan']);
        });
    }
};