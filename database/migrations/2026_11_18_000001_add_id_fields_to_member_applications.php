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
            $table->string('id_type')->nullable()->after('rejection_reason');
            $table->string('id_number')->nullable()->after('id_type');
            $table->string('id_front_path')->nullable()->after('id_number');
            $table->string('id_back_path')->nullable()->after('id_front_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_applications', function (Blueprint $table) {
            $table->dropColumn(['id_type', 'id_number', 'id_front_path', 'id_back_path']);
        });
    }
};
