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
            // Demographics columns (not yet added)
            if (!Schema::hasColumn('member_applications', 'middle_name')) {
                $table->string('middle_name')->nullable()->after('first_name');
            }
            if (!Schema::hasColumn('member_applications', 'suffix')) {
                $table->string('suffix')->nullable()->after('middle_name');
            }
            if (!Schema::hasColumn('member_applications', 'house_no')) {
                $table->string('house_no')->nullable()->after('city_of_birth');
            }
            if (!Schema::hasColumn('member_applications', 'street')) {
                $table->string('street')->nullable()->after('house_no');
            }
            if (!Schema::hasColumn('member_applications', 'barangay')) {
                $table->string('barangay')->nullable()->after('street');
            }
            if (!Schema::hasColumn('member_applications', 'municipality')) {
                $table->string('municipality')->nullable()->after('barangay');
            }
            if (!Schema::hasColumn('member_applications', 'zip_code')) {
                $table->string('zip_code', 20)->nullable()->after('municipality');
            }
            if (!Schema::hasColumn('member_applications', 'stay_years')) {
                $table->integer('stay_years')->nullable()->after('zip_code');
            }
            if (!Schema::hasColumn('member_applications', 'stay_months')) {
                $table->integer('stay_months')->nullable()->after('stay_years');
            }
            if (!Schema::hasColumn('member_applications', 'residency_type')) {
                $table->string('residency_type', 50)->nullable()->after('stay_months');
            }

            // Permanent address columns
            if (!Schema::hasColumn('member_applications', 'perm_house_no')) {
                $table->string('perm_house_no')->nullable()->after('residency_type');
            }
            if (!Schema::hasColumn('member_applications', 'perm_street')) {
                $table->string('perm_street')->nullable()->after('perm_house_no');
            }
            if (!Schema::hasColumn('member_applications', 'perm_barangay')) {
                $table->string('perm_barangay')->nullable()->after('perm_street');
            }
            if (!Schema::hasColumn('member_applications', 'perm_municipality')) {
                $table->string('perm_municipality')->nullable()->after('perm_barangay');
            }
            if (!Schema::hasColumn('member_applications', 'perm_zip_code')) {
                $table->string('perm_zip_code', 20)->nullable()->after('perm_municipality');
            }
            if (!Schema::hasColumn('member_applications', 'perm_stay_years')) {
                $table->integer('perm_stay_years')->nullable()->after('perm_zip_code');
            }
            if (!Schema::hasColumn('member_applications', 'perm_stay_months')) {
                $table->integer('perm_stay_months')->nullable()->after('perm_stay_years');
            }

            // Demographics columns
            if (!Schema::hasColumn('member_applications', 'occupation')) {
                $table->string('occupation')->nullable()->after('perm_stay_months');
            }
            if (!Schema::hasColumn('member_applications', 'reviewed_at')) {
                $table->dateTime('reviewed_at')->nullable()->after('occupation');
            }
            if (!Schema::hasColumn('member_applications', 'reviewed_by')) {
                $table->unsignedBigInteger('reviewed_by')->nullable()->after('reviewed_at');
            }
            if (!Schema::hasColumn('member_applications', 'approved_member_id')) {
                $table->unsignedBigInteger('approved_member_id')->nullable()->after('reviewed_by');
            }
            if (!Schema::hasColumn('member_applications', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('approved_member_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('member_applications', function (Blueprint $table) {
            $table->dropColumn([
                'middle_name',
                'suffix',
                'house_no',
                'street',
                'barangay',
                'municipality',
                'zip_code',
                'stay_years',
                'stay_months',
                'residency_type',
                'perm_house_no',
                'perm_street',
                'perm_barangay',
                'perm_municipality',
                'perm_zip_code',
                'perm_stay_years',
                'perm_stay_months',
                'occupation',
                'reviewed_at',
                'reviewed_by',
                'approved_member_id',
                'user_id',
            ]);
        });
    }
};