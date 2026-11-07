<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('member_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('member_applications', 'monthly_income')) {
                $table->decimal('monthly_income', 15, 2)->nullable()->after('zip_code');
            }
            if (!Schema::hasColumn('member_applications', 'existing_monthly_obligations')) {
                $table->decimal('existing_monthly_obligations', 15, 2)->nullable()->after('monthly_income');
            }
        });
    }

    public function down(): void
    {
        Schema::table('member_applications', function (Blueprint $table) {
            if (Schema::hasColumn('member_applications', 'existing_monthly_obligations')) {
                $table->dropColumn('existing_monthly_obligations');
            }
            if (Schema::hasColumn('member_applications', 'monthly_income')) {
                $table->dropColumn('monthly_income');
            }
        });
    }
};