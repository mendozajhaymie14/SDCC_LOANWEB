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
        Schema::dropIfExists('applications');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Leaving empty since loan_applications is now your primary table
    }
};