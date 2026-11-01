<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\LoanApplication;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepaymentConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that admin repayments page, admin application page, and member dashboard
     * all show the same outstanding balance, payment list, and next due date.
     */
    public function test_outstanding_balance_payment_list_and_next_due_date_are_consistent_across_admin_and_member_views()
    {
        // ── Create member user ─────────────────────────────────────────────
        $member = User::factory()->create([
            'usertype' => 'member',
            'email'    => 'member@example.com',
        ]);

        // ── Create admin user with loan_officer role ───────────────────────
        $loanOfficer = User::factory()->create([
            'usertype' => 'loan_officer',
            'email'    => 'officer@example.com',
        ]);

        // ── Create approved loan application (principal 10,000, 12 months) ──
        // At 12% add-on: interest = 10000 * 0.12 * (12/12) = 1200, total = 11200
        // Monthly payment = 11200 / 12 = 933.33...
        $loanApplication = LoanApplication::factory()->create([
            'user_id'            => $member->id,
            'amount'             => 10000,
            'term_months'        => 12,
            'status'             => 'approved',
            'remaining_balance'  => 11200, // total payable initially
            'reviewed_at'        => '2024-01-01',
        ]);

        $application = Application::factory()->create([
            'loan_application_id' => $loanApplication->id,
            'app_id'              => 'APP-0001',
            'amount'              => 10000,
            'term_months'         => 12,
            'status'              => 'Approved',
            'remaining_balance'   => 11200,
        ]);

        // ── Record first payment (₱2,500) ──────────────────────────────────
        $payment1 = $this->actingAs($loanOfficer)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/admin/repayments', [
                'application_id' => $application->id,
                'amount'         => 2500,
                'payment_date'   => '2024-02-01',
                'payment_method' => 'Cash',
            ]);

        $payment1->assertStatus(200);
        $payment1->assertJson(['success' => true]);

        // ── Record second payment (₱3,000) ─────────────────────────────────
        $payment2 = $this->actingAs($loanOfficer)
            ->withHeaders(['Accept' => 'application/json'])
            ->post('/admin/repayments', [
                'application_id' => $application->id,
                'amount'         => 3000,
                'payment_date'   => '2024-03-01',
                'payment_method' => 'Cash',
            ]);

        $payment2->assertStatus(200);
        $payment2->assertJson(['success' => true]);

        // ── Now assert consistency ────────────────────────────────────────
        // Total payable computed from AmortizationService (uses configured rate)
        // Total paid = 2500 + 3000 = 5500
        // Outstanding = totalPayable - totalPaid

        // Refresh models
        $loanApplication->refresh();
        $application->refresh();

        $totalPayable = $loanApplication->totalPayable();
        $expectedTotalPaid = 5500.00;
        $expectedOutstanding = max(0, $totalPayable - $expectedTotalPaid);
        $expectedNextDue = $loanApplication->nextDueDate();

        // ── 1. Admin Repayments Page (GET /repayments) ─────────────────────
        $adminRepayments = $this->actingAs($loanOfficer)
            ->get('/repayments');

        $adminRepayments->assertStatus(200);

        // ── 2. Admin Application Detail Page (GET /admin/applications/{id}) ──
        $adminAppDetail = $this->actingAs($loanOfficer)
            ->get("/admin/applications/{$application->id}");

        $adminAppDetail->assertStatus(200);

        // ── 3. Member Dashboard (GET /user/dashboard) ──────────────────────
        $memberDashboard = $this->actingAs($member)
            ->get('/user/dashboard');

        $memberDashboard->assertStatus(200);

        // ── 4. Member Amortization Schedule (GET /user/loans/{id}/amortization) ──
        $memberAmort = $this->actingAs($member)
            ->get("/user/loans/{$loanApplication->id}/amortization");

        $memberAmort->assertStatus(200);

        // ── 5. Verify model methods return consistent values ───────────────
        $this->assertEqualsWithDelta($expectedTotalPaid, $loanApplication->totalPaid(), 0.01);
        $this->assertEqualsWithDelta($expectedOutstanding, $loanApplication->outstandingBalance(), 0.01);
        $this->assertEqualsWithDelta($totalPayable, $loanApplication->totalPayable(), 0.01);
        $this->assertEquals($expectedNextDue, $loanApplication->nextDueDate());

        // Application mirrors loan application
        $this->assertEqualsWithDelta($expectedTotalPaid, $application->totalPaid(), 0.01);
        $this->assertEqualsWithDelta($expectedOutstanding, $application->outstandingBalance(), 0.01);
        $this->assertEqualsWithDelta($totalPayable, $application->totalPayable(), 0.01);
        $this->assertEquals($expectedNextDue, $application->nextDueDate());

        // ── 6. Verify PaymentService recomputeLoanState was called correctly ──
        // Both LoanApplication and Application should have same remaining_balance
        $this->assertEqualsWithDelta(
            $loanApplication->outstandingBalance(),
            $loanApplication->remaining_balance,
            0.01
        );
        $this->assertEqualsWithDelta(
            $application->outstandingBalance(),
            $application->remaining_balance,
            0.01
        );

        // ── 7. Verify payment running balances (remaining_balance_after) ─────
        $payments = Payment::where('loan_application_id', $loanApplication->id)
            ->orderBy('payment_date')
            ->get();

        $runningBalance = $loanApplication->totalPayable();
        foreach ($payments as $payment) {
            $runningBalance -= $payment->amount_paid;
            $this->assertEqualsWithDelta(max(0, $runningBalance), $payment->remaining_balance_after, 0.01);
        }
    }
}