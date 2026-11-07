<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Records which staff user recorded each payment. Nullable so existing
     * payment rows can be backfilled via sdcc:backfill-payment-recorders.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('payments', 'recorded_by')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->unsignedBigInteger('recorded_by')->nullable()->after('encoded_by');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('payments', 'recorded_by')) {
            Schema::table('payments', function (Blueprint $table) {
                $table->dropColumn('recorded_by');
            });
        }
    }
};
