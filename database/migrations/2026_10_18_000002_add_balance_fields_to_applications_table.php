<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The `applications` table mirrors `loan_applications` for the admin
     * side. It needs the same balance/term/due-date columns so the repayment
     * flow (storeRepayment, approve, processDisbursement) can update both
     * tables in a single transaction.
     */
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->integer('term_months')->nullable()->after('amount');
            $table->decimal('remaining_balance', 12, 2)->default(0)->after('term_months');
            $table->date('next_due_date')->nullable()->after('remaining_balance');
            $table->date('disbursement_date')->nullable()->after('next_due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'term_months',
                'remaining_balance',
                'next_due_date',
                'disbursement_date',
            ]);
        });
    }
};