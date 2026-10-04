<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Single-purpose migration — no prior versions to consolidate.
     *
     * Safe to re-run: creates the table only when it is missing, and
     * adds the soft-delete column only when it is missing too.
     */
    public function up(): void
    {
        if (!Schema::hasTable('app_notifications')) {
            Schema::create('app_notifications', function (Blueprint $table) {
                $table->id();
                $table->string('app_id');
                $table->string('applicant');
                $table->string('type'); // approved, rejected
                $table->string('message');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        } elseif (!Schema::hasColumn('app_notifications', 'deleted_at')) {
            Schema::table('app_notifications', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app_notifications');
    }
};