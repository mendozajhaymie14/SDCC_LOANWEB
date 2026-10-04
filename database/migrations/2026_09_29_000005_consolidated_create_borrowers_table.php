<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Consolidates the two borrowers-table migrations:
     *   - 2026_09_14_172107_create_borrowers_table.php (original, missing)
     *   - 2026_09_28_000200_create_borrowers_table.php (recreated)
     *
     * Safe to re-run: creates the table only when it is missing, and
     * adds the soft-delete column only when it is missing too.
     */
    public function up(): void
    {
        if (!Schema::hasTable('borrowers')) {
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
                $table->integer('ai_credit_score')->default(600);

                // Status & Verification
                $table->enum('status', ['Active', 'Pending', 'Inactive', 'Blacklisted'])
                      ->default('Active');

                $table->timestamps();
                $table->softDeletes();
            });
        } elseif (!Schema::hasColumn('borrowers', 'deleted_at')) {
            Schema::table('borrowers', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('borrowers');
    }
};