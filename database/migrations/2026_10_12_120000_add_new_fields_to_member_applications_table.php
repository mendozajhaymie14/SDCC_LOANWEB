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
            $table->string('last_name')->nullable()->after('surname');
            $table->enum('sex', ['male', 'female', 'lgbtqia+'])->nullable()->after('gender');
            $table->string('country_of_birth')->nullable()->after('nationality');
            $table->string('city_of_birth')->nullable()->after('place_of_birth');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_applications', function (Blueprint $table) {
            $table->dropColumn(['last_name', 'sex', 'country_of_birth', 'city_of_birth']);
        });
    }
};
