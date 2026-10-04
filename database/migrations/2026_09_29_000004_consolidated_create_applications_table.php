<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Consolidates the four applications-table migrations into one:
     *   - 2026_09_12_182312_create_applications_table.php (initial)
     *   - 2026_09_15_112156_add_loan_application_id_to_applications_table.php
     *   - 2026_09_19_170548_drop_applications_table.php (drop + rework)
     *   - 2026_09_20_000100_recreate_applications_table.php
     *   - 2026_09_28_000100_create_applications_table.php (final)
     *
     * Safe to re-run: creates the table only when it is missing, and
     * adds the soft-delete column only when it is missing too.
     */
    public function up(): void
    {
        if (!Schema::hasTable('applications')) {
            Schema::create('applications', function (Blueprint $table) {
                $table->id();
                $table->string('app_id')->unique(); // e.g. APP-0001
                $table->foreignId('loan_application_id')
                    ->nullable()
                    ->constrained('loan_applications')
                    ->nullOnDelete();
                $table->string('applicant');
                $table->string('loan_type');
                $table->decimal('amount', 12, 2);
                $table->string('status')->default('Pending');
                $table->integer('ai_score')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        } elseif (!Schema::hasColumn('applications', 'deleted_at')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};