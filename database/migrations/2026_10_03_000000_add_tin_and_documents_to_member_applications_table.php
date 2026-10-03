<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the TIN and supporting-document paths to the membership
     * application table. Each column is only added when missing, so the
     * migration is safe to re-run on already-provisioned databases.
     */
    public function up(): void
    {
        if (Schema::hasTable('member_applications')) {
            Schema::table('member_applications', function (Blueprint $table) {
                if (! Schema::hasColumn('member_applications', 'tin')) {
                    $table->string('tin')->nullable()->after('civil_status');
                }

                if (! Schema::hasColumn('member_applications', 'id_picture')) {
                    $table->string('id_picture')->nullable()->after('tin');
                }

                if (! Schema::hasColumn('member_applications', 'proof_of_billing')) {
                    $table->string('proof_of_billing')->nullable()->after('id_picture');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('member_applications')) {
            Schema::table('member_applications', function (Blueprint $table) {
                if (Schema::hasColumn('member_applications', 'tin')) {
                    $table->dropColumn('tin');
                }
                if (Schema::hasColumn('member_applications', 'id_picture')) {
                    $table->dropColumn('id_picture');
                }
                if (Schema::hasColumn('member_applications', 'proof_of_billing')) {
                    $table->dropColumn('proof_of_billing');
                }
            });
        }
    }
};
