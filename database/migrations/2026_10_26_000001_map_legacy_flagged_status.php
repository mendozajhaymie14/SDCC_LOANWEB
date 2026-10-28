<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Map legacy "Flagged" → "pending" in applications table
        // Flagged is a risk indicator (High/Medium/Low), not a workflow status.
        // Pending is the correct workflow state for applications awaiting review.
        DB::table('applications')
            ->whereRaw('LOWER(status) = ?', ['flagged'])
            ->update(['status' => 'Pending', 'updated_at' => now()]);

        // Map legacy "Flagged" → "pending" in loan_applications table
        DB::table('loan_applications')
            ->whereRaw('LOWER(status) = ?', ['flagged'])
            ->update(['status' => 'pending', 'updated_at' => now()]);
    }

    public function down(): void
    {
        // No-op: cannot reliably reverse the mapping
    }
};