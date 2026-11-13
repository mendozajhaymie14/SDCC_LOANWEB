<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds approved_monthly_credit_limit and monthly_credit_limit_source to coop_members.
     * monthly_credit_limit_source is 'auto' (computed at approval) or 'manual' (staff-edited).
     * Safe to run multiple times via Schema::hasColumn guards.
     *
     * Do NOT run config:cache after this migration on the real sdcc database;
     * the code guards column access with Schema::hasColumn().
     */
    public function up(): void
    {
        Schema::table('coop_members', function (Blueprint $table) {
            if (!Schema::hasColumn('coop_members', 'approved_monthly_credit_limit')) {
                $table->decimal('approved_monthly_credit_limit', 12, 2)->nullable()->after('approved_credit_limit');
            }
            if (!Schema::hasColumn('coop_members', 'monthly_credit_limit_source')) {
                $table->string('monthly_credit_limit_source')->nullable()->after('approved_monthly_credit_limit');
            }
        });

        // Backfill: where approved_credit_limit > 0 and monthly_income is set,
        // compute auto monthly limit in PHP (GREATEST/?? are MySQL-only syntax).
        DB::table('coop_members')
            ->whereNotNull('approved_credit_limit')
            ->where('approved_credit_limit', '>', 0)
            ->whereNotNull('monthly_income')
            ->where('monthly_income', '>', 0)
            ->orderBy('id')
            ->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    $income = (float) $row->monthly_income;
                    $obligations = (float) ($row->existing_monthly_obligations ?? 0);
                    $capacity = max(0, $income * 0.35 - $obligations);
                    DB::table('coop_members')
                        ->where('id', $row->id)
                        ->update([
                            'approved_monthly_credit_limit' => $capacity,
                            'monthly_credit_limit_source'   => 'auto',
                        ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coop_members', function (Blueprint $table) {
            if (Schema::hasColumn('coop_members', 'monthly_credit_limit_source')) {
                $table->dropColumn('monthly_credit_limit_source');
            }
            if (Schema::hasColumn('coop_members', 'approved_monthly_credit_limit')) {
                $table->dropColumn('approved_monthly_credit_limit');
            }
        });
    }
};