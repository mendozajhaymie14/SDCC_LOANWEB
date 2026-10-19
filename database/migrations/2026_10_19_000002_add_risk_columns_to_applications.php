<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add risk evaluation columns to loan_applications and mirror the
     * two most important ones (recommended_amount, risk_level) to the
     * admin-side applications table so the dashboard risk count works
     * without extra joins.
     */
    public function up(): void
    {
        // Mirror recommended_amount and risk_level to applications if the column
        // does not already exist (idempotent — safe to re-run). The
        // loan_applications risk columns are added by an earlier migration.
        if (!Schema::hasColumn('applications', 'recommended_amount')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->decimal('recommended_amount', 12, 2)->nullable();
                $table->string('risk_level', 20)->default('None')->nullable();
                $table->integer('risk_score')->default(0)->nullable();
                $table->text('risk_factors')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropColumn([
                'recommended_amount',
                'risk_level',
                'risk_score',
                'risk_factors',
            ]);
        });
    }
};