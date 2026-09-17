<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('borrowers', function (Blueprint $table) {
            $table->id();
            
            // Unique Identifier
            $table->string('borrower_id')->unique(); // e.g., 'BOR-0001'

            // Personal Information
            $table->string('full_name');
            $table->string('email')->unique();
            $table->string('phone_number')->nullable();
            $table->text('address')->nullable();

            // Financial & Scoring Details
            $table->decimal('monthly_income', 12, 2)->default(0.00);
            $table->integer('ai_credit_score')->default(600); // Referenced in UI / Settings
            
            // Status & Verification
            $table->enum('status', ['Active', 'Pending', 'Inactive', 'Blacklisted'])->default('Active');
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('borrowers');
    }
};