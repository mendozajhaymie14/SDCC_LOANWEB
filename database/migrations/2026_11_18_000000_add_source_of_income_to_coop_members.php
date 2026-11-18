<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add source_of_income to coop_members so the member's financial profile
     * (declared in the membership application) survives into the coop member
     * record and can be prefilled on the loan application form.
     * Safe to run multiple times via Schema::hasColumn guards.
     */
    public function up(): void
    {
        if (! Schema::hasTable('coop_members')) {
            return;
        }

        Schema::table('coop_members', function (Blueprint $table) {
            if (! Schema::hasColumn('coop_members', 'source_of_income')) {
                $table->string('source_of_income')->nullable()->after('existing_monthly_obligations');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('coop_members')) {
            return;
        }

        Schema::table('coop_members', function (Blueprint $table) {
            if (Schema::hasColumn('coop_members', 'source_of_income')) {
                $table->dropColumn('source_of_income');
            }
        });
    }
};
