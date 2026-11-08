<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds credit-limit source tracking columns to coop_members.
     * The migration uses Schema::hasColumn guards so it is safe to run
     * multiple times and is compatible with SQLite (no MySQL-specific syntax).
     *
     * If a member has an approved_credit_limit > 0, the source is set to 'manual'.
     * Rows where approved_credit_limit is null or 0 remain untouched.
     *
     * Do NOT run config:cache after this migration on the real sdcc database;
     * the code guards column access with Schema::hasColumn().
     */
    public function up(): void
    {
        Schema::table('coop_members', function (Blueprint $table) {
            if (!Schema::hasColumn('coop_members', 'credit_limit_source')) {
                $table->string('credit_limit_source')->nullable()->after('approved_credit_limit');
            }
            if (!Schema::hasColumn('coop_members', 'credit_limit_computed_at')) {
                $table->timestamp('credit_limit_computed_at')->nullable()->after('credit_limit_source');
            }
        });

        // Backfill: where approved_credit_limit is not null and > 0, set source = 'manual'
        // (approved_credit_limit is nullable decimal with no default 0, so a stored 0
        // is possible; we only backfill rows > 0 to avoid overwriting a legitimate 0).
        DB::table('coop_members')
            ->whereNotNull('approved_credit_limit')
            ->where('approved_credit_limit', '>', 0)
            ->update(['credit_limit_source' => 'manual']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coop_members', function (Blueprint $table) {
            // Safe: only drop if the columns exist
            if (Schema::hasColumn('coop_members', 'credit_limit_computed_at')) {
                $table->dropColumn('credit_limit_computed_at');
            }
            if (Schema::hasColumn('coop_members', 'credit_limit_source')) {
                $table->dropColumn('credit_limit_source');
            }
        });
    }
};