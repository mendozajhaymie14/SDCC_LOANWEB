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
            // Repayment tracking fields for the automated reminder system.
            // These are populated when a loan is approved and adjusted as
            // payments are recorded.
            $table->decimal('remaining_balance', 12, 2)->default(0.00)
                ->after('amount');

            $table->date('next_due_date')->nullable()->after('remaining_balance');

            $table->index('next_due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropIndex(['next_due_date']);
            $table->dropColumn(['remaining_balance', 'next_due_date']);
        });
    }
};
