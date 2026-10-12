<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();

            // The loan this payment is applied to.
            $table->foreignId('loan_application_id')
                ->constrained('loan_applications')
                ->cascadeOnDelete();

            // The member who made the payment (denormalised from the loan so
            // the payment history can be queried without joining through the
            // loan every time).
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->decimal('amount_paid', 12, 2)->default(0);
            $table->decimal('remaining_balance_after', 12, 2)->default(0);
            $table->string('reference_no', 50)->nullable();
            $table->date('payment_date');
            $table->foreignId('encoded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->text('notes')->nullable();

            $table->timestamps();

            // Fast lookups for the repayment history table and the reminder
            // command, which both scan by loan + date.
            $table->index(['loan_application_id', 'payment_date'], 'payments_loan_date_idx');
            $table->index('payment_date', 'payments_date_idx');
            $table->index('user_id', 'payments_user_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['loan_application_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['encoded_by']);
        });

        Schema::dropIfExists('payments');
    }
};