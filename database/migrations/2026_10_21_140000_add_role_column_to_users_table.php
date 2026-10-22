<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds `users.role` — the paper-facing role column for the five RBAC roles
 * (admin / branch_manager / loan_officer / credit_investigator / member).
 *
 * `users.usertype` is kept intact for compatibility (it still carries the
 * membership status: 'member' = linked to an active coop_members row,
 * 'user' = registered but not yet a member). `role` mirrors the effective
 * permission role so the permission matrix reads `role` directly, with a
 * usertype fallback for any row written before this migration.
 *
 * Registered non-members keep the 'user' role rather than being collapsed to
 * 'member': mapping them to 'member' would grant them loan-application rights
 * before membership approval, breaking the register-flow gate that the
 * LoanApplyGateTest exercises.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('users')) return;

        if (! Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role', 20)->nullable();
            });
        }

        if ($this->needsBackfill()) {
            DB::table('users')
                ->whereNull('role')
                ->update(['role' => DB::raw("COALESCE(usertype, 'user')")]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'role')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('role');
            });
        }
    }

    private function needsBackfill(): bool
    {
        return DB::select("SELECT COUNT(*) AS count FROM \`users\` WHERE \`role\` IS NULL")[0]->count > 0;
    }
};
