<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql' || $driver === 'mariadb') {
            Schema::table('loan_applications', function (Blueprint $table) {
                if (!\Illuminate\Support\Facades\DB::getSchemaBuilder()->hasIndex('loan_applications', 'loan_applications_status_index')) {
                    $table->index('status', 'loan_applications_status_index');
                }
                if (!\Illuminate\Support\Facades\DB::getSchemaBuilder()->hasIndex('loan_applications', 'loan_applications_status_next_due_date_index')) {
                    $table->index(['status', 'next_due_date'], 'loan_applications_status_next_due_date_index');
                }
            });
        } else {
            Schema::table('loan_applications', function (Blueprint $table) {
                $table->index('status', 'loan_applications_status_index');
                $table->index(['status', 'next_due_date'], 'loan_applications_status_next_due_date_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropIndex('loan_applications_status_index');
            $table->dropIndex('loan_applications_status_next_due_date_index');
        });
    }
};