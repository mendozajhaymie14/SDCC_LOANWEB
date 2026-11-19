<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot the due date and lateness at payment time.
 *
 * The Borrower Performance report used to reconstruct each payment's due date
 * by subtracting months from the loan's CURRENT next_due_date. That value
 * moves after every payment, edit and reconciliation, so a payment that was
 * once on time could later be reported as late (or vice-versa). Storing the
 * due date and the signed lateness on the payment row freezes the result at
 * the moment the payment is recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (! Schema::hasColumn('payments', 'due_date')) {
                $table->date('due_date')->nullable()->after('payment_date');
            }
            if (! Schema::hasColumn('payments', 'days_late')) {
                $table->integer('days_late')->default(0)->after('due_date');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('payments')) {
            return;
        }

        Schema::table('payments', function (Blueprint $table) {
            if (Schema::hasColumn('payments', 'due_date')) {
                $table->dropColumn('due_date');
            }
            if (Schema::hasColumn('payments', 'days_late')) {
                $table->dropColumn('days_late');
            }
        });
    }
};