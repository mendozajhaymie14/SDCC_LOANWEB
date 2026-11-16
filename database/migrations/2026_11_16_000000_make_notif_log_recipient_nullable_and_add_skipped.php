<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Skipped rows have no resolvable recipient, so recipient_email must be
     * nullable; and the status set must include 'Skipped' (MySQL enum rejects
     * unknown values). Both changes are safe to re-run.
     */
    public function up(): void
    {
        Schema::table('app_notification_logs', function (Blueprint $table) {
            $table->string('recipient_email')->nullable()->change();
            $table->enum('status', ['Sent', 'Failed', 'Skipped'])->default('Sent')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_notification_logs', function (Blueprint $table) {
            $table->string('recipient_email')->nullable(false)->change();
            $table->enum('status', ['Sent', 'Failed'])->default('Sent')->change();
        });
    }
};
