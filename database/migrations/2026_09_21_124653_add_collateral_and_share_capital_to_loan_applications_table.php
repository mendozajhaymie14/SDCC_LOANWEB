<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_applications', 'share_capital')) {
                $table->decimal('share_capital', 12, 2)->nullable()->after('amount');
            }

            if (!Schema::hasColumn('loan_applications', 'collateral')) {
                $table->string('collateral', 60)->nullable()->after('share_capital');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            if (Schema::hasColumn('loan_applications', 'collateral')) {
                $table->dropColumn('collateral');
            }
            if (Schema::hasColumn('loan_applications', 'share_capital')) {
                $table->dropColumn('share_capital');
            }
        });
    }
};