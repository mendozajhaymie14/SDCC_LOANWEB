<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
            $table->decimal('monthly_income', 12, 2);

            // Loan details
            $table->string('loan_type', 60);
            $table->decimal('amount', 12, 2);
            $table->unsignedSmallInteger('term_months');
            $table->text('purpose');

            // Workflow
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->text('admin_remarks')->nullable();
            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loan_applications');
    }
};