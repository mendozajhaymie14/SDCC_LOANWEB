<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Standalone membership-application form for people who are not yet
     * registered cooperative members. Kept separate from coop_members so
     * that a pending application never pollutes the live member registry.
     *
     * Safe to re-run: creates the table only when it is missing, and
     * adds the soft-delete column only when it is missing too.
     */
    public function up(): void
    {
        if (!Schema::hasTable('member_applications')) {
            Schema::create('member_applications', function (Blueprint $table) {
                $table->id();

                // ── Identity ──
                $table->string('surname');
                $table->string('first_name');
                $table->string('middle_name')->nullable();

                // ── Present address ──
                $table->string('house_no')->nullable();
                $table->string('street')->nullable();
                $table->string('barangay')->nullable();
                $table->string('municipality')->nullable();
                $table->string('zip_code')->nullable();
                $table->unsignedSmallInteger('stay_years')->nullable();
                $table->unsignedSmallInteger('stay_months')->nullable();

                // ── Permanent address ──
                $table->string('perm_house_no')->nullable();
                $table->string('perm_street')->nullable();
                $table->string('perm_barangay')->nullable();
                $table->string('perm_municipality')->nullable();
                $table->string('perm_zip_code')->nullable();
                $table->unsignedSmallInteger('perm_stay_years')->nullable();
                $table->unsignedSmallInteger('perm_stay_months')->nullable();

                // ── Demographics ──
                $table->enum('residency_type', ['owned', 'rented', 'mortgage', 'living_with_relatives']);
                $table->string('contact_number');
                $table->string('email')->unique();
                $table->date('birthdate');
                $table->string('nationality');
                $table->string('place_of_birth');
                $table->enum('gender', ['male', 'female', 'lgbtqia+']);
                $table->string('occupation');
                $table->enum('civil_status', ['single', 'married', 'legally_separated', 'annulled', 'widowed', 'widower']);

                // ── Workflow ──
                $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
                $table->timestamp('reviewed_at')->nullable();
                $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
                $table->foreignId('approved_member_id')->nullable()->constrained('coop_members')->onDelete('set null');
                $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');

                $table->timestamps();
                $table->softDeletes();
            });
        } elseif (!Schema::hasColumn('member_applications', 'deleted_at')) {
            Schema::table('member_applications', function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('member_applications');
    }
};