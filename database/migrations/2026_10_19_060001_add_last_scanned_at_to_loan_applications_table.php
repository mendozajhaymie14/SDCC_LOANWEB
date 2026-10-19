<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add last_scanned_at timestamp column to the loan_applications table.
     * Previous stub migration did not add the column.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('loan_applications', 'last_scanned_at')) {
            Schema::table('loan_applications', function (Blueprint $table) {
                $table->timestamp('last_scanned_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn('last_scanned_at');
        });
    }
};