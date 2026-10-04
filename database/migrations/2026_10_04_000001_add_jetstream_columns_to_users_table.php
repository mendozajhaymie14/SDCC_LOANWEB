<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * The consolidated users migration (2026_09_29_000001) only carried the
     * columns the app actually uses, so the Jetstream/Fortify scaffolding
     * columns that Laravel's own factories and tests expect were missing.
     * Each column is only added when missing, so this is safe to re-run.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'two_factor_secret')) {
                $table->text('two_factor_secret')->nullable();
            }

            if (!Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->text('two_factor_recovery_codes')->nullable();
            }

            if (!Schema::hasColumn('users', 'profile_photo_path')) {
                $table->string('profile_photo_path', 2048)->nullable();
            }

            if (!Schema::hasColumn('users', 'current_team_id')) {
                $table->foreignId('current_team_id')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'current_team_id')) {
                $table->dropForeign(['current_team_id']);
                $table->dropColumn('current_team_id');
            }
            if (Schema::hasColumn('users', 'profile_photo_path')) {
                $table->dropColumn('profile_photo_path');
            }
            if (Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                $table->dropColumn('two_factor_recovery_codes');
            }
            if (Schema::hasColumn('users', 'two_factor_secret')) {
                $table->dropColumn('two_factor_secret');
            }
        });
    }
};