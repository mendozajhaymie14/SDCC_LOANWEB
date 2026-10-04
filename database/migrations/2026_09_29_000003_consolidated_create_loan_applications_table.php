<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Consolidates every column ever added to loan_applications:
     *   - 2026_09_15_034801_create_loan_applications_table.php (base)
     *   - 2026_09_19_170452_add_app_id_and_ai_score_to_loan_applications_table.php
     *   - 2026_09_20_000000_fix_missing_loan_applications_columns.php
     *   - 2026_09_21_124653_add_collateral_and_share_capital_to_loan_applications_table.php
     *   - 2026_09_21_130059_add_documents_to_loan_applications_table.php
     *
     * Safe to re-run: creates the table only when it is missing, and
     * adds the soft-delete column only when it is missing too.
     */
    public function up(): void
    {
        if (!Schema::hasTable('loan_applications')) {
            Schema::create('loan_applications', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();

                // Reference shown to the member, e.g. SDCC-2026-000014
                $table->string('reference')->unique();

                // Applicant details
                $table->string('full_name');
                $table->string('email');
                $table->string('contact_number', 30);
                $table->text('address');
                $table->date('birth_date')->nullable();
                $table->string('civil_status', 30)->nullable();

                // Employment / income
                $table->string('employment_status', 40);
                $table->string('employer_name')->nullable();
                $table->string('source_of_income')->nullable();
                $table->decimal('monthly_income', 12, 2);

                // Loan details
                $table->string('loan_type', 60);
                $table->decimal('amount', 12, 2);
                $table->decimal('share_capital', 12, 2)->nullable();
                $table->string('collateral', 60)->nullable();
                $table->text('purpose');
                $table->json('documents')->nullable();
                $table->unsignedSmallInteger('term_months');

                // Admin-side mirror fields
                $table->string('app_id')->nullable()->unique();
                $table->integer('ai_score')->nullable();

                // Workflow
                $table->string('status', 20)->default('pending');
                $table->text('admin_remarks')->nullable();
                $table->timestamp('reviewed_at')->nullable();

                $table->timestamps();
                $table->softDeletes();
            });
        } elseif (!Schema::hasColumn('loan_applications', 'deleted_at')) {
            Schema::table('loan_applications', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};