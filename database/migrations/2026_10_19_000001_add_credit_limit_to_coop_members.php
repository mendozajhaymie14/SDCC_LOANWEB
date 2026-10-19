<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds credit-limit and financial-position fields to coop_members so
     * the evaluation engine can enforce the real Loan Matrix caps without
     * inventing hard-coded maximums.
     *
     * - approved_credit_limit: the member's approved credit limit (nullable)
     * - share_capital: member's share capital balance (nullable)
     * - savings_balance: member's savings/time deposit balance (nullable)
     * - is_migs: whether the member is a MIGS member (affects Recovery Loan cap)
     *
     * If a member has no approved_credit_limit, evaluation must respond
     * "credit limit not assessed" rather than guess.
     */
    public function up(): void
    {
        Schema::table('coop_members', function (Blueprint $table) {
            $table->decimal('approved_credit_limit', 12, 2)->nullable()->after('user_id');
            $table->decimal('share_capital', 12, 2)->nullable()->after('approved_credit_limit');
            $table->decimal('savings_balance', 12, 2)->nullable()->after('share_capital');
            $table->boolean('is_migs')->default(false)->after('savings_balance');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coop_members', function (Blueprint $table) {
            $table->dropColumn([
                'approved_credit_limit',
                'share_capital',
                'savings_balance',
                'is_migs',
            ]);
        });
    }
};