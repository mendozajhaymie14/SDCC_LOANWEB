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
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->decimal('recommended_amount', 12, 2)->nullable();
            $table->string('risk_level', 10)->nullable();
            $table->unsignedSmallInteger('risk_score')->nullable();
            $table->json('risk_factors')->nullable();
            $table->decimal('approved_amount', 12, 2)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn([
                'recommended_amount',
                'risk_level',
                'risk_score',
                'risk_factors',
                'approved_amount',
            ]);
        });
    }
};