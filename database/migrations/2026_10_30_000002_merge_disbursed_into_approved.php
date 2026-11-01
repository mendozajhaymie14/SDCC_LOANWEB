<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Merge 'disbursed' status into 'approved' in both applications tables.
     *
     * This is a DATA-ONLY migration. No schema changes.
     * Run against sdcc_scratch (--env=scratch) only.
     */
    public function up(): void
    {
        // Merge in admin applications table (stores PascalCase)
        DB::table('applications')
            ->whereIn('status', ['Disbursed', 'disbursed'])
            ->update(['status' => 'Approved']);

        // Merge in member loan_applications table (stores lowercase)
        DB::table('loan_applications')
            ->where('status', 'disbursed')
            ->update(['status' => 'approved']);
    }

    /**
     * Partial rollback — only recover rows that had a recorded disbursement_date.
     * Loans approved but never disbursed cannot be distinguished post-merge.
     */
    public function down(): void
    {
        DB::table('applications')
            ->where('status', 'Approved')
            ->whereNotNull('disbursement_date')
            ->update(['status' => 'Disbursed']);

        DB::table('loan_applications')
            ->where('status', 'approved')
            ->whereNotNull('disbursement_date')
            ->update(['status' => 'disbursed']);
    }
};