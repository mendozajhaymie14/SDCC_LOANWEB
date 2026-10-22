<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * coop_members had no contact number, but the register form autofills it
 * from the cooperative record and staff need a way to put one on file.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coop_members') || Schema::hasColumn('coop_members', 'contact_number')) {
            return;
        }

        Schema::table('coop_members', function (Blueprint $table) {
            $table->string('contact_number', 20)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('coop_members') || ! Schema::hasColumn('coop_members', 'contact_number')) {
            return;
        }

        Schema::table('coop_members', function (Blueprint $table) {
            $table->dropColumn('contact_number');
        });
    }
};
