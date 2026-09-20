<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('loan_applications')) {
            $columns = Schema::getColumnListing('loan_applications');

            if (!in_array('reviewed_at', $columns)) {
                Schema::table('loan_applications', function (Blueprint $table) {
                    $table->timestamp('reviewed_at')->nullable()->after('status');
                });
            }

            if (!in_array('source_of_income', $columns)) {
                Schema::table('loan_applications', function (Blueprint $table) {
                    $table->string('source_of_income')->nullable()->after('employment_status');
                });
            }

            if (!in_array('birth_date', $columns)) {
                Schema::table('loan_applications', function (Blueprint $table) {
                    $table->date('birth_date')->nullable()->after('address');
                });
            }

            if (!in_array('civil_status', $columns)) {
                Schema::table('loan_applications', function (Blueprint $table) {
                    $table->string('civil_status', 30)->nullable()->after('birth_date');
                });
            }

            if (!in_array('admin_remarks', $columns)) {
                Schema::table('loan_applications', function (Blueprint $table) {
                    $table->text('admin_remarks')->nullable()->after('status');
                });
            }
        }
    }

    public function down(): void
    {
        // No-op — these columns are additive and safe to leave in place
    }
};
