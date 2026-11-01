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
        Schema::table('app_notification_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('recipient_email');
            $table->unsignedBigInteger('loan_application_id')->nullable()->after('user_id');
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('loan_application_id')->references('id')->on('loan_applications')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_notification_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropForeign(['loan_application_id']);
            $table->dropColumn(['user_id', 'loan_application_id']);
        });
    }
};
