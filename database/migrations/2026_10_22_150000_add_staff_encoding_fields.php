<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task D (FR-05 - FR-09): staff encoding of member profiles and loans.
     *
     * coop_members gains the member-profile fields a loan officer types in
     * (address, occupation) and the financial-profile fields the evaluation
     * engine reads (monthly income, existing monthly obligations). Savings,
     * share capital and the approved credit limit already live on this table.
     *
     * loan_applications gains encoded_by: the staff member who typed the
     * application in, so a staff-encoded submission stays distinguishable
     * from a member self-submission.
     *
     * Safe to re-run: every column is guarded by Schema::hasColumn().
     */
    public function up(): void
    {
        if (Schema::hasTable('coop_members')) {
            Schema::table('coop_members', function (Blueprint $table) {
                if (! Schema::hasColumn('coop_members', 'address')) {
                    $table->text('address')->nullable()->after('contact_number');
                }
                if (! Schema::hasColumn('coop_members', 'occupation')) {
                    $table->string('occupation', 100)->nullable()->after('address');
                }
                if (! Schema::hasColumn('coop_members', 'monthly_income')) {
                    $table->decimal('monthly_income', 12, 2)->nullable()->after('occupation');
                }
                if (! Schema::hasColumn('coop_members', 'existing_monthly_obligations')) {
                    $table->decimal('existing_monthly_obligations', 12, 2)->nullable()->after('monthly_income');
                }
            });
        }

        if (Schema::hasTable('loan_applications') && ! Schema::hasColumn('loan_applications', 'encoded_by')) {
            Schema::table('loan_applications', function (Blueprint $table) {
                $table->foreignId('encoded_by')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('loan_applications') && Schema::hasColumn('loan_applications', 'encoded_by')) {
            Schema::table('loan_applications', function (Blueprint $table) {
                $table->dropForeign(['encoded_by']);
                $table->dropColumn('encoded_by');
            });
        }

        if (Schema::hasTable('coop_members')) {
            $columns = array_values(array_filter(
                ['address', 'occupation', 'monthly_income', 'existing_monthly_obligations'],
                fn (string $column) => Schema::hasColumn('coop_members', $column)
            ));

            if ($columns) {
                Schema::table('coop_members', function (Blueprint $table) use ($columns) {
                    $table->dropColumn($columns);
                });
            }
        }
    }
};
