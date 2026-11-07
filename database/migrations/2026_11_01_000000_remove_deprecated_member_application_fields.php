<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Make deprecated member_application fields nullable (no data loss).
     * The form no longer collects these; existing values are preserved.
     */
    public function up(): void
    {
        Schema::table('member_applications', function (Blueprint $table) {
            $columns = [
                'suffix',
                'sex',
                'civil_status',
                'birthdate',
                'tin',
                'id_picture',
                'proof_of_billing',
                'country_of_birth',
                'gender',
                'character_references',
                'city_of_birth',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('member_applications', $column)) {
                    // Make nullable instead of dropping — keeps data, no irreversible loss
                    $table->text($column)->nullable()->change();
                }
            }
        });
    }

    /**
     * Reverse: restore original column types (best-effort, since we changed to nullable text).
     * The down() is informational; the original types must match the pre-migration schema.
     */
    public function down(): void
    {
        Schema::table('member_applications', function (Blueprint $table) {
            $table->string('suffix', 10)->nullable()->change();
            $table->enum('sex', ['male', 'female', 'lgbtqia+'])->nullable()->change();
            $table->enum('civil_status', ['single', 'married', 'legally_separated', 'annulled', 'widowed', 'widower'])->nullable()->change();
            $table->date('birthdate')->nullable()->change();
            $table->string('tin')->nullable()->change();
            $table->string('id_picture')->nullable()->change();
            $table->string('proof_of_billing')->nullable()->change();
            $table->string('country_of_birth')->nullable()->change();
            $table->enum('gender', ['male', 'female', 'lgbtqia+'])->nullable()->change();
            $table->text('character_references')->nullable()->change();
            $table->string('city_of_birth')->nullable()->change();
        });
    }
};