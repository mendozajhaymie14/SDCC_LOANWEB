<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\QueryException as DBQueryException;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, check for duplicate loan_application_id values.
        // We include soft-deleted rows (withTrashed) because the UNIQUE index
        // will also cover them — inserting a second row for the same loan would
        // violate the constraint even if the first mirror is soft-deleted.
        $dupes = \App\Models\Application::withTrashed()
            ->select('loan_application_id')
            ->whereNotNull('loan_application_id')
            ->groupBy('loan_application_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('loan_application_id');

        if ($dupes->isNotEmpty()) {
            $lines = [];
            foreach ($dupes as $loanId) {
                $mirrors = \App\Models\Application::withTrashed()
                    ->where('loan_application_id', $loanId)
                    ->orderByDesc('id')
                    ->pluck('id', 'app_id')
                    ->toArray();
                $appIds = implode(', ', array_keys($mirrors));
                $lines[] = "  loan_application_id={$loanId}: mirrors {$appIds}";
            }
            throw new \RuntimeException(
                'Cannot add UNIQUE index on applications.loan_application_id: duplicate ' .
                'mirror rows exist. Clean up duplicates first. Details:' . "\n" . implode("\n", $lines)
            );
        }

        // Check whether the index already exists (idempotent re-run)
        if (! Schema::hasIndex('applications', 'applications_loan_application_id_unique')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->unique('loan_application_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasIndex('applications', 'applications_loan_application_id_unique')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropUnique('applications_loan_application_id_unique');
            });
        }
    }
};