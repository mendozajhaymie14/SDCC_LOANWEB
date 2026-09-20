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
        $table->string('app_id')->nullable()->unique()->after('id');
        $table->integer('ai_score')->nullable()->after('status');
    });
}

public function down(): void
{
    Schema::table('loan_applications', function (Blueprint $table) {
        $table->dropColumn(['app_id', 'ai_score']);
    });
}
};
