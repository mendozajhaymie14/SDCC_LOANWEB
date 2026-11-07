<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Records which staff user approved each loan application. Nullable so
     * existing approved rows can be backfilled later via sdcc:backfill-approvers.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('applications', 'approved_by')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('admin_remarks');
            });
        }

        if (!Schema::hasColumn('loan_applications', 'approved_by')) {
            Schema::table('loan_applications', function (Blueprint $table) {
                $table->unsignedBigInteger('approved_by')->nullable()->after('reviewed_at');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('applications', 'approved_by')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropColumn('approved_by');
            });
        }

        if (Schema::hasColumn('loan_applications', 'approved_by')) {
            Schema::table('loan_applications', function (Blueprint $table) {
                $table->dropColumn('approved_by');
            });
        }
    }
};
