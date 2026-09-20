<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('applications');

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
            $table->string('status')->default('Pending'); // Approved, Pending, Review, Rejected
            $table->integer('ai_score')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};