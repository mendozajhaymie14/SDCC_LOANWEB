<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\CoopMember;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\User;
use App\Http\Controllers\AdminController;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Route;
use ReflectionMethod;
use Tests\TestCase;

class PaymentLatenessSnapshotTest extends TestCase
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

    private function callPerformanceData($filters = null)
    {
        $controller = app(AdminController::class);
        $method = new ReflectionMethod($controller, 'borrowerPerformanceData');
        $method->setAccessible(true);
        return $method->invoke($controller, $filters ?? [
            'report_type'   => 'borrower_performance',
            'start_date'    => null,
            'end_date'      => null,
            'status'        => null,
            'risk_level'    => null,
            'status_closures' => [
                'status'       => null,
                'risk'         => null,
                'date'         => null,
                'overdue_date' => null,
            ],
        ]);
    }

    /** @test */
    public function step_1_reproduction_on_time_miscounted_before_snapshot(): void
    {
        // This test documents the ORIGINAL bug that the snapshot fixes.
        //
        // A legacy payment (null due_date / days_late) was actually paid 1 day
        // late: due yesterday, paid today. The loan's CURRENT next_due_date is
        // then ADVANCED by a month (e.g. by a later payment or reconciliation).
        // The report's legacy fallback reconstructs that old payment's due date
        // by subtracting months from the ADVANCED next_due_date, which lands on
        // today — so the payment that was 1 day late is counted as ON TIME.

        $loan = $this->createApprovedLoan([
            'next_due_date' => now()->format('Y-m-d'), // CURRENT next_due_date = today
        ]);

        // Legacy payment: truly 1 day late (due yesterday, paid today), but no
        // snapshot columns populated.
        $legacy = Payment::factory()->create([
            'loan_application_id'      => $loan->id,
            'amount_paid'              => 1000,
            'remaining_balance_after'  => 0,
            'reference_no'             => 'REP-LEGACY-1',
            'payment_date'             => now()->format('Y-m-d'),
            'payment_method'           => 'Cash',
            'proof_path'               => 'payment-proofs/legacy.jpg',
            'due_date'                 => null,
            'days_late'                => 0,
        ]);

        // Advance next_due_date forward as later payments/reconciliation would
        // do (this is what drifts and miscounts old payments under reconstruction).
        $loan->next_due_date = now()->addMonth()->format('Y-m-d');
        $loan->save();

        $rows = $this->callPerformanceData();
        $row = $rows->first();

        // The payment's true lateness: paid today, due yesterday = 1 day late.
        // With the buggy legacy fallback it lands on "today" reconstructed due
        // date → days_late = 0 → counted ON TIME. That is the bug this test
        // documents: the legacy fallback counts a 1-day-late payment as on-time.
        $this->assertSame(1, $row->on_time_count, 'Bug: legacy fallback counts a 1-day-late payment as on-time');
        $this->assertSame(0, $row->late_count, 'Bug: legacy fallback counts a 1-day-late payment as on-time');
    }

    /** @test */
    public function snapshot_freezes_lateness_for_new_payments(): void
    {
        // Loan due yesterday; full payment today → 1 day late.
        $loan = $this->createApprovedLoan([
            'next_due_date' => now()->subDay()->format('Y-m-d'),
            'remaining_balance' => 1000,
        ]);

        $officer = $this->officer();
        $app = Application::where('loan_application_id', $loan->id)->first();

        $this->actingAs($officer)->post(route('repayments.store'), [
            'application_id' => $app->id,
            'amount'         => 1000,
            'payment_method' => 'Cash',
            'payment_date'   => now()->format('Y-m-d'),
            'proof'          => \Illuminate\Http\UploadedFile::fake()->image('proof.jpg'),
        ])->assertSessionHasNoErrors();

        $payment = Payment::where('loan_application_id', $loan->id)->first();

        // Snapshot frozen at recording time: due_date = yesterday, days_late = 1
        $this->assertEquals(now()->subDay()->format('Y-m-d'), $payment->due_date->format('Y-m-d'));
        $this->assertEquals(1, $payment->days_late);
        $this->assertEquals('Late (1 day)', $payment->latenessLabel());
    }

    /** @test */
    public function payment_paid_one_day_late_is_classified_as_late_not_overdue(): void
    {
        $loan = $this->createApprovedLoan([
            'next_due_date' => now()->subDay()->format('Y-m-d'),
            'remaining_balance' => 1000,
        ]);

        $officer = $this->officer();
        $app = Application::where('loan_application_id', $loan->id)->first();

        $this->actingAs($officer)->post(route('repayments.store'), [
            'application_id' => $app->id,
            'amount'         => 1000,
            'payment_method' => 'Cash',
            'payment_date'   => now()->format('Y-m-d'),
            'proof'          => \Illuminate\Http\UploadedFile::fake()->image('proof.jpg'),
        ]);

        $payment = Payment::where('loan_application_id', $loan->id)->first();

        $this->assertEquals(1, $payment->days_late);
        $this->assertEquals('Late (1 day)', $payment->latenessLabel());

        $rows = $this->callPerformanceData();
        $row = $rows->first();

        $this->assertSame(0, $row->on_time_count);
        $this->assertSame(1, $row->late_count);
        $this->assertSame(0, $row->overdue_count);
    }

    /** @test */
    public function payment_paid_on_due_date_is_on_time(): void
    {
        $loan = $this->createApprovedLoan([
            'next_due_date' => now()->format('Y-m-d'),
            'remaining_balance' => 1000,
        ]);

        $officer = $this->officer();
        $app = Application::where('loan_application_id', $loan->id)->first();

        $this->actingAs($officer)->post(route('repayments.store'), [
            'application_id' => $app->id,
            'amount'         => 1000,
            'payment_method' => 'Cash',
            'payment_date'   => now()->format('Y-m-d'),
            'proof'          => \Illuminate\Http\UploadedFile::fake()->image('proof.jpg'),
        ]);

        $payment = Payment::where('loan_application_id', $loan->id)->first();

        $this->assertEquals(0, $payment->days_late);
        $this->assertEquals('On time', $payment->latenessLabel());

        $rows = $this->callPerformanceData();
        $row = $rows->first();

        $this->assertSame(1, $row->on_time_count);
        $this->assertSame(0, $row->late_count);
        $this->assertSame(0, $row->overdue_count);
    }

    /** @test */
    public function payment_paid_31_days_late_is_overdue(): void
    {
        $loan = $this->createApprovedLoan([
            'next_due_date' => now()->subDays(31)->format('Y-m-d'),
            'remaining_balance' => 1000,
        ]);

        $officer = $this->officer();
        $app = Application::where('loan_application_id', $loan->id)->first();

        $this->actingAs($officer)->post(route('repayments.store'), [
            'application_id' => $app->id,
            'amount'         => 1000,
            'payment_method' => 'Cash',
            'payment_date'   => now()->format('Y-m-d'),
            'proof'          => \Illuminate\Http\UploadedFile::fake()->image('proof.jpg'),
        ]);

        $payment = Payment::where('loan_application_id', $loan->id)->first();

        $this->assertEquals(31, $payment->days_late);
        $this->assertEquals('Very late (31 days)', $payment->latenessLabel());

        $rows = $this->callPerformanceData();
        $row = $rows->first();

        $this->assertSame(0, $row->on_time_count);
        $this->assertSame(1, $row->late_count);
        $this->assertSame(1, $row->overdue_count);
    }

    /** @test */
    public function editing_next_due_date_later_does_not_change_old_payment_lateness(): void
    {
        $loan = $this->createApprovedLoan([
            'next_due_date' => now()->format('Y-m-d'),
            'remaining_balance' => 2000,
        ]);

        $officer = $this->officer();
        $app = Application::where('loan_application_id', $loan->id)->first();

        // Payment 1: on time (paid today, due today)
        $this->actingAs($officer)->post(route('repayments.store'), [
            'application_id' => $app->id,
            'amount'         => 1000,
            'payment_method' => 'Cash',
            'payment_date'   => now()->format('Y-m-d'),
            'proof'          => \Illuminate\Http\UploadedFile::fake()->image('proof1.jpg'),
        ]);

        $payment1 = Payment::where('loan_application_id', $loan->id)->orderBy('id')->first();
        $this->assertEquals(0, $payment1->days_late);
        $this->assertEquals('On time', $payment1->latenessLabel());

        // Advance the loan's next_due_date forward (simulating an edit /
        // reconciliation that moves the schedule).
        $loan->next_due_date = now()->addDays(5)->format('Y-m-d');
        $loan->save();

        // Payment 2: on the new due date → on time
        $this->actingAs($officer)->post(route('repayments.store'), [
            'application_id' => $app->id,
            'amount'         => 1000,
            'payment_method' => 'Cash',
            'payment_date'   => $loan->next_due_date,
            'proof'          => \Illuminate\Http\UploadedFile::fake()->image('proof2.jpg'),
        ]);

        $payment2 = Payment::where('loan_application_id', $loan->id)->orderBy('id', 'desc')->first();
        $this->assertEquals(0, $payment2->days_late);

        // Payment 1 still reads On time — its snapshot did not move
        $payment1->refresh();
        $this->assertEquals(0, $payment1->days_late);
        $this->assertEquals('On time', $payment1->latenessLabel());

        $rows = $this->callPerformanceData();
        $row = $rows->first();

        $this->assertSame(2, $row->on_time_count);
        $this->assertSame(0, $row->late_count);
        $this->assertSame(0, $row->overdue_count);
    }

    /** @test */
    public function reversed_payment_removed_from_counts_and_shows_reversed_label(): void
    {
        $loan = $this->createApprovedLoan([
            'next_due_date' => now()->subDay()->format('Y-m-d'),
            'remaining_balance' => 1000,
        ]);

        $manager = User::factory()->asBranchManager()->create();
        $app = Application::where('loan_application_id', $loan->id)->first();

        $this->actingAs($manager)->post(route('repayments.store'), [
            'application_id' => $app->id,
            'amount'         => 1000,
            'payment_method' => 'Cash',
            'payment_date'   => now()->format('Y-m-d'),
            'proof'          => \Illuminate\Http\UploadedFile::fake()->image('proof.jpg'),
        ]);

        $payment = Payment::where('loan_application_id', $loan->id)->first();
        $this->assertEquals(1, $payment->days_late);

        $rows = $this->callPerformanceData();
        $this->assertSame(1, $rows->first()->late_count);

        // Reverse the payment (soft delete)
        $this->actingAs($manager)->post(route('repayments.reverse', $payment->id), [
            'reason' => 'Test reversal reason here',
        ]);

        $payment->refresh();
        $this->assertTrue($payment->trashed());
        $this->assertEquals('Reversed', $payment->latenessLabel());

        // Report: excluded from all counts
        $rows = $this->callPerformanceData();
        $row = $rows->first();
        $this->assertSame(0, $row->on_time_count);
        $this->assertSame(0, $row->late_count);
        $this->assertSame(0, $row->overdue_count);
    }
}
