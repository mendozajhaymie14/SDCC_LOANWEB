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
        Schema::table('app_notifications', function (Blueprint $table) {
            // Extend the notification system with payment reminder fields.
            // Keeping existing columns intact for backward compatibility.

            $table->foreignId('user_id')->nullable()->after('applicant')
                ->constrained()->cascadeOnDelete();

            $table->foreignId('loan_application_id')->nullable()->after('user_id')
                ->constrained()->cascadeOnDelete();

            $table->string('title', 255)->nullable()->after('loan_application_id');

            $table->boolean('is_read')->nullable()->default(false)->after('title');

            $table->timestamp('sent_at')->nullable()->after('is_read');

            // Make legacy columns nullable so reminder notifications don't
            // require app_id/applicant values.
            $table->string('app_id')->nullable()->change();
            $table->string('applicant')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_notifications', function (Blueprint $table) {
            $table->dropColumn([
                'user_id',
                'loan_application_id',
                'title',
                'is_read',
                'sent_at',
            ]);
        });
    }
};
