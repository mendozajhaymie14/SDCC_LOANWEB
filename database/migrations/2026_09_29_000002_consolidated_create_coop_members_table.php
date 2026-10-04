<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Single-purpose migration — no prior versions to consolidate.
     *
     * Safe to re-run: creates the table only when it is missing, and
     * adds the soft-delete column only when it is missing too.
     */
    public function up(): void
    {
        if (!Schema::hasTable('coop_members')) {
            Schema::create('coop_members', function (Blueprint $table) {
                $table->id();
                $table->string('member_id')->unique(); // e.g., "SDCC-2024-0001"
                $table->string('full_name');
                $table->date('date_of_birth');
                $table->string('email')->unique();
                $table->boolean('is_registered')->default(false);
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
                $table->softDeletes();
            });
        } elseif (!Schema::hasColumn('coop_members', 'deleted_at')) {
            Schema::table('coop_members', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coop_members');
    }
};