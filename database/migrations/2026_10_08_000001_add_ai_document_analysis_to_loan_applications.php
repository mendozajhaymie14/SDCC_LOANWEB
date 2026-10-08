<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('loan_applications', 'ai_analysis_notes')) {
                $table->text('ai_analysis_notes')->nullable()->comment('Summary from AI document analysis');
            }
            if (!Schema::hasColumn('loan_applications', 'extracted_document_data')) {
                $table->json('extracted_document_data')->nullable()->comment('Structured extraction from Gemini');
            }
            if (!Schema::hasColumn('loan_applications', 'document_processing_status')) {
                $table->string('document_processing_status', 20)->default('pending')->comment('pending|processing|completed|failed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->dropColumn([
                'ai_score',
                'ai_analysis_notes',
                'extracted_document_data',
                'document_processing_status',
            ]);
        });
    }
};