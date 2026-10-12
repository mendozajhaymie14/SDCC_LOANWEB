<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('member_applications', function (Blueprint $table) {
            $table->id();
            $table->string('surname');
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('email')->unique();
            $table->string('contact_number')->nullable();
            $table->string('tin')->nullable();
            $table->string('place_of_birth')->nullable();
            $table->string('nationality')->nullable();
            $table->enum('gender', ['male', 'female', 'lgbtqia+'])->nullable();
            $table->enum('civil_status', ['single', 'married', 'legally_separated', 'annulled', 'widowed', 'widower'])->nullable();
            $table->text('character_references')->nullable();
            $table->string('id_picture')->nullable();
            $table->string('proof_of_billing')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('member_applications');
    }
};