<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Reverse-tracking on payments: a reversed payment is soft-deleted, so
     * every Payment::sum()/query in the app automatically excludes it, and
     * the columns record who reversed it and why.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'deleted_at')) {
                $table->softDeletes();
            }
            if (! Schema::hasColumn('payments', 'reversed_by')) {
                $table->unsignedBigInteger('reversed_by')->nullable()->after('encoded_by');
            }
            if (! Schema::hasColumn('payments', 'reversal_reason')) {
                $table->text('reversal_reason')->nullable()->after('reversed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $columns = ['reversal_reason', 'reversed_by', 'deleted_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
