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
            if (!Schema::hasColumn('member_applications', 'date_of_birth')) {
                $table->date('date_of_birth')->nullable()->after('occupation');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_applications', function (Blueprint $table) {
            if (Schema::hasColumn('member_applications', 'date_of_birth')) {
                $table->dropColumn('date_of_birth');
            }
        });
    }
};
