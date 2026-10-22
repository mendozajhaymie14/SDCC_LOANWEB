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
        if (!Schema::hasColumn('applications', 'last_scanned_at')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->timestamp('last_scanned_at')->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('applications', 'last_scanned_at')) {
            Schema::table('applications', function (Blueprint $table) {
                $table->dropColumn('last_scanned_at');
            });
        }
    }
};
