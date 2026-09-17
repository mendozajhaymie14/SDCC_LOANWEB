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
        Schema::create('applications', function (Blueprint $table) {
        $table->id();
        $table->string('app_id')->unique(); // e.g. #SCC-20891
        $table->string('applicant');
        $table->string('loan_type');
        $table->decimal('amount', 12, 2);
        $table->string('status')->default('Pending'); // Approved, Pending, Review, Rejected
        $table->integer('ai_score')->nullable();
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
