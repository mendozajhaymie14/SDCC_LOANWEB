<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a JSON column that holds the applicant's character references
     * (up to three entries, each with a full name, address and contact
     * number). Safe to re-run: only adds the column when it is missing.
     */
    public function up(): void
    {
        if (Schema::hasTable('member_applications') && ! Schema::hasColumn('member_applications', 'character_references')) {
            Schema::table('member_applications', function (Blueprint $table) {
                $table->json('character_references')->nullable()->after('proof_of_billing');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('member_applications') && Schema::hasColumn('member_applications', 'character_references')) {
            Schema::table('member_applications', function (Blueprint $table) {
                $table->dropColumn('character_references');
            });
        }
    }
};
