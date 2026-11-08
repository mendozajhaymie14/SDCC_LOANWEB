<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('member_applications', function (Blueprint $table) {
            $table->decimal('savings_balance', 12, 2)->nullable()->after('existing_monthly_obligations');
        });
    }

    public function down(): void {
        Schema::table('member_applications', function (Blueprint $table) {
            $table->dropColumn('savings_balance');
        });
    }
};
