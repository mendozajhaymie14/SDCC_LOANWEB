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
        Schema::table('member_applications', function (Blueprint $table) {
            if (!Schema::hasColumn('member_applications', 'province')) {
                $table->string('province', 255)->nullable()->after('municipality');
            }
            if (!Schema::hasColumn('member_applications', 'perm_province')) {
                $table->string('perm_province', 255)->nullable()->after('perm_municipality');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_applications', function (Blueprint $table) {
            if (Schema::hasColumn('member_applications', 'perm_province')) {
                $table->dropColumn('perm_province');
            }
            if (Schema::hasColumn('member_applications', 'province')) {
                $table->dropColumn('province');
            }
        });
    }
};