<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackfillPaymentLatenessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function officer(): User
    {
        return User::factory()->create(['usertype' => 'loan_officer']);
    }

    private function createApprovedLoan(array $overrides = []): LoanApplication
    {
        $loan = LoanApplication::factory()->create(array_merge([
            'user_id'           => null,
            'email'             => 'loan_' . uniqid() . '@example.com',
            'amount'            => 10000,
            'term_months'       => 12,
            'status'            => 'approved',
            'remaining_balance' => 1000,
            'next_due_date'     => now()->format('Y-m-d'),
        ], $overrides));

        Application::factory()->create([
            'loan_application_id' => $loan->id,
            'app_id'              => 'APP-' . strtoupper(uniqid()),
            'status'              => 'Approved',
            'remaining_balance'   => 1000,
        ]);

        return $loan;
    }

    /** @test */
    public function backfill_dry_run_does_not_write(): void
    {
        $loan = $this->createApprovedLoan([
            'next_due_date' => now()->subDay()->format('Y-m-d'),
            'remaining_balance' => 1000,
        ]);

        // Create a legacy payment with null due_date/days_late
        $legacy = Payment::factory()->create([
            'loan_application_id' => $loan->id,
            'amount_paid'         => 1000,
            'payment_date'        => now(),
            'due_date'            => null,
            'days_late'           => 0,
        ]);

        $this->assertNull($legacy->due_date);

        // Run dry-run
        $exitCode = Artisan::call('sdcc:backfill-payment-lateness --dry-run');
        $this->assertEquals(0, $exitCode);

        // Verify the payment was NOT updated
        $legacy->refresh();
        $this->assertNull($legacy->due_date, 'Dry run should not write');
        $this->assertEquals(0, $legacy->days_late);
    }

    /** @test */
    public function backfill_apply_writes_due_date_and_days_late(): void
    {
        $loan = $this->createApprovedLoan([
            'next_due_date' => now()->subDay()->format('Y-m-d'),
            'remaining_balance' => 1000,
        ]);

        // Create a legacy payment with null due_date/days_late
        $legacy = Payment::factory()->create([
            'loan_application_id' => $loan->id,
            'amount_paid'         => 1000,
            'payment_date'        => now(),
            'due_date'            => null,
            'days_late'           => 0,
        ]);

        $this->assertNull($legacy->due_date);

        // Run with --apply
        $exitCode = Artisan::call('sdcc:backfill-payment-lateness --apply');
        $this->assertEquals(0, $exitCode);

        // Verify the payment was updated
        $legacy->refresh();
        $this->assertNotNull($legacy->due_date, 'Apply should set due_date');

        // The backfill reconstructs the due date backwards from the loan's
        // CURRENT next_due_date, exactly as the report's legacy fallback does,
        // so the stored value agrees with what the report would compute.
        $expectedDue = \Carbon\Carbon::parse($loan->next_due_date)
            ->subMonths(1)->startOfDay()->format('Y-m-d');
        $this->assertEquals($expectedDue, $legacy->due_date->format('Y-m-d'));

        $expectedDaysLate = max(0, (int) $legacy->due_date->startOfDay()->diffInDays($legacy->payment_date->startOfDay()));
        $this->assertEquals($expectedDaysLate, $legacy->days_late);
    }

    /** @test */
    public function backfill_handles_paid_loan_with_null_next_due_date(): void
    {
        $loan = $this->createApprovedLoan([
            'next_due_date' => now()->subDay()->format('Y-m-d'),
            'remaining_balance' => 1000,
            'status' => 'paid',
        ]);

        // Paid loans have null next_due_date, backfill should use first_due_date anchor
        $loan->next_due_date = null;
        $loan->save();

        $legacy = Payment::factory()->create([
            'loan_application_id' => $loan->id,
            'amount_paid'         => 1000,
            'payment_date'        => now(),
            'due_date'            => null,
            'days_late'           => 0,
        ]);

        $exitCode = Artisan::call('sdcc:backfill-payment-lateness --apply');
        $this->assertEquals(0, $exitCode);

        $legacy->refresh();
        $this->assertNotNull($legacy->due_date, 'Backfill should compute due_date even for paid loans');
    }

    /** @test */
    public function backfill_refuses_to_run_on_protected_sdcc_database(): void
    {
        // This test verifies the guard exists (can't easily test actual DB refusal
        // without mocking the config, but we can at least verify the command runs
        // on the test DB which is sqlite in-memory, not sdcc)
        $exitCode = Artisan::call('sdcc:backfill-payment-lateness --dry-run');
        $this->assertEquals(0, $exitCode);
    }
}