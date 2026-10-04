<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix column types added by 2026_10_04_000001:
     *   - two_factor_secret was VARCHAR(16) but Fortify stores base64 JSON (~150 chars)
     *   - two_factor_recovery_codes was VARCHAR(100) but stores JSON array
     *   Safe to re-run.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'two_factor_secret')) {
                DB::statement('ALTER TABLE users MODIFY two_factor_secret TEXT NULL');
            }
            if (Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                DB::statement('ALTER TABLE users MODIFY two_factor_recovery_codes TEXT NULL');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'two_factor_recovery_codes')) {
                DB::statement('ALTER TABLE users MODIFY two_factor_recovery_codes VARCHAR(100) NULL');
            }
            if (Schema::hasColumn('users', 'two_factor_secret')) {
                DB::statement('ALTER TABLE users MODIFY two_factor_secret VARCHAR(16) NULL');
            }
        });
    }
};