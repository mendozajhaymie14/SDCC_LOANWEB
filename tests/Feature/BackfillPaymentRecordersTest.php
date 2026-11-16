<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\AuditLog;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillPaymentRecordersTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function dry_run_changes_nothing_and_apply_sets_encoded_by_only_on_unambiguous_matches(): void
    {
        $staff = User::factory()->asLoanOfficer()->create(['name' => 'Staff Encoder']);

        $loanApplication = LoanApplication::factory()->create([
            'amount' => 10000,
            'term_months' => 12,
            'status' => 'approved',
            'remaining_balance' => 10661.88,
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id' => 'APP-0001',
            'amount' => 10000,
            'term_months' => 12,
            'status' => 'Approved',
            'remaining_balance' => 10661.88,
        ]);

        // Unambiguous: one payment matching this amount
        $target = Payment::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'amount_paid' => 2000,
            'reference_no' => 'UNIQ-REF',
            'encoded_by' => null,
        ]);

        // Ambiguous: two payments with the same amount on the same loan
        $amb1 = Payment::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'amount_paid' => 1500,
            'reference_no' => 'AMB-1',
            'encoded_by' => null,
        ]);
        $amb2 = Payment::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'amount_paid' => 1500,
            'reference_no' => 'AMB-2',
            'encoded_by' => null,
        ]);

        // Already encoded: must be left alone
        $done = Payment::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'amount_paid' => 3000,
            'reference_no' => 'DONE-REF',
            'encoded_by' => $staff->id,
        ]);

        // Amount with no matching payment at all
        Payment::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'amount_paid' => 999,
            'reference_no' => 'NO-LOG-REF',
            'encoded_by' => null,
        ]);

        AuditLog::log('Payment Recorded', "Repayment of ₱2000 recorded for APP-0001", $staff->id);
        AuditLog::log('Payment Recorded', "Repayment of ₱1500 recorded for APP-0001", $staff->id);
        AuditLog::log('Payment Recorded', "Repayment of ₱3000 recorded for APP-0001", $staff->id);

        // Dry run: nothing written
        $this->artisan('sdcc:backfill-payment-recorders --dry-run')->assertExitCode(0);
        $this->assertNull($target->fresh()->encoded_by);
        $this->assertNull($amb1->fresh()->encoded_by);
        $this->assertNull($amb2->fresh()->encoded_by);
        $this->assertEquals($staff->id, $done->fresh()->encoded_by);

        // Apply: only the unambiguous match gets encoded_by
        $this->artisan('sdcc:backfill-payment-recorders --apply')->assertExitCode(0);
        $this->assertEquals($staff->id, $target->fresh()->encoded_by);
        $this->assertNull($amb1->fresh()->encoded_by);
        $this->assertNull($amb2->fresh()->encoded_by);
        $this->assertEquals($staff->id, $done->fresh()->encoded_by);
        $this->assertNull(Payment::where('reference_no', 'NO-LOG-REF')->first()->encoded_by);
    }

    /** @test */
    public function it_refuses_to_run_on_the_protected_sdcc_database(): void
    {
        // The guard reads the configured database name; simulate sdcc by
        // overriding the sqlite connection's name (NOT database.default —
        // flipping the default connection breaks RefreshDatabase teardown
        // for every test that runs after this one).
        config(['database.connections.sqlite.database' => 'sdcc']);

        $this->artisan('sdcc:backfill-payment-recorders --dry-run')->assertExitCode(1);
    }
}
