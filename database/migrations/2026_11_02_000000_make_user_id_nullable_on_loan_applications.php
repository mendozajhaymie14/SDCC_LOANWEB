<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Staff-encoded applications may be created for a cooperative member that
     * has not yet been linked to a registered user account. The user_id column
     * must therefore be nullable so a member's first loan can be encoded
     * before the member has a login.
     */
    public function up(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('loan_applications', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};