<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Consolidates:
     *   - 0001_01_01_000000_create_users_table.php (base)
     *   - 2026_09_17_085907_add_coop_member_id_to_users_table.php
     *
     * Also adds phone + usertype columns that the User model expects
     * (they were never captured in a dedicated migration).
     *
     * Safe to re-run: creates the table only when it is missing, and
     * adds the soft-delete column only when it is missing too.
     */
    public function up(): void
    {
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();

                // Link to cooperative member registry
                $table->unsignedBigInteger('coop_member_id')->nullable();

                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();

                // Jetstream & Fortify fields
                $table->string('password');
                $table->rememberToken();
                $table->string('two_factor_secret', 16)->nullable();
                $table->string('two_factor_recovery_codes', 100)->nullable();
                $table->string('profile_photo_path', 2048)->nullable();
                $table->foreignId('current_team_id')->nullable();

                // Application-specific fields
                $table->string('phone', 20)->nullable();
                $table->string('usertype', 20)->nullable()->default('user');

                $table->timestamps();
                $table->softDeletes();
            });
        } elseif (!Schema::hasColumn('users', 'deleted_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};