<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (!Schema::hasColumn('applications', 'loan_application_id')) {
                $table->foreignId('loan_application_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('loan_applications')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            if (Schema::hasColumn('applications', 'loan_application_id')) {
                $table->dropConstrainedForeignId('loan_application_id');
            }
        });
    }
};