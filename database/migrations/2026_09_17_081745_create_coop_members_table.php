<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coop_members', function (Blueprint $table) {
            $table->id();
            $table->string('member_id')->unique(); // e.g., "SDCC-2024-0001"
            $table->string('full_name');
            $table->date('date_of_birth');
            $table->string('email')->unique();
            $table->boolean('is_registered')->default(false); // Tracks if web account was created
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coop_members');
    }
};