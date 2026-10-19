<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The reject flow writes admin_remarks to the applications table,
     * but this column was only present in loan_applications. Mirror it
     * to the admin-side applications table.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('applications', 'admin_remarks')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->text('admin_remarks')->nullable()->after('approved_amount');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn('admin_remarks');
        });
    }
};