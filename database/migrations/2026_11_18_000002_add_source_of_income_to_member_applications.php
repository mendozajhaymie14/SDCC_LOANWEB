<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add source_of_income to member_applications so the member's declared
     * source of income survives into the coop member record on approval
     * (see AdminController::approveMember, which reads
     * $application->source_of_income). Without this column every approval
     * whose application carried a source of income failed with
     * "table member_applications has no column named source_of_income".
     * Safe to run multiple times via Schema::hasColumn guards.
     */
    public function up(): void
    {
        if (! Schema::hasTable('member_applications')) {
            return;
        }

        Schema::table('member_applications', function (Blueprint $table) {
            if (! Schema::hasColumn('member_applications', 'source_of_income')) {
                $table->string('source_of_income')->nullable()->after('existing_monthly_obligations');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('member_applications')) {
            return;
        }

        Schema::table('member_applications', function (Blueprint $table) {
            if (Schema::hasColumn('member_applications', 'source_of_income')) {
                $table->dropColumn('source_of_income');
            }
        });
    }
};