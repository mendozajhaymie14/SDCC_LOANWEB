<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Proof-of-payment upload for repayments. Nullable so every existing
     * payment row stays valid; sdcc:backfill-payment-recorders does not
     * touch this column.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('payments', 'proof_path')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->string('proof_path')->nullable()->after('notes');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('payments', 'proof_path')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('proof_path');
            });
        }
    }
};
